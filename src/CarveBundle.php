<?php

declare(strict_types=1);

namespace Carve\Symfony;

use Carve\SafeMode;
use Carve\Symfony\Twig\CarveExtension;
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
            ->end();
    }

    /**
     * @param array{safe_mode: bool, raw_html: string} $config
     * @param \Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator $container
     * @param \Symfony\Component\DependencyInjection\ContainerBuilder $builder
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $services = $container->services();

        $services->set(CarveRenderer::class)
            ->args([$config['safe_mode'], $config['raw_html']])
            ->public();

        // Register the Twig extension only when Twig is installed, so the
        // bundle works in non-Twig apps without a hard dependency.
        if (class_exists(AbstractExtension::class)) {
            $services->set(CarveExtension::class)
                ->args([service(CarveRenderer::class)])
                ->tag('twig.extension');
        }
    }
}
