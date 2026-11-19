<?php

declare(strict_types=1);

namespace MarkupCarve\SymfonyCarve;

final class LintFinding
{
    public function __construct(
        public readonly string $rule,
        public readonly string $message,
        public readonly ?string $file,
        public readonly int $line,
        public readonly int $column,
    ) {
    }
}
