<?php

declare(strict_types=1);

namespace MarkupCarve\SymfonyCarve\Tests;

use MarkupCarve\SymfonyCarve\CarveBundle;
use MarkupCarve\SymfonyCarve\CarveRenderer;
use MarkupCarve\SymfonyCarve\CarveTools;
use MarkupCarve\SymfonyCarve\Twig\CarveExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Config\Definition\Configuration;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
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

        self::assertTrue($config['safe_mode']);
        self::assertSame('interactive', $config['mode']);
        self::assertNull($config['soft_break_mode']);
        self::assertFalse($config['xhtml']);
        self::assertFalse($config['source_lines']);
        self::assertNull($config['smart_typography']);
        self::assertNull($config['on_disallowed']);
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
        return [
            'safe_mode string' => [['safe_mode' => 'loose']],
            'mode' => [['mode' => 'print']],
            'soft_break_mode' => [['soft_break_mode' => 'tab']],
            'on_disallowed' => [['on_disallowed' => 'shout']],
            'smart_typography' => [['smart_typography' => 'yes']],
            'extension entry' => [['extensions' => [['symbol' => '#']]]],
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

    public function testStrictSafeModeIsAccepted(): void
    {
        self::assertSame('strict', $this->process(['safe_mode' => 'strict'])['safe_mode']);
    }

    public function testTheContainerWiresEveryOptionIntoTheServices(): void
    {
        $container = $this->container([
            'safe_mode' => 'strict',
            'soft_break_mode' => 'br',
            'xhtml' => true,
            'extensions' => ['autolink'],
            'cache' => ['enabled' => true, 'pool' => 'app.carve_pool', 'prefix' => 'site'],
        ]);
        $pool = $container->get('app.carve_pool');
        self::assertInstanceOf(ArrayAdapter::class, $pool);

        $renderer = $container->get(CarveRenderer::class);
        self::assertInstanceOf(CarveRenderer::class, $renderer);
        $html = $renderer->render("a\nb https://example.com");

        self::assertStringContainsString('<br />', $html);
        self::assertStringContainsString('href="https://example.com"', $html);
        self::assertStringStartsWith('site.render.', (string)array_key_first($pool->getValues()));
        self::assertInstanceOf(CarveTools::class, $container->get(CarveTools::class));
    }

    public function testTheTwigExtensionIsTagged(): void
    {
        $container = $this->container([], compile: false);

        self::assertArrayHasKey(CarveExtension::class, $container->findTaggedServiceIds('twig.extension'));
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
