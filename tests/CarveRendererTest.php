<?php

declare(strict_types=1);

namespace MarkupCarve\SymfonyCarve\Tests;

use InvalidArgumentException;
use MarkupCarve\Carve\SafeMode;
use MarkupCarve\SymfonyCarve\CarveRenderer;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
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
        $records = [];
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning')->willReturnCallback(
            function (string|Stringable $message, array $context = []) use (&$records): void {
                $records[] = $context;
            },
        );

        try {
            $result = (new CarveRenderer(includeRoot: $root, logger: $logger))->renderFileWithReport($root . '/main.crv');
            self::assertSame('include-unresolved', $result['warnings'][0]['rule']);
            self::assertStringNotContainsString($root, json_encode($result['warnings'], JSON_THROW_ON_ERROR));
            self::assertCount(1, $records);
            self::assertSame('include-unresolved', $records[0]['rule']);
            self::assertArrayNotHasKey('detail', $records[0]);
        } finally {
            unlink($root . '/main.crv');
            rmdir($root);
        }
    }

    public function testARelativeIncludeRootIsRefused(): void
    {
        // `src` exists relative to the working directory, so a root that gets
        // canonicalized first would be accepted here.
        self::assertDirectoryExists(getcwd() . '/src');

        $this->expectException(InvalidArgumentException::class);
        new CarveRenderer(includeRoot: 'src');
    }

    public function testTraversalAndSymlinkEscapeStayLiteralAndReportNoPath(): void
    {
        $root = sys_get_temp_dir() . '/symfony-carve-' . bin2hex(random_bytes(6));
        $outside = tempnam(sys_get_temp_dir(), 'symfony-carve-secret-');
        mkdir($root);
        file_put_contents($outside, "SECRET\n");
        symlink($outside, $root . '/linked.crv');
        file_put_contents($root . '/main.crv', '{{ ../' . basename($outside) . " }}\n\n{{ linked.crv }}\n\n{{ " . $outside . " }}\n");

        try {
            $result = (new CarveRenderer(includeRoot: $root))->renderFileWithReport($root . '/main.crv');
            self::assertStringNotContainsString('SECRET', $result['value']);
            // The traversal and the absolute spec name paths outside the root
            // and are masked; the symlink names one inside it, so its own
            // spelling says which directive was refused.
            self::assertSame(
                [
                    ['path' => CarveRenderer::OUTSIDE_ROOT, 'resolved' => false],
                    ['path' => 'linked.crv', 'resolved' => false],
                    ['path' => CarveRenderer::OUTSIDE_ROOT, 'resolved' => false],
                ],
                $result['dependencies'],
            );
            self::assertCount(3, $result['warnings']);
            foreach ($result['warnings'] as $warning) {
                self::assertSame('include-unresolved', $warning['rule']);
                self::assertSame(['rule', 'message', 'file', 'line', 'column'], array_keys($warning));
                self::assertSame('main.crv', $warning['file']);
            }
            // `message` quotes the directive the author typed, which is theirs
            // to know. The containment root is the host's, and the resolver's
            // own message embeds it on the engine's `detail` channel, which
            // this bundle never reports.
            self::assertStringNotContainsString($root, serialize($result));
        } finally {
            unlink($root . '/linked.crv');
            unlink($root . '/main.crv');
            unlink($outside);
            rmdir($root);
        }
    }

    public function testANestedRelativeIncludeResolvesAgainstItsOwnParent(): void
    {
        $root = sys_get_temp_dir() . '/symfony-carve-' . bin2hex(random_bytes(6));
        mkdir($root . '/chapters/parts', 0777, true);
        file_put_contents($root . '/chapters/main.crv', "{{ one.crv }}\n");
        file_put_contents($root . '/chapters/one.crv', "{{ parts/deep.crv }}\n");
        file_put_contents($root . '/chapters/parts/deep.crv', "DEEP\n");

        try {
            $result = (new CarveRenderer(includeRoot: $root))->renderFileWithReport($root . '/chapters/main.crv');
            self::assertStringContainsString('DEEP', $result['value']);
            self::assertSame(
                [
                    ['path' => 'chapters/one.crv', 'resolved' => true],
                    ['path' => 'chapters/parts/deep.crv', 'resolved' => true],
                ],
                $result['dependencies'],
            );
        } finally {
            unlink($root . '/chapters/parts/deep.crv');
            unlink($root . '/chapters/one.crv');
            unlink($root . '/chapters/main.crv');
            rmdir($root . '/chapters/parts');
            rmdir($root . '/chapters');
            rmdir($root);
        }
    }

    public function testAMissingTargetIsReportedWhereItWouldBe(): void
    {
        $root = sys_get_temp_dir() . '/symfony-carve-' . bin2hex(random_bytes(6));
        mkdir($root . '/chapters', 0777, true);
        file_put_contents($root . '/chapters/main.crv', "{{ later.crv }}\n");

        try {
            $result = (new CarveRenderer(includeRoot: $root))->renderFileWithReport($root . '/chapters/main.crv');
            self::assertStringContainsString('{{ later.crv }}', $result['value']);
            // Reported where the file WOULD be, so a cache watching the
            // dependency invalidates when somebody creates it.
            self::assertSame([['path' => 'chapters/later.crv', 'resolved' => false]], $result['dependencies']);
        } finally {
            unlink($root . '/chapters/main.crv');
            rmdir($root . '/chapters');
            rmdir($root);
        }
    }

    public function testFileRenderLeavesIncludesLiteralWithoutConfiguredRoot(): void
    {
        $root = sys_get_temp_dir() . '/symfony-carve-' . bin2hex(random_bytes(6));
        mkdir($root);
        file_put_contents($root . '/main.crv', "{{ child.crv }}\n");
        // The target is readable and sits beside the document, so a root that
        // fell back to the document's own directory would expand it.
        file_put_contents($root . '/child.crv', "SECRET\n");

        try {
            $result = (new CarveRenderer())->renderFileWithReport($root . '/main.crv');
            self::assertStringContainsString('{{ child.crv }}', $result['value']);
            self::assertStringNotContainsString('SECRET', $result['value']);
            self::assertSame([], $result['dependencies']);
        } finally {
            unlink($root . '/child.crv');
            unlink($root . '/main.crv');
            rmdir($root);
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

        $this->assertStringContainsString('<pre class="plantuml" role="img" aria-label="plantuml">', $html);
        $this->assertStringContainsString('A -> B', $html);
    }

    public function testMermaidPresetRendersHydrationElement(): void
    {
        $carve = "``` mermaid\ngraph TD; A-->B\n```";

        $html = (new CarveRenderer(true, SafeMode::RAW_HTML_STRIP, ['mermaid']))->render($carve);

        $this->assertStringContainsString('<pre class="mermaid" role="img" aria-label="mermaid">', $html);
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
