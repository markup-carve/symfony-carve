<?php

declare(strict_types=1);

namespace MarkupCarve\SymfonyCarve\Tests\Twig;

use MarkupCarve\SymfonyCarve\CarveRenderer;
use MarkupCarve\SymfonyCarve\Twig\CarveExtension;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

final class CarveExtensionTest extends TestCase
{
    public function testFilterRendersCarve(): void
    {
        $twig = new Environment(new ArrayLoader([
            'tpl' => '{{ source|carve }}',
        ]));
        $twig->addExtension(new CarveExtension(new CarveRenderer()));

        $out = $twig->render('tpl', ['source' => '# Hi']);

        $this->assertStringContainsString('<h1', $out);
    }

    public function testFunctionRendersCarve(): void
    {
        $twig = new Environment(new ArrayLoader([
            'tpl' => '{{ carve("# Hi") }}',
        ]));
        $twig->addExtension(new CarveExtension(new CarveRenderer()));

        $out = $twig->render('tpl', []);

        $this->assertStringContainsString('<h1', $out);
    }

    public function testOutputIsNotDoubleEscaped(): void
    {
        $twig = new Environment(new ArrayLoader([
            'tpl' => '{{ "*bold*"|carve }}',
        ]));
        $twig->addExtension(new CarveExtension(new CarveRenderer()));

        $out = $twig->render('tpl', []);

        $this->assertStringNotContainsString('&lt;', $out);
    }

    public function testTextFilterRendersAndIsEscaped(): void
    {
        $twig = new Environment(new ArrayLoader([
            'tpl' => '{{ source|carve_text }}',
        ]));
        $twig->addExtension(new CarveExtension(new CarveRenderer()));

        $out = $twig->render('tpl', ['source' => '*bold* <tag>']);

        $this->assertSame("bold &lt;tag&gt;\n", $out);
    }

    public function testMarkdownFilterRendersAndIsEscaped(): void
    {
        $twig = new Environment(new ArrayLoader([
            'tpl' => '{{ source|carve_markdown }}',
        ]));
        $twig->addExtension(new CarveExtension(new CarveRenderer()));

        $out = $twig->render('tpl', ['source' => '*bold* <tag>']);

        $this->assertSame("**bold** &amp;lt;tag&amp;gt;\n", $out);
    }
}
