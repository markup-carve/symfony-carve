<?php

declare(strict_types=1);

namespace MarkupCarve\SymfonyCarve\Tests\Extension;

use InvalidArgumentException;
use MarkupCarve\SymfonyCarve\CarveBundle;
use MarkupCarve\SymfonyCarve\CarveRenderer;
use MarkupCarve\SymfonyCarve\Extension\EngineExtensionFactory;
use MarkupCarve\SymfonyCarve\Extension\ExtensionFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Configuration;
use Symfony\Component\Config\Definition\Processor;

final class EngineConfigTest extends TestCase
{
    /**
     * @return array<string, array{array<string, mixed>|string}>
     */
    public static function extensionNames(): array
    {
        $special = ['default_attributes' => ['type' => 'default_attributes', 'defaults' => ['paragraph' => ['class' => 'note']]], 'fenced_render' => ['type' => 'fenced_render', 'language' => 'custom']];
        $cases = [];
        foreach (EngineExtensionFactory::types() as $name) {
            $cases[$name] = [$special[$name] ?? $name];
        }

        return $cases;
    }

    /**
     * @param array<string, mixed>|string $entry
     */
    #[DataProvider('extensionNames')]
    public function testEveryRegisteredNameCanRender(array|string $entry): void
    {
        $factory = new EngineExtensionFactory();
        self::assertNotSame([], $factory->create($entry));
        self::assertNotSame('', (new CarveRenderer(extensions: [$entry]))->render('hello'));
    }

    public function testHeadingLevelsDefaultsToOneLevel(): void
    {
        self::assertStringContainsString('<h2>Title</h2>', (new CarveRenderer(extensions: ['heading_levels']))->render("# Title\n"));
    }

    public function testExternalLinksNofollowOption(): void
    {
        $entry = ['type' => 'external_links', 'nofollow' => true];
        self::assertStringContainsString('nofollow', (new CarveRenderer(extensions: [$entry]))->render('[x](https://example.com)'));
    }

    public function testUnknownOptionIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new EngineExtensionFactory())->create(['type' => 'heading_permalinks', 'misspelled' => true]);
    }

    public function testBundleDocumentTransformsRun(): void
    {
        $entry = ['type' => 'default_attributes', 'defaults' => ['paragraph' => ['class' => 'note']]];
        $factory = new EngineExtensionFactory();
        self::assertStringContainsString('class="note"', (new CarveRenderer(extensions: [$entry]))->render('hello'));
    }

    public function testDocumentTransformsFollowConfigurationOrder(): void
    {
        $defaults = ['type' => 'default_attributes', 'defaults' => ['link' => ['class' => 'marked']]];
        $before = (new CarveRenderer(extensions: [$defaults, 'heading_permalinks']))->render("# Hello\n");
        $after = (new CarveRenderer(extensions: ['heading_permalinks', $defaults]))->render("# Hello\n");
        self::assertStringNotContainsString('permalink marked', $before);
        self::assertStringContainsString('permalink marked', $after);
    }

    public function testSoftBreakAndNullableProfile(): void
    {
        $renderer = new CarveRenderer(profile: null, softBreak: 'space');
        self::assertStringContainsString('a b', $renderer->render("a\nb"));
        $configuration = new Configuration(new CarveBundle(), null, 'carve');
        $resolved = (new Processor())->processConfiguration($configuration, [['profile' => null, 'soft_break_mode' => 'space']]);
        self::assertNull($resolved['profile']);
        self::assertSame('space', $resolved['soft_break_mode']);
    }

    public function testCoreFrontmatterAliasRemainsAccepted(): void
    {
        self::assertSame([], (new ExtensionFactory())->create('frontmatter'));
    }

    public function testPositionalLabelsAndRenderedTocRemainConsistent(): void
    {
        $positional = new CarveRenderer(false, [], 'full', null, null, 'interactive', true, 'keep-case', [], false, true, ['example' => 'Example']);
        self::assertStringContainsString('<p>hello</p>', $positional->render('hello'));
        $result = (new CarveRenderer(headingIds: 'lower', extensions: ['heading_level_shift', 'heading_permalinks']))->renderResult("# My Title\n");
        self::assertSame([['level' => 2, 'text' => 'My Title', 'html' => 'My Title', 'id' => 'my-title']], $result->toc());
        $custom = (new CarveRenderer(extensions: [['type' => 'heading_permalinks', 'css_class' => 'anchor-link']]))->renderResult("# Hello\n");
        self::assertSame('Hello', $custom->toc()[0]['text']);
        $trusted = (new CarveRenderer(rawHtml: true, extensions: ['heading_level_shift']))->renderResult("`<h2 id=\"manual\">Manual</h2>`{=html}\n\n# Real\n");
        self::assertSame('Real', $trusted->toc()[0]['id']);
        self::assertSame(2, $trusted->toc()[0]['level']);
        $duplicate = (new CarveRenderer(rawHtml: true, headingIds: 'lower', extensions: ['heading_level_shift']))->renderResult("`<h2 id=\"manual\">My Title</h2>`{=html}\n\n# My Title\n");
        self::assertSame('my-title', $duplicate->toc()[0]['id']);
        $noIds = (new CarveRenderer(rawHtml: true, headingIds: 'off', extensions: ['heading_level_shift']))->renderResult("`<h3 id=\"raw\">Real preview</h3>`{=html}\n\n# Real\n");
        self::assertSame(2, $noIds->toc()[0]['level']);
        self::assertSame('', $noIds->toc()[0]['id']);
        $dataId = new CarveRenderer(extensions: [['type' => 'default_attributes', 'defaults' => ['heading' => ['data-id' => 'wrong']]]]);
        self::assertSame('Real', $dataId->renderResult("# Real\n")->toc()[0]['id']);
    }

    public function testPlantumlAlias(): void
    {
        $renderer = new CarveRenderer(diagrams: ['plantuml']);
        self::assertStringContainsString('class="puml"', $renderer->render("```puml\nA -> B\n```"));
        $graphviz = new CarveRenderer(diagrams: ['graphviz']);
        self::assertStringContainsString('class="dot"', $graphviz->render("```dot\nA -> B\n```"));
    }
}
