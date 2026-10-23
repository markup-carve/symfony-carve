<?php

declare(strict_types=1);

namespace MarkupCarve\SymfonyCarve\Tests\Extension;

use InvalidArgumentException;
use MarkupCarve\Carve\Extension\AutolinkExtension;
use MarkupCarve\Carve\Extension\FencedRenderExtension;
use MarkupCarve\SymfonyCarve\CarveRenderer;
use MarkupCarve\SymfonyCarve\Extension\ExtensionFactory;
use MarkupCarve\SymfonyCarve\Extension\WikiLinkUrlTemplate;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ExtensionFactoryTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function types(): array
    {
        return array_combine(ExtensionFactory::TYPES, array_map(static fn (string $type): array => [$type], ExtensionFactory::TYPES));
    }

    #[DataProvider('types')]
    public function testEveryListedTypeBuilds(string $type): void
    {
        $extension = (new ExtensionFactory())->create($type);

        self::assertStringEndsWith('Extension', $extension::class);
        self::assertMatchesRegularExpression('/<h[12][ >]/', (new CarveRenderer(extensions: [$type]))->render("# A\n"));
    }

    public function testAnInstanceAndAClassNamePassThrough(): void
    {
        $factory = new ExtensionFactory();
        $instance = new AutolinkExtension();

        self::assertSame($instance, $factory->create($instance));
        self::assertInstanceOf(AutolinkExtension::class, $factory->create(AutolinkExtension::class));
    }

    public function testMermaidIsAFencedRenderShortcut(): void
    {
        self::assertInstanceOf(FencedRenderExtension::class, (new ExtensionFactory())->create('mermaid'));
    }

    public function testAnUnknownOptionIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown option "colour"');

        (new ExtensionFactory())->create(['type' => 'autolink', 'colour' => 'red']);
    }

    public function testAnEntryWithoutTypeIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new ExtensionFactory())->create(['symbol' => '#']);
    }

    public function testWikiLinkUrlTemplateSlugsThePageName(): void
    {
        self::assertSame('/wiki/hello-world', (new WikiLinkUrlTemplate('/wiki/{page}'))('  Hello   World! '));

        $html = (new CarveRenderer(extensions: [['type' => 'wikilinks', 'url_template' => '/wiki/{page}']]))->render('[[Main Page]]');

        self::assertStringContainsString('href="/wiki/main-page"', $html);
    }
}
