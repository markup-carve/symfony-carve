<?php

declare(strict_types=1);

namespace MarkupCarve\SymfonyCarve\Tests;

use InvalidArgumentException;
use MarkupCarve\Carve2\Carve2;
use MarkupCarve\SymfonyCarve\CarveTools;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class CarveToolsTest extends TestCase
{
    public function testFormatPrintsCanonicalSource(): void
    {
        $tools = new CarveTools();

        // C2-W-001 and U-043: the canonical heading marker is =.
        self::assertSame("= Title\n", $tools->format("#   Title\n"));
        self::assertTrue($tools->isFormatted("= Title\n"));
        self::assertFalse($tools->isFormatted("#   Title\n"));
    }

    public function testFormatAlignsTablesOnRequest(): void
    {
        // U-055: 0.2 tables use native header cells instead of a GFM separator.
        $source = "|= a |= bbb |\n| cccc | d |\n";
        $tools = new CarveTools();

        $aligned = $tools->format($source, true);

        self::assertNotSame($tools->format($source), $aligned);
        self::assertStringContainsString('| cccc | d    |', $aligned);
    }

    public function testLintReportsDocumentLinterFindings(): void
    {
        $findings = (new CarveTools())->lint("**bold**\n");

        self::assertNotSame([], $findings);
        self::assertNotSame('', $findings[0]->rule);
    }

    public function testLintWithIncludeRootFollowsIncludes(): void
    {
        $root = sys_get_temp_dir() . '/symfony-carve-lint-' . bin2hex(random_bytes(4));
        mkdir($root);
        file_put_contents($root . '/part.crv', "**bold**\n");
        $main = $root . '/main.crv';
        file_put_contents($main, "# Main\n\n{{ part.crv }}\n");

        try {
            $tools = new CarveTools();
            $source = (string)file_get_contents($main);
            $findings = $tools->lint($source, $root, $main);

            // C2-N-085: unresolved includes are lint findings even without a resolver.
            self::assertSame('include-no-resolver', $tools->lint($source)[0]->rule);
            self::assertNotSame([], $findings);
            self::assertStringEndsWith('part.crv', (string)$findings[0]->file);
        } finally {
            unlink($root . '/part.crv');
            unlink($main);
            rmdir($root);
        }
    }

    public function testLintRefusesARelativeIncludeRoot(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new CarveTools())->lint('x', 'relative/root');
    }

    public function testImportsMarkdownHtmlAndDjot(): void
    {
        // C2-T-003: these two importers are pending, see carve-php#5237 and #5242.
        $api = new ReflectionClass(Carve2::class);
        if (!$api->hasMethod('importMarkdown') || !$api->hasMethod('importDjot')) {
            $this->markTestSkipped('Pending carve-php#5237 and carve-php#5242.');
        }
        $tools = new CarveTools();

        self::assertSame('*bold*', trim($tools->import('**bold**', 'markdown')));
        self::assertSame('*bold*', trim($tools->import('<p><strong>bold</strong></p>', 'html')));
        self::assertSame('*bold*', trim($tools->import('*bold*', 'djot')));
    }

    public function testImportsHtmlWithCarve2(): void
    {
        // C2-M-001: HTML import is available in Carve2.
        self::assertSame('*bold*', trim((new CarveTools())->fromHtml('<p><strong>bold</strong></p>')));
    }

    public function testImportRefusesAnUnknownFormat(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new CarveTools())->import('x', 'rst');
    }
}
