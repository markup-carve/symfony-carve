<?php

declare(strict_types=1);

namespace MarkupCarve\Symfony\Tests;

use MarkupCarve\Carve\SafeMode;
use MarkupCarve\Symfony\CarveRenderer;
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
}
