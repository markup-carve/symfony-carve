<?php

declare(strict_types=1);

namespace MarkupCarve\SymfonyCarve\Tests\Command;

use MarkupCarve\SymfonyCarve\CarveTools;
use MarkupCarve\SymfonyCarve\Command\FormatCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class FormatCommandTest extends CommandTestCase
{
    /**
     * @var string
     */
    private const TABLE = "| a | bbb |\n| --- | --- |\n| cccc | d |\n";

    public function testPrintsTheCanonicalForm(): void
    {
        $tester = new CommandTester(new FormatCommand(new CarveTools()));

        self::assertSame(Command::SUCCESS, $tester->execute(['paths' => [$this->file('a.crv', "#   Title\n")]]));
        self::assertSame("# Title\n", $tester->getDisplay());
    }

    public function testCheckFailsOnUnformattedFilesWithoutChangingThem(): void
    {
        $path = $this->file('a.crv', "#   Title\n");
        $this->file('nested/ok.crv', "# Fine\n");
        $tester = new CommandTester(new FormatCommand(new CarveTools()));

        self::assertSame(Command::FAILURE, $tester->execute(['paths' => [$this->dir], '--check' => true]));
        self::assertStringContainsString('a.crv is not formatted', $tester->getDisplay());
        self::assertStringNotContainsString('ok.crv is not formatted', $tester->getDisplay());
        self::assertSame("#   Title\n", file_get_contents($path));
    }

    public function testWriteRewritesInPlace(): void
    {
        $path = $this->file('a.crv', "#   Title\n");
        $tester = new CommandTester(new FormatCommand(new CarveTools()));

        self::assertSame(Command::SUCCESS, $tester->execute(['paths' => [$path], '--write' => true]));
        self::assertSame("# Title\n", file_get_contents($path));
        self::assertSame(Command::SUCCESS, $tester->execute(['paths' => [$path], '--check' => true]));
    }

    public function testAlignTablesPadsColumns(): void
    {
        $path = $this->file('t.crv', self::TABLE);
        $tester = new CommandTester(new FormatCommand(new CarveTools()));

        $tester->execute(['paths' => [$path]]);
        self::assertStringNotContainsString('| a    | bbb |', $tester->getDisplay());
        $tester->execute(['paths' => [$path], '--align-tables' => true]);
        self::assertStringContainsString('| a    | bbb |', $tester->getDisplay());
    }

    public function testWriteAndCheckAreExclusive(): void
    {
        $tester = new CommandTester(new FormatCommand(new CarveTools()));

        self::assertSame(Command::FAILURE, $tester->execute(['paths' => [$this->dir], '--write' => true, '--check' => true]));
    }
}
