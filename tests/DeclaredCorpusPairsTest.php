<?php

declare(strict_types=1);

namespace MarkupCarve\SymfonyCarve\Tests;

use PHPUnit\Framework\TestCase;
use function carveDeclaredCorpusPairs;

require_once __DIR__ . '/../scripts/declared-corpus-pairs.php';

/**
 * The population guard in .github/workflows/engine-drift.yml is only as good as
 * this count, so the count has to be provable outside CI.
 */
final class DeclaredCorpusPairsTest extends TestCase
{
    public function testCountsEveryCarveFenceInABlock(): void
    {
        $page = implode("\n", [
            '::: compare',
            '```carve',
            'one',
            '```',
            '```html',
            '<p>one</p>',
            '```',
            '````carve',
            '```carve',
            'nested, not a pair',
            '```',
            '````',
            '```html',
            '<pre>two</pre>',
            '```',
            '```carve',
            'three',
            '```',
            '```html',
            '<p>three</p>',
            '```',
            ':::',
            '```carve',
            'outside any block',
            '```',
        ]);

        $this->assertSame(3, carveDeclaredCorpusPairs(explode("\n", $page)));
    }

    public function testCarveFenceOutsideAnyBlockDeclaresNoPair(): void
    {
        $page = implode("\n", [
            '```carve',
            'not in a compare block',
            '```',
        ]);

        $this->assertSame(0, carveDeclaredCorpusPairs(explode("\n", $page)));
    }
}
