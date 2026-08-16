<?php

declare(strict_types=1);

namespace MarkupCarve\SymfonyCarve\Tests;

use MarkupCarve\Carve\SafeMode;
use MarkupCarve\SymfonyCarve\CarveRenderer;
use PHPUnit\Framework\TestCase;

final class CarveRendererTest extends TestCase
{
    public function testRendersHeading(): void
    {
        $html = (new CarveRenderer())->render('# Hello');

        $this->assertStringContainsString('<h1', $html);
        $this->assertStringContainsString('Hello', $html);
    }

    public function testSafeModeStripsRawHtml(): void
    {
        $html = (new CarveRenderer(true, SafeMode::RAW_HTML_STRIP))->render('<script>alert(1)</script>');

        $this->assertStringNotContainsString('<script>', $html);
    }

    public function testSafeModeEscapesRawHtml(): void
    {
        $html = (new CarveRenderer(true, SafeMode::RAW_HTML_ESCAPE))->render('<b>x</b>');

        $this->assertStringContainsString('&lt;b&gt;', $html);
    }

    public function testFreshConverterPerRenderAvoidsHeadingIdCollision(): void
    {
        $renderer = new CarveRenderer();
        $first = $renderer->render('# Title');
        $second = $renderer->render('# Title');

        $this->assertSame($first, $second);
    }

    public function testRendersPlainText(): void
    {
        $text = (new CarveRenderer())->renderText('# Hello *world*');

        $this->assertSame("Hello world\n", $text);
    }

    public function testRendersMarkdown(): void
    {
        $markdown = (new CarveRenderer())->renderMarkdown('# Hello *world*');

        $this->assertSame("# Hello **world**\n", $markdown);
    }

    public function testCommentProfileRestrictsHeadings(): void
    {
        $html = (new CarveRenderer(profile: 'comment'))->render('# Heading');

        $this->assertStringNotContainsString('<h1', $html);
        $this->assertStringContainsString('<p># Heading</p>', $html);
    }

    public function testProfileAppliesToTextAndMarkdownRenderers(): void
    {
        $renderer = new CarveRenderer(profile: 'minimal');

        $this->assertSame("[img: alt]\n", $renderer->renderText('![alt](https://example.com/image.png)'));
        $this->assertSame("\[img: alt\]\n", $renderer->renderMarkdown('![alt](https://example.com/image.png)'));
    }

    public function testDiagramsDefaultLeavesFencedBlockUnchanged(): void
    {
        $carve = "``` plantuml\nA -> B\n```";

        $default = (new CarveRenderer())->render($carve);
        $explicitEmpty = (new CarveRenderer(true, SafeMode::RAW_HTML_STRIP, []))->render($carve);

        // No FencedRenderExtension applied: rendered as a plain code block, and
        // the default and the explicit-empty config produce identical output.
        $this->assertStringNotContainsString('<pre class="plantuml"', $default);
        $this->assertStringContainsString('<code', $default);
        $this->assertSame($default, $explicitEmpty);
    }

    public function testPlantumlPresetRendersHydrationElement(): void
    {
        $carve = "``` plantuml\nA -> B\n```";

        $html = (new CarveRenderer(true, SafeMode::RAW_HTML_STRIP, ['plantuml']))->render($carve);

        $this->assertStringContainsString('<pre class="plantuml">', $html);
        $this->assertStringContainsString('A -> B', $html);
    }

    public function testMermaidPresetRendersHydrationElement(): void
    {
        $carve = "``` mermaid\ngraph TD; A-->B\n```";

        $html = (new CarveRenderer(true, SafeMode::RAW_HTML_STRIP, ['mermaid']))->render($carve);

        $this->assertStringContainsString('<pre class="mermaid">', $html);
    }

    public function testUnknownDiagramNameIsIgnored(): void
    {
        $carve = "``` plantuml\nA -> B\n```";

        $html = (new CarveRenderer(true, SafeMode::RAW_HTML_STRIP, ['nope']))->render($carve);

        // Unknown preset skipped, so the plantuml fence stays a plain code block.
        $this->assertStringNotContainsString('<pre class="plantuml"', $html);
        $this->assertStringContainsString('<code', $html);
    }
}
