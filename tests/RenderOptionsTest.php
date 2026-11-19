<?php

declare(strict_types=1);

namespace MarkupCarve\SymfonyCarve\Tests;

use InvalidArgumentException;
use MarkupCarve\Carve\Carve;
use MarkupCarve\Carve\Extension\ExtensionDefinition;
use MarkupCarve\SymfonyCarve\CarveRenderer;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class RenderOptionsTest extends TestCase
{
    public function testRawHtmlDefaultsOffAndUnsafeUrlsAreDenied(): void
    {
        $html = (new CarveRenderer(rawHtml: false))->render('[x](javascript:alert(1))');

        self::assertStringNotContainsString('javascript:', $html);
    }

    public function testAnUnknownUrlPolicyFieldIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        // C2-H-128: URL policy rejects unknown keys.
        new CarveRenderer(urlPolicy: ['wrong' => []]);
    }

    public function testSectionsOptionControlsHeadingWrapper(): void
    {
        // C2-H-137: sections replaces the removed soft break option.
        self::assertStringNotContainsString('<section', (new CarveRenderer(sections: false))->render('# A'));
        self::assertStringContainsString('<section', (new CarveRenderer())->render('# A'));
    }

    public function testAnUnknownHeadingIdModeIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        // C2-H-131: heading IDs use the 0.2 enumeration.
        new CarveRenderer(headingIds: 'unknown');
    }

    public function testHtmlWriterUsesHtmlVoidSpelling(): void
    {
        // C2-H-137: XHTML is no longer an option; the HTML writer uses HTML spelling.
        self::assertStringContainsString('<hr>', (new CarveRenderer())->render('***'));
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

    public function testCommentProfileKeepsDisallowedText(): void
    {
        // C2-H-129: the comment profile controls disallowed headings.
        $html = (new CarveRenderer(profile: 'comment'))->render('# Title');

        self::assertStringNotContainsString('<h1', $html);
        self::assertStringContainsString('Title', $html);
    }

    public function testAnUnknownProfileIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        // C2-H-129: profiles use the 0.2 enumeration.
        new CarveRenderer(profile: 'unknown');
    }

    public function testExtensionsAreBuiltFromConfigEntries(): void
    {
        // C2-H-137: pending Carve API, see carve-php#5236.
        if (!(new ReflectionClass(ExtensionDefinition::class))->hasMethod('withHeadingPermalink')) {
            $this->markTestSkipped('Pending carve-php#5236.');
        }
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
        // C2-X-001: immutable extension definitions can be reused without state leaks.
        $definition = new ExtensionDefinition('test.metadata', '1');
        $renderer = new CarveRenderer(extensions: [$definition]);

        self::assertCount(1, $renderer->renderResult('# One')->toc());
        self::assertCount(1, $renderer->renderResult('# Two')->toc());
        self::assertSame('test.metadata', $definition->name);
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
        // C2-A-021: frontmatter is a core tree node in 0.2.
        $renderer = new CarveRenderer();
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
        // C2-H-137: pending Carve API, see carve-php#5235.
        if (!Carve::writers()->has('ansi')) {
            $this->markTestSkipped('Pending carve-php#5235.');
        }
        self::assertStringContainsString("\e[", (new CarveRenderer())->renderAnsi('*bold*'));
    }
}
