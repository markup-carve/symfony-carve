<?php

declare(strict_types=1);

namespace MarkupCarve\SymfonyCarve\Extension;

use Closure;
use InvalidArgumentException;
use MarkupCarve\Carve\Extension\Citations;
use MarkupCarve\Carve\Extension\Claim;
use MarkupCarve\Carve\Extension\CodeCallouts;
use MarkupCarve\Carve\Extension\CodeGroup;
use MarkupCarve\Carve\Extension\ColorSwatch;
use MarkupCarve\Carve\Extension\Details;
use MarkupCarve\Carve\Extension\ExtensionDefinition;
use MarkupCarve\Carve\Extension\ExtensionProvider;
use MarkupCarve\Carve\Extension\FencedRender;
use MarkupCarve\Carve\Extension\Glossary;
use MarkupCarve\Carve\Extension\ImgFence;
use MarkupCarve\Carve\Extension\IndexTerms;
use MarkupCarve\Carve\Extension\ListTable;
use MarkupCarve\Carve\Extension\MathBlock;
use MarkupCarve\Carve\Extension\Navigation;
use MarkupCarve\Carve\Extension\RenderHost;
use MarkupCarve\Carve\Extension\SemanticSpan;
use MarkupCarve\Carve\Extension\SmartQuotes;
use MarkupCarve\Carve\Extension\Spoiler;
use MarkupCarve\Carve\Extension\Tabs;
use MarkupCarve\Carve\Extension\Transforms;
use MarkupCarve\Carve\Extension\Wikilinks;
use MarkupCarve\Carve\Node;
use ReflectionFunction;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;

class EngineExtensionFactory
{
    /**
     * Engine extension factories by configuration name. Options bind by name:
     * `css_class` in the configuration is the factory's `$cssClass` parameter.
     *
     * @var array<string, array{0: class-string, 1: string}>
     */
    private const NAMED = [
        'abc' => [FencedRender::class, 'abc'],
        'autolink' => [Navigation::class, 'autolink'],
        'chart' => [FencedRender::class, 'chart'],
        'citations' => [Citations::class, 'bundle'],
        'code_callouts' => [CodeCallouts::class, 'bundle'],
        'code_group' => [CodeGroup::class, 'bundle'],
        'color_swatch' => [ColorSwatch::class, 'bundle'],
        'd2' => [FencedRender::class, 'd2'],
        'default_attributes' => [Transforms::class, 'defaultAttributes'],
        'tab_normalize' => [Transforms::class, 'tabNormalize'],
        'inline_footnotes' => [Transforms::class, 'inlineFootnotesToParentheses'],
        'details' => [Details::class, 'bundle'],
        'fenced_render' => [FencedRender::class, 'bundle'],
        'glossary' => [Glossary::class, 'bundle'],
        'graphviz' => [FencedRender::class, 'graphviz'],
        'heading_numbers' => [Navigation::class, 'headingNumbers'],
        'heading_permalinks' => [Navigation::class, 'headingPermalinks'],
        'heading_reference' => [Navigation::class, 'headingReference'],
        'index_terms' => [IndexTerms::class, 'bundle'],
        'index' => [IndexTerms::class, 'bundle'],
        'link_policy' => [Navigation::class, 'linkPolicy'],
        'list_table' => [ListTable::class, 'bundle'],
        'math_block' => [MathBlock::class, 'bundle'],
        'mermaid' => [FencedRender::class, 'mermaid'],
        'semantic_span' => [SemanticSpan::class, 'bundle'],
        'smart_quotes' => [SmartQuotes::class, 'bundle'],
        'spoiler' => [Spoiler::class, 'bundle'],
        'table_of_contents' => [Navigation::class, 'tableOfContents'],
        'toc_placement' => [Navigation::class, 'tocPlacement'],
        'vega_lite' => [FencedRender::class, 'vegaLite'],
        'wavedrom' => [FencedRender::class, 'wavedrom'],
    ];

    /**
     * @return list<string>
     */
    public static function types(): array
    {
        $types = array_merge(
            array_keys(self::NAMED),
            ['external_links', 'heading_level_shift', 'heading_levels', 'img_fence', 'plantuml', 'tabs', 'wikilinks'],
        );
        sort($types);

        return $types;
    }

