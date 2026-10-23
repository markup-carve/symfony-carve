<?php

declare(strict_types=1);

namespace MarkupCarve\SymfonyCarve\Tests\Command;

use MarkupCarve\SymfonyCarve\CarveTools;
use MarkupCarve\SymfonyCarve\Command\ConvertCommand;
use PHPUnit\Framework\Attributes\DataProvider;
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

        self::assertSame(Command::SUCCESS, $tester->execute(['path' => $this->file($name, $content)]));
        self::assertSame('*bold*', trim($tester->getDisplay()));
    }

    public function testFromOverridesAndOutputWritesAFile(): void
    {
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
