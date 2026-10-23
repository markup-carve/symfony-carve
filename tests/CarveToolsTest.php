<?php

declare(strict_types=1);

namespace MarkupCarve\SymfonyCarve\Tests;

use InvalidArgumentException;
use MarkupCarve\SymfonyCarve\CarveTools;
use PHPUnit\Framework\TestCase;

final class CarveToolsTest extends TestCase
{
    public function testFormatPrintsCanonicalSource(): void
    {
        $tools = new CarveTools();

        self::assertSame("# Title\n", $tools->format("#   Title\n"));
        self::assertTrue($tools->isFormatted("# Title\n"));
        self::assertFalse($tools->isFormatted("#   Title\n"));
    }

    public function testFormatAlignsTablesOnRequest(): void
    {
        $source = "| a | bbb |\n| --- | --- |\n| cccc | d |\n";
        $tools = new CarveTools();

        $aligned = $tools->format($source, true);

        self::assertNotSame($tools->format($source), $aligned);
        self::assertStringContainsString('| a    | bbb |', $aligned);
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

            self::assertSame([], $tools->lint($source));
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
        $tools = new CarveTools();

        self::assertSame('*bold*', trim($tools->import('**bold**', 'markdown')));
        self::assertSame('*bold*', trim($tools->import('<p><strong>bold</strong></p>', 'html')));
        self::assertSame('*bold*', trim($tools->import('*bold*', 'djot')));
    }

    public function testImportRefusesAnUnknownFormat(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new CarveTools())->import('x', 'rst');
    }
}
