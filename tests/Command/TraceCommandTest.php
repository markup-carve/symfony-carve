<?php

declare(strict_types=1);

namespace MarkupCarve\SymfonyCarve\Tests\Command;

use MarkupCarve\SymfonyCarve\CarveTools;
use MarkupCarve\SymfonyCarve\Command\TraceCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class TraceCommandTest extends CommandTestCase
{
    public function testPrintsOneJsonRecordPerLineAfterTheHeader(): void
    {
        $tester = new CommandTester(new TraceCommand(new CarveTools()));

        self::assertSame(Command::SUCCESS, $tester->execute(['path' => $this->file('a.crv', "- one\n- two\n")]));
        $lines = explode("\n", rtrim($tester->getDisplay(true), "\n"));
        self::assertSame(['trace' => 'carve-decision-trace', 'version' => 1, 'engine' => 'carve-php'], json_decode($lines[0], true));
        self::assertCount(3, $lines);
        foreach (array_slice($lines, 1) as $line) {
            self::assertIsArray(json_decode($line, true, flags: JSON_THROW_ON_ERROR));
        }
    }

    public function testWritesToAFile(): void
    {
        $target = $this->dir . '/trace.jsonl';
        $tester = new CommandTester(new TraceCommand(new CarveTools()));

        self::assertSame(Command::SUCCESS, $tester->execute(['path' => $this->file('a.crv', "Text.\n"), '--output' => $target]));
        self::assertStringStartsWith('{"trace":"carve-decision-trace"', (string)file_get_contents($target));
    }

    public function testAMissingFileFails(): void
    {
        $tester = new CommandTester(new TraceCommand(new CarveTools()));

        self::assertSame(Command::FAILURE, $tester->execute(['path' => $this->dir . '/missing.crv']));
    }
}
