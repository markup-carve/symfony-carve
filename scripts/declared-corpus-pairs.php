<?php

declare(strict_types=1);

/**
 * Counts the corpus pairs a spec example page declares.
 *
 * This lives in a file of its own, rather than inline in
 * .github/workflows/engine-drift.yml, so the counting can be unit-tested. The
 * workflow's population guard is only as good as this count, and a counter
 * embedded in a YAML `run:` block cannot be run against a case that proves it.
 *
 * Port of the spec's scripts/lib/example-pair-census.mjs: every `carve` fence
 * inside a `::: compare` block is one pair, a block may hold several, and
 * nothing inside an open fence is markup - so a four-backtick example holding a
 * three-backtick `carve` line declares no pair of its own.
 *
 * @param array<int, string> $lines
 */
function carveDeclaredCorpusPairs(array $lines): int
{
    $declared = 0;
    $marker = null;
    $fence = null;

    foreach ($lines as $line) {
        if ($fence !== null) {
            if (str_starts_with($line, $fence) && trim(substr($line, strlen($fence))) === '') {
                $fence = null;
            }

            continue;
        }

        $ticks = strspn($line, '`');
        if ($ticks >= 3) {
            $fence = substr($line, 0, $ticks);
            if ($marker !== null && trim(substr($line, $ticks)) === 'carve') {
                $declared++;
            }

            continue;
        }

        $trimmed = trim($line);
        $colons = strspn($trimmed, ':');
        if ($colons < 3) {
            continue;
        }

        if ($marker === null) {
            if (preg_match('/^[ \t]+compare(?:[ \t]|$)/', substr($trimmed, $colons)) === 1) {
                $marker = substr($trimmed, 0, $colons);
            }

            continue;
        }

        if ($trimmed === $marker) {
            $marker = null;
        }
    }

    return $declared;
}
