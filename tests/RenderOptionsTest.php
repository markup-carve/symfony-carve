<?php

declare(strict_types=1);

namespace MarkupCarve\SymfonyCarve\Tests;

use InvalidArgumentException;
use MarkupCarve\Carve\Extension\TableOfContentsExtension;
use MarkupCarve\SymfonyCarve\CarveRenderer;
use PHPUnit\Framework\TestCase;

final class RenderOptionsTest extends TestCase
{
    public function testStrictSafeModeIsAccepted(): void
    {
        $html = (new CarveRenderer(safeMode: 'strict'))->render('[x](javascript:alert(1))');

        self::assertStringNotContainsString('javascript:', $html);
    }

    public function testAnUnknownSafeModeIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new CarveRenderer(safeMode: 'loose');
    }

    public function testSoftBreakModeBr(): void
    {
        self::assertStringContainsString('<br>', (new CarveRenderer(softBreakMode: 'br'))->render("a\nb"));
        self::assertStringNotContainsString('<br', (new CarveRenderer())->render("a\nb"));
    }

    public function testAnUnknownSoftBreakModeIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new CarveRenderer(softBreakMode: 'tab');
    }

    public function testXhtmlSelfClosesVoidElements(): void
    {
        self::assertStringContainsString('<hr />', (new CarveRenderer(xhtml: true))->render('***'));
    }

    public function testSourceLinesAddLineAttributes(): void
    {
        self::assertStringContainsString('data-source-line="1"', (new CarveRenderer(sourceLines: true))->render('# A'));
    }

    public function testSymbolsReplaceShortcodes(): void
    {
        $html = (new CarveRenderer(symbols: ['star' => '<b>*</b>']))->render('a :star: b');

        self::assertStringContainsString('<b>*</b>', $html);
    }

    public function testStaticModeIsAcceptedAndUnknownModeRefused(): void
    {
        self::assertStringContainsString('<h1', (new CarveRenderer(mode: 'static'))->render('# A'));

        $this->expectException(InvalidArgumentException::class);
        new CarveRenderer(mode: 'print');
    }

    public function testOnDisallowedToTextKeepsTheText(): void
    {
        $html = (new CarveRenderer(profile: 'comment', onDisallowed: 'to_text'))->render('# Title');

        self::assertStringNotContainsString('<h1', $html);
        self::assertStringContainsString('Title', $html);
    }

    public function testAnUnknownOnDisallowedActionIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new CarveRenderer(profile: 'comment', onDisallowed: 'shout');
    }

    public function testExtensionsAreBuiltFromConfigEntries(): void
    {
        $renderer = new CarveRenderer(extensions: [
            'autolink',
            ['type' => 'heading_permalinks', 'symbol' => '#'],
        ]);
        $html = $renderer->render("# Head\n\nSee https://example.com");

        self::assertStringContainsString('href="https://example.com"', $html);
        self::assertStringContainsString('#</a>', $html);
    }

    public function testExtensionStateDoesNotLeakBetweenRenders(): void
    {
        $renderer = new CarveRenderer(extensions: ['table_of_contents']);

        self::assertCount(1, $renderer->renderResult('# One')->toc());
        self::assertCount(1, $renderer->renderResult('# Two')->toc());
    }

    public function testAnExtensionInstanceIsClonedPerRender(): void
    {
        $toc = new TableOfContentsExtension();
        $renderer = new CarveRenderer(extensions: [$toc]);

        self::assertCount(1, $renderer->renderResult('# One')->toc());
        self::assertCount(1, $renderer->renderResult('# Two')->toc());
        self::assertSame([], $toc->getToc());
    }

    public function testAnUnknownExtensionIsRefusedAtConstruction(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new CarveRenderer(extensions: ['no_such_extension']);
    }

    public function testWithOptionsOverridesOneContext(): void
    {
        $renderer = new CarveRenderer();
        $comment = $renderer->withOptions(['preset' => 'comment']);

        self::assertStringContainsString('<h1', $renderer->render('# Title'));
        self::assertStringNotContainsString('<h1', $comment->render('# Title'));
        self::assertSame($renderer, $renderer->withOptions([]));
    }

    public function testWithOptionsRefusesAnUnknownKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown Carve render option "include_root"');

        (new CarveRenderer())->withOptions(['include_root' => '/']);
    }

    public function testRenderResultCarriesTocAndFrontmatter(): void
    {
        $renderer = new CarveRenderer(extensions: ['frontmatter']);
        $result = $renderer->renderResult("---json\n{\"title\": \"Hi\"}\n---\n\n# One\n\n## Two\n");

        self::assertSame(['One', 'Two'], array_column($result->toc(), 'text'));
        self::assertTrue($result->hasFrontmatter());
        self::assertSame('Hi', $result->meta('title'));
        self::assertSame('none', $result->meta('missing', 'none'));
        self::assertSame($result->html, (string)$result);
        self::assertStringNotContainsString('title', $result->html);
    }

    public function testRenderAnsi(): void
    {
        self::assertStringContainsString("\e[", (new CarveRenderer())->renderAnsi('*bold*'));
    }
}
