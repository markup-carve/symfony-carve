<?php

declare(strict_types=1);

namespace MarkupCarve\SymfonyCarve\Extension;

use InvalidArgumentException;
use MarkupCarve\Carve\Extension\ExtensionDefinition;
use MarkupCarve\Carve\Extension\ExtensionProvider;
use MarkupCarve\Carve\Extension\Name;

class EngineExtensionFactory
{
    /**
     * Names this adapter handles itself; the renderer applies the heading offset.
     *
     * @var list<string>
     */
    private const HEADING_LEVELS = ['heading_level_shift', 'heading_levels'];

    /**
     * @return list<string>
     */
    public static function types(): array
    {
        $types = array_merge(Name::values(), self::HEADING_LEVELS);
        sort($types);

        return $types;
    }

    /**
     * @param \MarkupCarve\Carve\Extension\ExtensionDefinition|\MarkupCarve\Carve\Extension\ExtensionProvider|\MarkupCarve\Carve\Extension\Name|array<mixed, mixed>|string $config
     *
     * @throws \InvalidArgumentException
     *
     * @return list<\MarkupCarve\Carve\Extension\ExtensionDefinition|\MarkupCarve\Carve\Extension\ExtensionProvider>
     */
    public function create(ExtensionDefinition|ExtensionProvider|Name|array|string $config): array
    {
        if ($config instanceof ExtensionDefinition || $config instanceof ExtensionProvider) {
            return [$config];
        }
        if ($config instanceof Name || is_string($config)) {
            $config = ['type' => $config];
        }
        $type = $config['type'] ?? null;
        if ($type instanceof Name) {
            $type = $type->value;
        }
        if (in_array($type, self::HEADING_LEVELS, true)) {
            self::acceptKeys($config, ['type', 'offset']);
            $offset = $config['offset'] ?? 1;
            if (!is_int($offset)) {
                throw new InvalidArgumentException('Heading level offset must be an integer.');
            }

            return [];
        }
        $name = is_string($type) ? Name::tryFrom($type) : null;
        if ($name === null) {
            throw new InvalidArgumentException(sprintf(
                'Carve 0.2 extension "%s" is not available yet.',
                is_string($type) ? $type : get_debug_type($type),
            ));
        }
        unset($config['type']);

        return $name->create($config);
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
}
