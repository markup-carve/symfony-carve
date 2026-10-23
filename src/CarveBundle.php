<?php

declare(strict_types=1);

namespace MarkupCarve\SymfonyCarve;

use MarkupCarve\Carve\Profile;
use MarkupCarve\Carve\Renderer\RenderMode;
use MarkupCarve\Carve\SafeMode;
use MarkupCarve\SymfonyCarve\Twig\CarveExtension;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Twig\Extension\AbstractExtension;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

final class CarveBundle extends AbstractBundle
{
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('safe_mode')
                    ->info('HTML sanitization: true, false or "strict". Keep it on for untrusted input.')
                    ->defaultTrue()
                    ->validate()
                        ->ifTrue(static fn (mixed $value): bool => !in_array($value, [true, false, 'strict'], true))
                        ->thenInvalid('carve.safe_mode must be true, false or "strict", got %s.')
                    ->end()
                ->end()
                ->enumNode('raw_html')
                    ->info('How raw HTML is treated when safe_mode is true.')
                    ->values([
                        SafeMode::RAW_HTML_STRIP,
                        SafeMode::RAW_HTML_ESCAPE,
                        SafeMode::RAW_HTML_ALLOW,
                    ])
                    ->defaultValue(SafeMode::RAW_HTML_STRIP)
                ->end()
                ->enumNode('profile')
                    ->info('Restrict the available markup features for a rendering context.')
                    ->values([null, 'full', 'article', 'comment', 'minimal'])
                    ->defaultNull()
                ->end()
                ->enumNode('on_disallowed')
                    ->info('What the profile does with markup it disallows: strip, to_text or error.')
                    ->values([null, Profile::ACTION_STRIP, Profile::ACTION_TO_TEXT, Profile::ACTION_ERROR])
                    ->defaultNull()
                ->end()
                ->enumNode('mode')
                    ->info('Render mode. "static" applies graceful degradation for print, PDF and email.')
                    ->values(RenderMode::ALL)
                    ->defaultValue(RenderMode::INTERACTIVE)
                ->end()
                ->enumNode('soft_break_mode')
                    ->info('How soft breaks render: newline, space or br. Null keeps the engine default.')
                    ->values([null, 'newline', 'space', 'br'])
                    ->defaultNull()
                ->end()
                ->booleanNode('xhtml')
                    ->info('Output XHTML-compatible self-closing tags.')
                    ->defaultFalse()
                ->end()
                ->booleanNode('source_lines')
                    ->info('Add 1-based data-source-line attributes for editor scroll-sync.')
                    ->defaultFalse()
                ->end()
                ->scalarNode('smart_typography')
                    ->info('Force smart typography on or off. Null keeps the engine default.')
                    ->defaultNull()
                    ->validate()
                        ->ifTrue(static fn (mixed $value): bool => !is_bool($value))
                        ->thenInvalid('carve.smart_typography must be true, false or null, got %s.')
                    ->end()
                ->end()
                ->arrayNode('symbols')
                    ->info('Trusted HTML replacements for :name: symbols, inserted without escaping.')
                    ->useAttributeAsKey('name')
                    ->scalarPrototype()->end()
                    ->defaultValue([])
                ->end()
                ->arrayNode('labels')
                    ->info('Overrides for the generated UI labels, keyed by label name.')
                    ->useAttributeAsKey('name')
                    ->scalarPrototype()->end()
                    ->defaultValue([])
                ->end()
                ->arrayNode('extensions')
                    ->info('carve-php extensions: a type name, or a map with "type" and the extension options in snake_case.')
                    ->variablePrototype()
                        ->validate()
                            ->ifTrue(static fn (mixed $value): bool => !is_string($value) && !(is_array($value) && is_string($value['type'] ?? null)))
                            ->thenInvalid('A carve.extensions entry must be a type name or a map with a "type" key, got %s.')
                        ->end()
                    ->end()
                    ->defaultValue([])
                ->end()
                ->arrayNode('diagrams')
                    ->info('Diagram/extension fenced-block presets to enable. Each needs a client-side renderer on the page (see README).')
                    ->enumPrototype()
                        ->values([
                            'mermaid',
                            'plantuml',
                            'd2',
                            'graphviz',
                            'wavedrom',
                            'vega_lite',
                            'chart',
                            'abc',
                        ])
                    ->end()
                    ->defaultValue([])
                ->end()
                ->scalarNode('include_root')
                    ->info('Absolute containment root for opt-in file includes. String and Twig rendering remain literal. The resolver refuses a relative root rather than resolving it against the working directory.')
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
                '$safeMode' => $config['safe_mode'],
                '$rawHtmlMode' => $config['raw_html'],
                '$diagrams' => $config['diagrams'],
                '$profile' => $config['profile'],
                '$includeRoot' => $config['include_root'],
                '$logger' => service('logger')->nullOnInvalid(),
                '$mode' => $config['mode'],
                '$softBreakMode' => $config['soft_break_mode'],
                '$xhtml' => $config['xhtml'],
                '$symbols' => $config['symbols'],
                '$sourceLines' => $config['source_lines'],
                '$smartTypography' => $config['smart_typography'],
                '$labels' => $config['labels'],
                '$onDisallowed' => $config['on_disallowed'],
                '$extensions' => $config['extensions'],
                '$cache' => $cache['enabled'] ? service($cache['pool']) : null,
                '$cacheTtl' => $cache['ttl'],
                '$cachePrefix' => $cache['prefix'],
                '$cacheVersion' => $cache['version'],
            ])
            ->public();

        $services->set(CarveTools::class)->public();

        // Register the Twig extension only when Twig is installed, so the
        // bundle works in non-Twig apps without a hard dependency.
        if (class_exists(AbstractExtension::class)) {
            $services->set(CarveExtension::class)
                ->args([service(CarveRenderer::class), service(CarveTools::class)])
                ->tag('twig.extension');
        }
    }
}
