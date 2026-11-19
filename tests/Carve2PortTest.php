<?php

declare(strict_types=1);

namespace MarkupCarve\SymfonyCarve\Tests;

use MarkupCarve\Carve2\Carve2;
use MarkupCarve\Carve2\Extension\RenderHost;
use MarkupCarve\Carve2\Node;
use MarkupCarve\Carve2\Output\OutputWriter;
use MarkupCarve\SymfonyCarve\CarveRenderer;
use MarkupCarve\SymfonyCarve\CarveTools;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;

final class Carve2PortTest extends TestCase
{
    public function testParseReturnsTheCarve2TreeAndRenderUsesItsHtmlPolicy(): void
    {
        // C2-T-005, C2-H-127, and C2-H-128.
        $renderer = new CarveRenderer();
        $tree = $renderer->parse("= Hello\n");

        self::assertInstanceOf(Node::class, $tree);
        self::assertSame('document', $tree->type);
        self::assertSame('2.0', $tree->f['astVersion']);
        self::assertStringContainsString('<h1>Hello</h1>', $renderer->render("= Hello\n"));
        self::assertStringContainsString('&lt;b&gt;', $renderer->render('`<b>x</b>`{=html}'));
        self::assertStringContainsString('<b>x</b>', (new CarveRenderer(rawHtml: true))->render('`<b>x</b>`{=html}'));
        self::assertStringNotContainsString('javascript:', $renderer->render('[bad](javascript:alert(1))'));
    }

    public function testRegisteredWriterServesTheExistingTextEntryPoint(): void
    {
        // C2-T-005: target writers are supplied through the Carve2 registry.
        $writer = new class implements OutputWriter {
            public function target(): string
            {
                return 'text';
            }

            public function write(Node $tree, array $options = []): string
            {
                return (string)count($tree->children ?? []);
            }
        };
        $registry = Carve2::writers()->register($writer);

        self::assertSame('2', (new CarveRenderer(writers: $registry))->renderText("one\n\ntwo\n"));
    }

    public function testMissingWriterReportsItsTarget(): void
    {
        // C2-T-005: the entry point remains until carve-php issue 5235 lands.
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Carve text writer not available yet.');
        (new CarveRenderer())->renderText('one');
    }

    public function testFormatLintAndHtmlImportUseCarve2(): void
    {
        // C2-W-001, C2-W-103, and C2-M-001.
        $tools = new CarveTools();
        self::assertSame("= Title\n", $tools->format("# Title\n"));
        self::assertNotSame([], $tools->lint("**bold**\n"));
        self::assertSame("Hello /there/\n", $tools->fromHtml('<p>Hello <em>there</em></p>'));
    }

    public function testStaticExtensionRenderingWaitsForTheD3Hook(): void
    {
        // C2-X-033 and D3: the static renderer channel is pending, see carve-php#5245.
        if (!(new ReflectionClass(RenderHost::class))->hasProperty('mode')) {
            $this->markTestSkipped('Pending carve-php#5245.');
        }
        $html = (new CarveRenderer(mode: 'static', diagrams: ['mermaid']))->render("```mermaid\ngraph TD; A-->B\n```\n");
        self::assertStringContainsString('<code class="language-mermaid">', $html);
    }
}
