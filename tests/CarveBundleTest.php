<?php

declare(strict_types=1);

namespace MarkupCarve\SymfonyCarve\Tests;

use MarkupCarve\SymfonyCarve\CarveBundle;
use MarkupCarve\SymfonyCarve\CarveRenderer;
use MarkupCarve\SymfonyCarve\CarveTools;
use MarkupCarve\SymfonyCarve\Command\ConvertCommand;
use MarkupCarve\SymfonyCarve\Command\FormatCommand;
use MarkupCarve\SymfonyCarve\Command\LintCommand;
use MarkupCarve\SymfonyCarve\Command\RenderCommand;
use MarkupCarve\SymfonyCarve\Twig\CarveExtension;
use MarkupCarve\SymfonyCarve\Validator\ValidCarveValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Config\Definition\Configuration;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Console\CommandLoader\CommandLoaderInterface;
use Symfony\Component\Console\DependencyInjection\AddConsoleCommandPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class CarveBundleTest extends TestCase
{
    public function testIncludeRootDefaultsToNull(): void
    {
        self::assertNull($this->process([])['include_root']);
    }

    public function testInvalidProfileIsRejectedByConfiguration(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->process(['profile' => 'unknown']);
    }

    public function testDefaultsCoverEveryRenderOption(): void
    {
        $config = $this->process([]);

        // C2-H-137: Carve uses rawHtml, urlPolicy, sections, and headingIds.
        self::assertFalse($config['raw_html']);
        self::assertSame([], $config['url_policy']);
        self::assertSame('interactive', $config['mode']);
        self::assertSame('full', $config['profile']);
        self::assertTrue($config['sections']);
        self::assertSame('keep-case', $config['heading_ids']);
        self::assertFalse($config['source_lines']);
        self::assertTrue($config['smart_typography']);
        self::assertSame([], $config['symbols']);
        self::assertSame([], $config['labels']);
        self::assertSame([], $config['extensions']);
        self::assertSame(['enabled' => false, 'pool' => 'cache.app', 'ttl' => null, 'prefix' => 'carve', 'version' => null], $config['cache']);
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function invalidConfigs(): array
    {
        // C2-H-137: removed 0.1 keys are replaced by the 0.2 option catalog.
        return [
            'raw_html string' => [['raw_html' => 'loose']],
            'mode' => [['mode' => 'print']],
            'heading_ids' => [['heading_ids' => 'tab']],
            'profile bad' => [['profile' => 'shout']],
            'smart_typography' => [['smart_typography' => 'yes']],
            'sections string' => [['sections' => 'yes']],
            'cache ttl' => [['cache' => ['ttl' => 0]]],
        ];
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('invalidConfigs')]
    public function testInvalidValuesAreRejected(array $config): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->process($config);
    }

    public function testRawHtmlCanBeExplicitlyDisabled(): void
    {
        // C2-H-127: rawHtml is the safe-mode switch and defaults off.
        self::assertFalse($this->process(['raw_html' => false])['raw_html']);
    }

    public function testTheContainerWiresEveryOptionIntoTheServices(): void
    {
        $container = $this->container([
            'raw_html' => false,
            'sections' => false,
            'extensions' => ['autolink'],
            'cache' => ['enabled' => true, 'pool' => 'app.carve_pool', 'prefix' => 'site'],
        ]);
        $pool = $container->get('app.carve_pool');
        self::assertInstanceOf(ArrayAdapter::class, $pool);

        $renderer = $container->get(CarveRenderer::class);
        self::assertInstanceOf(CarveRenderer::class, $renderer);
        $html = $renderer->render("a\nb <https://example.com>");

        // C2-H-137: sections is the supported layout option.
        self::assertStringNotContainsString('<section', $html);
        // C2-I-151: autolinks use the explicit angle form in 0.2.
        self::assertStringContainsString('href="https://example.com"', $html);
        self::assertStringStartsWith('site.render.', (string)array_key_first($pool->getValues()));
        self::assertInstanceOf(CarveTools::class, $container->get(CarveTools::class));
    }

    public function testTheTwigExtensionIsTagged(): void
    {
        $container = $this->container([], compile: false);

        self::assertArrayHasKey(CarveExtension::class, $container->findTaggedServiceIds('twig.extension'));
    }

    public function testCommandsAndTheValidatorAreTagged(): void
    {
        $container = $this->container([], compile: false);

        self::assertEqualsCanonicalizing(
            [RenderCommand::class, LintCommand::class, FormatCommand::class, ConvertCommand::class],
            array_keys($container->findTaggedServiceIds('console.command')),
        );
        self::assertArrayHasKey(ValidCarveValidator::class, $container->findTaggedServiceIds('validator.constraint_validator'));
    }

    public function testCommandsResolveFromACompiledContainer(): void
    {
        $container = $this->container([], compile: false);
        $container->addCompilerPass(new AddConsoleCommandPass());
        $container->compile();
        $loader = $container->get('console.command_loader');
        self::assertInstanceOf(CommandLoaderInterface::class, $loader);

        foreach (['carve:render', 'carve:lint', 'carve:fmt', 'carve:convert'] as $name) {
            self::assertSame($name, $loader->get($name)->getName());
        }
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private function process(array $config): array
    {
        $configuration = new Configuration(new CarveBundle(), null, 'carve');

        return (new Processor())->processConfiguration($configuration, [$config]);
    }

    /**
     * @param array<string, mixed> $config
     * @param bool $compile
     */
    private function container(array $config, bool $compile = true): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'test');
        $container->setParameter('kernel.debug', false);
        $container->setParameter('kernel.build_dir', sys_get_temp_dir());
        $extension = (new CarveBundle())->getContainerExtension();
        self::assertNotNull($extension);
        $container->register('app.carve_pool', ArrayAdapter::class)->setPublic(true);
        if (!$compile) {
            $extension->load([$config], $container);

            return $container;
        }
        $container->registerExtension($extension);
        $container->loadFromExtension('carve', $config);
        $container->compile();

        return $container;
    }
}