    /**
     * @param \MarkupCarve\Carve\Extension\ExtensionDefinition|\MarkupCarve\Carve\Extension\ExtensionProvider|array<mixed, mixed>|string $config
     *
     * @throws \InvalidArgumentException
     *
     * @return list<\MarkupCarve\Carve\Extension\ExtensionDefinition|\MarkupCarve\Carve\Extension\ExtensionProvider>
     */
    public function create(ExtensionDefinition|ExtensionProvider|array|string $config): array
    {
        if ($config instanceof ExtensionDefinition || $config instanceof ExtensionProvider) {
            return [$config];
        }
        if (is_string($config)) {
            $config = ['type' => $config];
        }
        $type = $config['type'] ?? null;
        if ($type === 'tabs') {
            self::acceptKeys($config, ['type']);

            return array_values(Tabs::extensions(true));
        }
        if ($type === 'plantuml') {
            self::acceptKeys($config, ['type', 'css_class']);
            $class = $config['css_class'] ?? 'plantuml';
            if (!is_string($class) || !preg_match('/^[a-zA-Z_][a-zA-Z0-9_-]*$/', $class)) {
                throw new InvalidArgumentException('Carve plantuml css_class must be one CSS class name.');
            }

            return [self::plantuml('plantuml', $class), self::plantuml('puml', $class)];
        }
        if ($type === 'external_links') {
            self::acceptKeys($config, ['type', 'rel', 'target', 'nofollow']);
            $target = $config['target'] ?? '_blank';
            $rel = $config['rel'] ?? 'noopener noreferrer';
            $nofollow = $config['nofollow'] ?? false;
            if (!is_string($target) || !is_string($rel) || !is_bool($nofollow)) {
                throw new InvalidArgumentException('External link target and rel must be strings.');
            }

            return [Navigation::externalLinks($target, $rel, $nofollow)];
        }
        if ($type === 'heading_levels' || $type === 'heading_level_shift') {
            self::acceptKeys($config, ['type', 'offset']);
            $offset = $config['offset'] ?? 1;
            if (!is_int($offset)) {
                throw new InvalidArgumentException('Heading level offset must be an integer.');
            }

            return [];
        }

        if ($type === 'wikilinks') {
            self::acceptKeys($config, ['type', 'url_template', 'css_class', 'new_window']);
            $template = $config['url_template'] ?? null;
            if ($template !== null && !is_string($template)) {
                throw new InvalidArgumentException('Wikilinks url_template must be a string.');
            }
            $class = $config['css_class'] ?? 'wikilink';
            $newWindow = $config['new_window'] ?? false;
            if (!is_string($class) || !is_bool($newWindow)) {
                throw new InvalidArgumentException('Wikilinks css_class must be a string and new_window a boolean.');
            }
            $url = $template === null ? null : static fn (string $page): string => str_replace(
                '{page}',
                Wikilinks::slug($page),
                $template,
            );

            return [Wikilinks::bundle($url, $class, $newWindow)];
        }
        if ($type === 'img_fence') {
            self::acceptKeys($config, ['type', 'language']);
            $languages = $config['language'] ?? ['img', 'image'];
            if (is_string($languages)) {
                $languages = [$languages];
            }
            if (!is_array($languages) || $languages === [] || array_filter($languages, 'is_string') !== $languages) {
                throw new InvalidArgumentException('img_fence language must be a fence name or a list of them.');
            }

            // carve-php ships the img fence renderer apart from its definition.
            return array_values(array_map(
                static fn (string $language): ExtensionDefinition => ImgFence::definition($language)
                    ->withRenderer(ImgFence::render(...))
                    ->withBuildTimeRenderer(ImgFence::render(...)),
                $languages,
            ));
        }
        if (is_string($type) && isset(self::NAMED[$type])) {
            return [self::named($type, $config)];
        }

        throw new InvalidArgumentException(sprintf(
            'Carve 0.2 extension "%s" is not available yet.',
            is_string($type) ? $type : get_debug_type($type),
        ));
    }

