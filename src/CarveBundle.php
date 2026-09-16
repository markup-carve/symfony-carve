<?php

declare(strict_types=1);

namespace MarkupCarve\SymfonyCarve;

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
                ->booleanNode('safe_mode')
                    ->info('Enable HTML sanitization (recommended for untrusted input).')
                    ->defaultTrue()
                ->end()
                ->enumNode('raw_html')
                    ->info('How raw HTML is treated when safe_mode is on.')
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
                    ->info('Absolute containment root for opt-in file includes. String and Twig rendering remain literal.')
                    ->defaultNull()
                    ->validate()
                        ->ifTrue(static fn (mixed $value): bool => $value !== null && (!is_string($value) || !self::isAbsolutePath($value)))
                        ->thenInvalid('carve.include_root must be an absolute path.')
                    ->end()
                ->end()
            ->end();
    }

    /**
     * @param array{safe_mode: bool, raw_html: string, profile: string|null, diagrams: array<string>, include_root: string|null}|array<string, mixed> $config
     * @param \Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator $container
     * @param \Symfony\Component\DependencyInjection\ContainerBuilder $builder
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $services = $container->services();

        $services->set(CarveRenderer::class)
            ->args([
                $config['safe_mode'],
                $config['raw_html'],
                $config['diagrams'],
                $config['profile'],
                $config['include_root'],
                service('logger')->nullOnInvalid(),
            ])
            ->public();

        // Register the Twig extension only when Twig is installed, so the
        // bundle works in non-Twig apps without a hard dependency.
        if (class_exists(AbstractExtension::class)) {
            $services->set(CarveExtension::class)
                ->args([service(CarveRenderer::class)])
                ->tag('twig.extension');
        }
    }

    private static function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;
    }
}
