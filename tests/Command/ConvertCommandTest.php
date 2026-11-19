<?php

declare(strict_types=1);

namespace MarkupCarve\SymfonyCarve\Tests\Command;

use MarkupCarve\Carve2\Carve2;
use MarkupCarve\SymfonyCarve\CarveTools;
use MarkupCarve\SymfonyCarve\Command\ConvertCommand;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class ConvertCommandTest extends CommandTestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function inputs(): array
    {
        return [
            'markdown' => ['a.md', "**bold**\n"],
            'html' => ['a.html', '<p><strong>bold</strong></p>'],
            'djot' => ['a.djot', "*bold*\n"],
        ];
    }

    #[DataProvider('inputs')]
    public function testGuessesTheFormatFromTheExtension(string $name, string $content): void
    {
        $tester = new CommandTester(new ConvertCommand(new CarveTools()));

        // C2-T-003: keep the conversion assertions until both 0.2 importers arrive.
        $api = new ReflectionClass(Carve2::class);
        if ($name === 'a.md' && !$api->hasMethod('importMarkdown')) {
            $this->markTestSkipped('Pending carve-php#5237.');
        }
        if ($name === 'a.djot' && !$api->hasMethod('importDjot')) {
            $this->markTestSkipped('Pending carve-php#5242.');
        }
        self::assertSame(Command::SUCCESS, $tester->execute(['path' => $this->file($name, $content)]));
        self::assertSame('*bold*', trim($tester->getDisplay()));
    }

    public function testFromOverridesAndOutputWritesAFile(): void
    {
        // C2-T-003: Markdown import is pending, see carve-php#5237.
        if (!(new ReflectionClass(Carve2::class))->hasMethod('importMarkdown')) {
            $this->markTestSkipped('Pending carve-php#5237.');
        }
        $tester = new CommandTester(new ConvertCommand(new CarveTools()));
        $target = $this->dir . '/out.crv';

        self::assertSame(Command::SUCCESS, $tester->execute([
            'path' => $this->file('a.txt', "**bold**\n"),
            '--from' => 'markdown',
            '--output' => $target,
        ]));
        self::assertSame('*bold*', trim((string)file_get_contents($target)));
    }

    public function testAnUnknownFormatOrMissingFileFails(): void
    {
        $tester = new CommandTester(new ConvertCommand(new CarveTools()));

        self::assertSame(Command::FAILURE, $tester->execute(['path' => $this->file('a.txt', 'x')]));
        self::assertSame(Command::FAILURE, $tester->execute(['path' => $this->file('b.md', 'x'), '--from' => 'rst']));
        self::assertSame(Command::FAILURE, $tester->execute(['path' => $this->dir . '/missing.md']));
    }
}