    /**
     * @param string $type
     * @param array<mixed, mixed> $config
     *
     * @throws \InvalidArgumentException
     */
    private static function named(string $type, array $config): ExtensionDefinition|ExtensionProvider
    {
        /** @var callable $callable */
        $callable = self::NAMED[$type];
        $factory = Closure::fromCallable($callable);
        $arguments = [];
        $parameters = [];
        foreach ((new ReflectionFunction($factory))->getParameters() as $parameter) {
            $parameters[$parameter->getName()] = $parameter;
        }
        foreach ($config as $key => $value) {
            if ($key === 'type') {
                continue;
            }
            $name = is_string($key) ? lcfirst(str_replace('_', '', ucwords($key, '_'))) : '';
            $parameter = $parameters[$name] ?? null;
            $kind = $parameter?->getType();
            $typeNames = match (true) {
                $kind instanceof ReflectionNamedType => [$kind->getName()],
                $kind instanceof ReflectionUnionType => array_map(
                    static fn (ReflectionType $member): string => $member instanceof ReflectionNamedType
                        ? $member->getName() : 'mixed',
                    $kind->getTypes(),
                ),
                default => ['mixed'],
            };
            if ($parameter === null || in_array(Closure::class, $typeNames, true)) {
                throw new InvalidArgumentException(sprintf(
                    'Unknown Carve extension option "%s" for "%s".',
                    (string)$key,
                    $type,
                ));
            }
            if ($value === null && $kind !== null && !$kind->allowsNull()) {
                throw new InvalidArgumentException(sprintf('Carve extension option "%s" must not be null.', $key));
            }
            $matching = array_filter($typeNames, static fn (string $typeName): bool => self::matches($value, $typeName));
            if ($value !== null && $matching === []) {
                throw new InvalidArgumentException(sprintf(
                    'Carve extension option "%s" for "%s" must be %s.',
                    $key,
                    $type,
                    (string)$kind,
                ));
            }
            $arguments[$name] = $value;
        }
        foreach ($parameters as $name => $parameter) {
            if (!$parameter->isOptional() && !array_key_exists($name, $arguments)) {
                throw new InvalidArgumentException(sprintf(
                    'Carve extension "%s" needs the option "%s".',
                    $type,
                    strtolower((string)preg_replace('/[A-Z]/', '_$0', $name)),
                ));
            }
        }
        $extension = $factory(...$arguments);
        if (!$extension instanceof ExtensionDefinition && !$extension instanceof ExtensionProvider) {
            throw new InvalidArgumentException(sprintf('Carve extension "%s" returned no definition.', $type));
        }

        return $extension;
    }

    private static function matches(mixed $value, string $type): bool
    {
        return match ($type) {
            'array' => is_array($value),
            'bool' => is_bool($value),
            'int' => is_int($value),
            'string' => is_string($value),
            'null' => $value === null,
            'mixed' => true,
            default => false,
        };
    }

    /**
     * @param array<mixed, mixed> $config
     * @param list<string> $accepted
     *
     * @throws \InvalidArgumentException
     */
    private static function acceptKeys(array $config, array $accepted): void
    {
        foreach (array_keys($config) as $key) {
            if (!is_string($key) || !in_array($key, $accepted, true)) {
                throw new InvalidArgumentException(sprintf('Unknown Carve extension option "%s".', (string)$key));
            }
        }
    }

    private static function plantuml(string $language, string $class): ExtensionDefinition
    {
        return new ExtensionDefinition(
            'host.plantuml.' . $language,
            '1',
            [],
            matcher: ['form' => 'fence', 'name' => $language],
            transform: static function (Claim $claim): Node {
                $node = new Node('block_extension', []);
                $node->f = [
                    'name' => 'host.plantuml.' . $claim->name,
                    'version' => '1',
                    'slots' => [],
                    'props' => ['body' => $claim->source['body'] ?? ''],
                    'fallback' => [$claim->copyNode()],
                ];

                return $node;
            },
            renderer: static function (Node $node, RenderHost $host) use ($class): string {
                $props = $node->f['props'] ?? [];
                $body = is_array($props) ? ($props['body'] ?? '') : '';

                return '<pre class="' . RenderHost::escapeAttribute($class) . '">'
                    . RenderHost::escapeText(is_string($body) ? $body : '') . '</pre>';
            },
        );
    }
}
