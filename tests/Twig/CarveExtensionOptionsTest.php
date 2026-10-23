<?php

declare(strict_types=1);

namespace MarkupCarve\SymfonyCarve\Tests\Twig;

use InvalidArgumentException;
use MarkupCarve\SymfonyCarve\CarveRenderer;
use MarkupCarve\SymfonyCarve\Twig\CarveExtension;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Error\RuntimeError;
use Twig\Loader\ArrayLoader;

final class CarveExtensionOptionsTest extends TestCase
{
    public function testFilterAndFunctionTakeRenderOptions(): void
    {
        $out = $this->render(
            "{{ source|carve({preset: 'comment'}) }}|{{ carve(source, {soft_break_mode: 'br'}) }}",
            ['source' => "# Title\na\nb"],
        );
        [$filter, $function] = explode('|', $out);

        self::assertStringNotContainsString('<h1', $filter);
        self::assertStringContainsString('<h1', $function);
        self::assertStringContainsString('<br>', $function);
    }

    public function testAnUnknownOptionFailsTheTemplate(): void
    {
        $this->expectException(RuntimeError::class);

        $this->render("{{ 'x'|carve({include_root: '/'}) }}");
    }

    public function testFormatFilterAlignsTablesOnRequest(): void
    {
        $source = "| a | bbb |\n| --- | --- |\n| cccc | d |\n";

        self::assertStringContainsString('| a    | bbb |', $this->render('{{ source|carve_format(align_tables = true)|raw }}', ['source' => $source]));
        self::assertStringNotContainsString('| a    | bbb |', $this->render('{{ source|carve_format|raw }}', ['source' => $source]));
    }

    public function testFormatOutputIsEscaped(): void
    {
        self::assertStringContainsString('&lt;', $this->render('{{ "<b>"|carve_format }}'));
    }

    public function testCarveDocumentExposesTocAndFrontmatter(): void
    {
        $out = $this->render(
            "{% set doc = carve_document(source) %}{{ doc.meta('title') }}|{% for h in doc.toc %}{{ h.text }},{% endfor %}|{{ doc.html|raw }}",
            ['source' => "---json\n{\"title\": \"Hi\"}\n---\n\n# One\n"],
            new CarveRenderer(extensions: ['frontmatter']),
        );

        self::assertStringStartsWith('Hi|One,|', $out);
        self::assertStringContainsString('<h1>One</h1>', $out);
    }

    public function testCarveLintReturnsFindings(): void
    {
        $out = $this->render('{% for f in carve_lint(source) %}{{ f.rule }}@{{ f.line }}{% endfor %}', ['source' => "**bold**\n"]);

        self::assertMatchesRegularExpression('/^[a-z-]+@1/', $out);
    }

    public function testCarveFileRendersRelativeToTheIncludeRoot(): void
    {
        $root = sys_get_temp_dir() . '/symfony-carve-twig-' . bin2hex(random_bytes(4));
        mkdir($root);
        file_put_contents($root . '/main.crv', "# Main\n\n{{ part.crv }}\n");
        file_put_contents($root . '/part.crv', "Included.\n");

        try {
            $out = $this->render("{{ carve_file('main.crv') }}", [], new CarveRenderer(includeRoot: $root));

            self::assertStringContainsString('<h1', $out);
            self::assertStringContainsString('Included.', $out);
        } finally {
            unlink($root . '/part.crv');
            unlink($root . '/main.crv');
            rmdir($root);
        }
    }

    public function testCarveFileRefusesAPathOutsideTheRoot(): void
    {
        $root = sys_get_temp_dir() . '/symfony-carve-twig-' . bin2hex(random_bytes(4));
        mkdir($root);

        try {
            $this->render("{{ carve_file('" . __FILE__ . "') }}", [], new CarveRenderer(includeRoot: $root));
            self::fail('A file outside the root rendered.');
        } catch (RuntimeError $error) {
            self::assertInstanceOf(InvalidArgumentException::class, $error->getPrevious());
        } finally {
            rmdir($root);
        }
    }

    public function testCarveFileNeedsAnIncludeRoot(): void
    {
        $this->expectException(RuntimeError::class);
        $this->expectExceptionMessage('carve.include_root');

        $this->render("{{ carve_file('" . __FILE__ . "') }}");
    }

    /**
     * @param string $template
     * @param array<string, mixed> $context
     * @param \MarkupCarve\SymfonyCarve\CarveRenderer|null $renderer
     */
    private function render(string $template, array $context = [], ?CarveRenderer $renderer = null): string
    {
        $twig = new Environment(new ArrayLoader(['tpl' => $template]));
        $twig->addExtension(new CarveExtension($renderer ?? new CarveRenderer()));

        return $twig->render('tpl', $context);
    }
}
