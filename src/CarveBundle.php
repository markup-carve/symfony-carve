<?php

declare(strict_types=1);

namespace MarkupCarve\SymfonyCarve;

use MarkupCarve\SymfonyCarve\Command\ConvertCommand;
use MarkupCarve\SymfonyCarve\Command\FormatCommand;
use MarkupCarve\SymfonyCarve\Command\LintCommand;
use MarkupCarve\SymfonyCarve\Command\RenderCommand;
use MarkupCarve\SymfonyCarve\Command\TraceCommand;
use MarkupCarve\SymfonyCarve\Twig\CarveExtension;
use MarkupCarve\SymfonyCarve\Validator\ValidCarveValidator;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Symfony\Component\Validator\ConstraintValidator;
use Twig\Extension\AbstractExtension;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

final class CarveBundle extends AbstractBundle
{
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->booleanNode('raw_html')
                    ->info('Allow trusted raw HTML. Disabled by default.')
                    ->defaultFalse()
                ->end()
                ->arrayNode('url_policy')
                    ->info('Optional URL scheme policy; the engine secure default applies when empty.')
                    ->variablePrototype()->end()
                    ->defaultValue([])
                ->end()
                ->enumNode('profile')
                    ->values(['full', 'article', 'comment', 'minimal'])
                    ->defaultValue('full')
                ->end()
                ->enumNode('mode')
                    ->values(['interactive', 'static'])
                    ->defaultValue('interactive')
                ->end()
                ->booleanNode('sections')->defaultTrue()->end()
                ->enumNode('heading_ids')
                    ->values(['off', 'keep-case', 'lower', 'ascii', 'ascii-lower', 'ascii-strict', 'ascii-strict-lower'])
                    ->defaultValue('keep-case')
                ->end()
                ->booleanNode('source_lines')->defaultFalse()->end()
                ->booleanNode('smart_typography')->defaultTrue()->end()
                ->arrayNode('symbols')
                    ->useAttributeAsKey('name')
                    ->scalarPrototype()->end()
                    ->defaultValue([])
                ->end()
                ->arrayNode('labels')
                    ->useAttributeAsKey('name')
                    ->scalarPrototype()->end()
                    ->defaultValue([])
                ->end()
                ->arrayNode('diagrams')
                    ->scalarPrototype()->end()
                    ->defaultValue([])
                ->end()
                ->arrayNode('extensions')
                    ->info('Carve2 extension definitions or a supported type such as tabs.')
                    ->variablePrototype()->end()
                    ->defaultValue([])
                ->end()
                ->scalarNode('include_root')
                    ->info('Absolute containment root for opt-in file includes.')
                    ->defaultNull()
                ->end()
                ->arrayNode('cache')
                    ->info('Cache rendered HTML in a Symfony cache pool, keyed by source and render options.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultFalse()->end()
                        ->scalarNode('pool')
                            ->info('Service id of a cache pool implementing Symfony\Contracts\Cache\CacheInterface.')
                            ->defaultValue('cache.app')
                            ->cannotBeEmpty()
                        ->end()
                        ->integerNode('ttl')
                            ->info('Lifetime in seconds. Null keeps the pool default.')
                            ->defaultNull()
                            ->min(1)
                        ->end()
                        ->scalarNode('prefix')
                            ->defaultValue('carve')
                            ->cannotBeEmpty()
                        ->end()
                        ->scalarNode('version')
                            ->info('Application version mixed into cache keys. Needed when an extension holds a closure.')
                            ->defaultNull()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    /**
     * @param array<string, mixed> $config
     * @param \Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator $container
     * @param \Symfony\Component\DependencyInjection\ContainerBuilder $builder
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $services = $container->services();
        /** @var array{enabled: bool, pool: string, ttl: int|null, prefix: string, version: string|null} $cache */
        $cache = $config['cache'];

        $services->set(CarveRenderer::class)
            ->args([
                '$rawHtml' => $config['raw_html'],
                '$urlPolicy' => $config['url_policy'],
                '$profile' => $config['profile'],
                '$includeRoot' => $config['include_root'],
                '$logger' => service('logger')->nullOnInvalid(),
                '$mode' => $config['mode'],
                '$sections' => $config['sections'],
                '$headingIds' => $config['heading_ids'],
                '$symbols' => $config['symbols'],
                '$sourceLines' => $config['source_lines'],
                '$smartTypography' => $config['smart_typography'],
                '$labels' => $config['labels'],
                '$extensions' => $config['extensions'],
                '$diagrams' => $config['diagrams'],
                '$cache' => $cache['enabled'] ? service($cache['pool']) : null,
                '$cacheTtl' => $cache['ttl'],
                '$cachePrefix' => $cache['prefix'],
                '$cacheVersion' => $cache['version'],
            ])
            ->public();

        $services->set(CarveTools::class)->public();

        foreach ([RenderCommand::class, LintCommand::class, FormatCommand::class, ConvertCommand::class, TraceCommand::class] as $command) {
            $services->set($command)->autowire()->tag('console.command');
        }

        // symfony/validator is optional; the constraint only exists with it.
        if (class_exists(ConstraintValidator::class)) {
            $services->set(ValidCarveValidator::class)
                ->args([service(CarveTools::class)])
                ->tag('validator.constraint_validator');
        }

        // Register the Twig extension only when Twig is installed, so the
        // bundle works in non-Twig apps without a hard dependency.
        if (class_exists(AbstractExtension::class)) {
            $services->set(CarveExtension::class)
                ->args([service(CarveRenderer::class), service(CarveTools::class)])
                ->tag('twig.extension');
        }
    }
}
