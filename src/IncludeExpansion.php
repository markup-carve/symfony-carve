<?php

declare(strict_types=1);

namespace MarkupCarve\SymfonyCarve;

use MarkupCarve\Carve\Node\Document;

/**
 * @internal
 */
final class IncludeExpansion
{
    /**
     * @param \MarkupCarve\Carve\Node\Document $document
     * @param list<array{rule: string|null, message: string, file: string|null, line: int, column: int}> $warnings
     * @param list<array{path: string, resolved: bool}> $dependencies
     * @param list<string> $targets Raw resolver identities, for cache invalidation only.
     * @param int $suppressedWarnings
     */
    public function __construct(
        public readonly Document $document,
        public readonly array $warnings,
        public readonly array $dependencies,
        public readonly array $targets,
        public readonly int $suppressedWarnings,
    ) {
    }
}
