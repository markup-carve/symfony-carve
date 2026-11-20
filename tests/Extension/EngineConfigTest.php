<?php

declare(strict_types=1);

namespace MarkupCarve\SymfonyCarve\Tests\Extension;

use InvalidArgumentException;
use MarkupCarve\SymfonyCarve\CarveBundle;
use MarkupCarve\SymfonyCarve\CarveRenderer;
use MarkupCarve\SymfonyCarve\Extension\EngineExtensionFactory;
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

    public function testSoftBreakAndNullableProfile(): void
    {
        $renderer = new CarveRenderer(profile: null, softBreak: 'space');
        self::assertStringContainsString('a b', $renderer->render("a\nb"));
        $configuration = new Configuration(new CarveBundle(), null, 'carve');
        $resolved = (new Processor())->processConfiguration($configuration, [['profile' => null, 'soft_break_mode' => 'space']]);
        self::assertNull($resolved['profile']);
        self::assertSame('space', $resolved['soft_break_mode']);
    }

    public function testPlantumlAlias(): void
    {
        $renderer = new CarveRenderer(diagrams: ['plantuml']);
        self::assertStringContainsString('class="puml"', $renderer->render("```puml\nA -> B\n```"));
        $graphviz = new CarveRenderer(diagrams: ['graphviz']);
        self::assertStringContainsString('class="dot"', $graphviz->render("```dot\nA -> B\n```"));
    }
}
