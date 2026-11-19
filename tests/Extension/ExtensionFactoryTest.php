<?php

declare(strict_types=1);

namespace MarkupCarve\SymfonyCarve\Tests\Extension;

use InvalidArgumentException;
use MarkupCarve\Carve2\Extension\ExtensionDefinition;
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
        // C2-X-001: the bundle now builds Carve2 extension definitions.
        $definitions = (new ExtensionFactory())->create($type);
        self::assertNotSame([], $definitions);
        self::assertInstanceOf(ExtensionDefinition::class, $definitions[0]);
    }

    public function testAnInstanceAndAClassNamePassThrough(): void
    {
        // C2-X-002: only definitions and providers are accepted by the 0.2 host.
        $definition = new ExtensionDefinition('test.echo', '1');
        self::assertSame([$definition], (new ExtensionFactory())->create($definition));
    }

    public function testMermaidIsAFencedRenderShortcut(): void
    {
        // C2-X-005: fenced diagram presets use an extension matcher.
        self::assertInstanceOf(ExtensionDefinition::class, (new ExtensionFactory())->create('mermaid')[0]);
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
        // U-408 and C2-X-057: 0.2 wiki links use explicit inline extension syntax.
        $html = (new CarveRenderer(extensions: [['type' => 'wikilinks', 'url_template' => '/wiki/{page}']]))->render(':wiki[Main Page]');
        self::assertStringContainsString('href="/wiki/main-page"', $html);
    }
}
