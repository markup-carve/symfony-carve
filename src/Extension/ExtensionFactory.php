<?php

declare(strict_types=1);

namespace MarkupCarve\SymfonyCarve\Extension;

use InvalidArgumentException;
use MarkupCarve\Carve\Extension\Claim;
use MarkupCarve\Carve\Extension\ExtensionDefinition;
use MarkupCarve\Carve\Extension\ExtensionProvider;
use MarkupCarve\Carve\Extension\RenderHost;
use MarkupCarve\Carve\Node;

final class ExtensionFactory
{
    /**
     * @var list<string>
     */
    public const TYPES = ['mermaid', 'plantuml', 'd2', 'graphviz', 'wavedrom', 'vega_lite', 'chart', 'abc'];

    /**
     * @param array<mixed> $entries
     *
     * @return list<\MarkupCarve\Carve\Extension\ExtensionDefinition|\MarkupCarve\Carve\Extension\ExtensionProvider>
     */
    public function createAll(array $entries): array
    {
        $definitions = [];
        foreach ($entries as $entry) {
            array_push($definitions, ...$this->create($entry));
        }

        return $definitions;
    }

    /**
     * @throws \InvalidArgumentException
     *
     * @return list<\MarkupCarve\Carve\Extension\ExtensionDefinition|\MarkupCarve\Carve\Extension\ExtensionProvider>
     */
    public function create(mixed $config): array
    {
        if (!is_string($config) && !is_array($config) && !$config instanceof ExtensionDefinition && !$config instanceof ExtensionProvider) {
            throw new InvalidArgumentException('A Carve extension entry needs a type.');
        }
        if ($config === 'frontmatter' || $config === ['type' => 'frontmatter']) {
            return [];
        }
        if (is_string($config) && in_array($config, self::TYPES, true)) {
            return $config === 'plantuml'
                ? [$this->diagram('plantuml'), $this->diagram('puml')]
                : ($config === 'graphviz' ? [$this->diagram('graphviz'), $this->diagram('dot')] : [$this->diagram($config)]);
        }

        return (new EngineExtensionFactory())->create($config);
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
