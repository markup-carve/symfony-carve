<?php

declare(strict_types=1);

namespace MarkupCarve\SymfonyCarve\Tests;

use MarkupCarve\Carve\SafeMode;
use MarkupCarve\SymfonyCarve\CarveRenderer;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Stringable;

final class CarveRendererTest extends TestCase
{
    public function testFileRenderExpandsContainedIncludesAndReportsDependencies(): void
    {
        $root = sys_get_temp_dir() . '/symfony-carve-' . bin2hex(random_bytes(6));
        mkdir($root . '/chapters', 0777, true);
        file_put_contents($root . '/main.crv', "# Main\n\n{{ chapters/one.crv }}\n");
        file_put_contents($root . '/chapters/one.crv', "Included.\n");

        try {
            $result = (new CarveRenderer(includeRoot: $root))->renderFileWithReport($root . '/main.crv');
            self::assertStringContainsString('Included.', $result['value']);
            self::assertSame([['path' => 'chapters/one.crv', 'resolved' => true]], $result['dependencies']);
        } finally {
            unlink($root . '/chapters/one.crv');
            unlink($root . '/main.crv');
            rmdir($root . '/chapters');
            rmdir($root);
        }
    }

    public function testFileRenderLogsSanitizedWarningWithoutResolverDetail(): void
    {
        $root = sys_get_temp_dir() . '/symfony-carve-' . bin2hex(random_bytes(6));
        mkdir($root);
        file_put_contents($root . '/main.crv', "{{ missing.crv }}\n");
        $logger = new class extends AbstractLogger {
            /**
             * @var list<array{level: mixed, message: string}>
             */
            public array $records = [];

            /**
             * @param mixed $level
             * @param \Stringable|string $message
             * @param array<mixed> $context
             *
             * @return void
             */
            public function log($level, Stringable|string $message, array $context = []): void
            {
                $this->records[] = ['level' => $level, 'message' => (string)$message];
            }
        };

        try {
            $result = (new CarveRenderer(includeRoot: $root, logger: $logger))->renderFileWithReport($root . '/main.crv');
            self::assertSame('include-unresolved', $result['warnings'][0]['rule']);
            self::assertStringNotContainsString($root, json_encode($result['warnings'], JSON_THROW_ON_ERROR));
            self::assertNotEmpty($logger->records);
        } finally {
            unlink($root . '/main.crv');
            rmdir($root);
        }
    }

    public function testFileRenderLeavesIncludesLiteralWithoutConfiguredRoot(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'symfony-carve-');
        file_put_contents($path, "{{ missing.crv }}\n");
        try {
            self::assertStringContainsString('{{ missing.crv }}', (new CarveRenderer())->renderFile($path));
        } finally {
            unlink($path);
        }
    }

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

    /**
     * The minimal profile denies images, so both non-HTML targets must degrade
     * the image to its alt text instead of emitting a reference to the file.
     * The assertions deliberately avoid pinning the Markdown renderer's
     * bracket-escaping, which is a detail of that renderer rather than of the
     * profile being applied.
     */
    public function testProfileAppliesToTextAndMarkdownRenderers(): void
    {
        $renderer = new CarveRenderer(profile: 'minimal');
        $source = '![alt](https://example.com/image.png)';

        $text = $renderer->renderText($source);
        $markdown = $renderer->renderMarkdown($source);

        $this->assertStringContainsString('img: alt', $text);
        $this->assertStringNotContainsString('example.com', $text);

        $this->assertStringContainsString('img: alt', $markdown);
        $this->assertStringNotContainsString('example.com', $markdown);
        $this->assertStringNotContainsString('!', $markdown);
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

        $this->assertStringContainsString('<pre class="plantuml"', $html);
        $this->assertStringContainsString('A -> B', $html);
    }

    public function testMermaidPresetRendersHydrationElement(): void
    {
        $carve = "``` mermaid\ngraph TD; A-->B\n```";

        $html = (new CarveRenderer(true, SafeMode::RAW_HTML_STRIP, ['mermaid']))->render($carve);

        $this->assertStringContainsString('<pre class="mermaid"', $html);
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
