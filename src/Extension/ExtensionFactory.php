<?php

declare(strict_types=1);

namespace MarkupCarve\SymfonyCarve\Extension;

use InvalidArgumentException;
use MarkupCarve\Carve\Extension\Claim;
use MarkupCarve\Carve\Extension\ExtensionDefinition;
use MarkupCarve\Carve\Extension\ExtensionProvider;
use MarkupCarve\Carve\Extension\RenderHost;
use MarkupCarve\Carve\Extension\Tabs;
use MarkupCarve\Carve\Node;

final class ExtensionFactory
{
    /**
     * @var array<string>
     */
    public const TYPES = ['tabs', 'mermaid', 'plantuml', 'd2', 'graphviz', 'wavedrom', 'vega_lite', 'chart', 'abc'];

    /**
     * @param array<mixed> $entries
     *
     * @return array<\MarkupCarve\Carve\Extension\ExtensionDefinition|\MarkupCarve\Carve\Extension\ExtensionProvider>
     */
    public function createAll(array $entries): array
    {
        $definitions = [];
        foreach ($entries as $entry) {
            foreach ($this->create($entry) as $definition) {
                $definitions[] = $definition;
            }
        }

        return $definitions;
    }

    /**
     * @throws \InvalidArgumentException
     *
     * @return array<\MarkupCarve\Carve\Extension\ExtensionDefinition|\MarkupCarve\Carve\Extension\ExtensionProvider>
     */
    public function create(mixed $config): array
    {
        if ($config instanceof ExtensionDefinition || $config instanceof ExtensionProvider) {
            return [$config];
        }
        if (is_string($config)) {
            $config = ['type' => $config];
        }
        if (!is_array($config) || !is_string($config['type'] ?? null)) {
            throw new InvalidArgumentException('A Carve extension entry needs a "type".');
        }
        $type = $config['type'];
        unset($config['type']);
        if ($type === 'tabs' && $config === []) {
            return Tabs::extensions(true);
        }
        if (in_array($type, ['frontmatter', 'table_of_contents', 'autolink', 'smart_quotes'], true)) {
            if ($config !== []) {
                throw new InvalidArgumentException(sprintf('Unknown option "%s" for core Carve 0.2 extension "%s".', (string)array_key_first($config), $type));
            }

            // C2-T-003: these are core Carve constructs and need no registration.

            return [];
        }
        if (in_array($type, self::TYPES, true) && $type !== 'tabs' && $config === []) {
            return [$this->diagram($type)];
        }
        if ($type === 'wikilinks' && is_string($config['url_template'] ?? null) && count($config) === 1) {
            return [$this->wiki($config['url_template'])];
        }

        throw new InvalidArgumentException(sprintf('Carve 0.2 extension "%s" is not available yet.', $type));
    }

    private function wiki(string $urlTemplate): ExtensionDefinition
    {
        $url = new WikiLinkUrlTemplate($urlTemplate);

        return new ExtensionDefinition(
            'symfony.wiki',
            '1',
            matcher: ['form' => 'inline', 'name' => 'wiki'],
            transform: static function (Claim $claim): Node {
                $node = new Node('inline_extension', []);
                $node->f = [
                    'name' => 'symfony.wiki',
                    'version' => '1',
                    'slots' => [],
                    'props' => ['page' => self::plainText($claim->node)],
                    'fallback' => [$claim->copyNode()],
                ];

                return $node;
            },
            renderer: static fn (Node $node, RenderHost $host): string => '<a href="'
                . RenderHost::escapeAttribute($url((string)($node->f['props']['page'] ?? '')))
                . '">' . RenderHost::escapeText((string)($node->f['props']['page'] ?? '')) . '</a>',
        );
    }

    private static function plainText(Node $node): string
    {
        $text = (string)($node->f['value'] ?? '');
        foreach ($node->children ?? [] as $child) {
            $text .= self::plainText($child);
        }

        return $text;
    }

    private function diagram(string $language): ExtensionDefinition
    {
        $fence = $language === 'vega_lite' ? 'vega-lite' : $language;

        return new ExtensionDefinition(
            'symfony.' . $language,
            '1',
            matcher: ['form' => 'fence', 'name' => $fence],
            transform: static function (Claim $claim) use ($language): Node {
                $node = new Node('block_extension', []);
                $node->f = [
                    'name' => 'symfony.' . $language,
                    'version' => '1',
                    'slots' => [],
                    'props' => ['body' => $claim->source['body'] ?? ''],
                    'fallback' => [$claim->copyNode()],
                ];

                return $node;
            },
            renderer: static fn (Node $node, RenderHost $host): string => '<pre class="' . $language
                . '" role="img" aria-label="' . $language . '">'
                . RenderHost::escapeText((string)($node->f['props']['body'] ?? '')) . '</pre>',
        );
    }
}
