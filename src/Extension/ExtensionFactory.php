<?php

declare(strict_types=1);

namespace MarkupCarve\SymfonyCarve\Extension;

use InvalidArgumentException;
use MarkupCarve\Carve\Extension\ExtensionInterface;
use ReflectionClass;
use Throwable;

/**
 * Builds carve-php extensions from bundle configuration entries.
 *
 * An entry is a type name (`autolink`), an array with a `type` key and the
 * extension's constructor options in snake_case, an extension class name, or
 * an extension instance.
 */
final class ExtensionFactory
{
    /**
     * @var array<string>
     */
    public const TYPES = [
        'admonition',
        'ascii_heading_ids',
        'autolink',
        'citations',
        'code_callouts',
        'code_group',
        'color_swatch',
        'default_attributes',
        'details',
        'external_links',
        'fenced_render',
        'frontmatter',
        'glossary',
        'heading_level_shift',
        'heading_numbers',
        'heading_permalinks',
        'heading_reference',
        'img_fence',
        'index',
        'inline_footnotes',
        'list_table',
        'lowercase_heading_ids',
        'math_block',
        'mentions',
        'mermaid',
        'plantuml',
        'plus_bullet',
        'semantic_span',
        'smart_quotes',
        'spoiler',
        'tab_normalize',
        'table_of_contents',
        'tabs',
        'toc_placement',
        'wikilinks',
    ];

    /**
     * @param \MarkupCarve\Carve\Extension\ExtensionInterface|array<string, mixed>|string $config
     *
     * @throws \InvalidArgumentException
     */
    public function create(ExtensionInterface|array|string $config): ExtensionInterface
    {
        if ($config instanceof ExtensionInterface) {
            return $config;
        }
        if (is_string($config)) {
            $config = ['type' => $config];
        }
        $type = $config['type'] ?? null;
        if (!is_string($type) || $type === '') {
            throw new InvalidArgumentException('A Carve extension entry needs a "type".');
        }
        unset($config['type']);
        if ($type === 'mermaid' || $type === 'plantuml') {
            $config += ['language' => $type === 'mermaid' ? 'mermaid' : ['plantuml', 'puml']];
            $type = 'fenced_render';
        }
        if ($type === 'fenced_render') {
            $config += ['language' => 'mermaid'];
        }
        $class = is_a($type, ExtensionInterface::class, true)
            ? $type
            : 'MarkupCarve\\Carve\\Extension\\' . self::studly($type) . 'Extension';
        if (!is_a($class, ExtensionInterface::class, true)) {
            throw new InvalidArgumentException(sprintf('Unknown Carve extension "%s".', $type));
        }
        if ($type === 'wikilinks' && isset($config['url_template']) && is_string($config['url_template'])) {
            $config['url_generator'] = (new WikiLinkUrlTemplate($config['url_template']))(...);
            unset($config['url_template']);
        }
        $aliases = match ($type) {
            'heading_permalinks' => ['class' => 'css_class'],
            'table_of_contents' => ['toc_class' => 'css_class'],
            'wikilinks' => ['link_class' => 'css_class'],
            'mentions' => ['user_url_template' => 'mention_url', 'user_class' => 'mention_class'],
            default => [],
        };
        $arguments = [];
        foreach ($config as $key => $value) {
            $arguments[lcfirst(self::studly((string)($aliases[$key] ?? $key)))] = $value;
        }
        $constructor = (new ReflectionClass($class))->getConstructor();
        $parameters = $constructor === null ? [] : array_map(static fn ($parameter): string => $parameter->getName(), $constructor->getParameters());
        foreach (array_keys($arguments) as $argument) {
            if (!in_array($argument, $parameters, true)) {
                throw new InvalidArgumentException(sprintf('Unknown option "%s" for Carve extension "%s".', $argument, $type));
            }
        }

        try {
            return new $class(...$arguments);
        } catch (Throwable $exception) {
            throw new InvalidArgumentException(sprintf('Invalid options for Carve extension "%s": %s', $type, $exception->getMessage()), 0, $exception);
        }
    }

    private static function studly(string $value): string
    {
        return str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $value)));
    }
}
