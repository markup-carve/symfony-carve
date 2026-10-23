<?php

declare(strict_types=1);

namespace MarkupCarve\SymfonyCarve\Tests\Command;

use MarkupCarve\SymfonyCarve\CarveTools;
use MarkupCarve\SymfonyCarve\Command\LintCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class LintCommandTest extends CommandTestCase
{
    public function testACleanTreePasses(): void
    {
        $this->file('nested/clean.crv', "# Clean\n");
        $tester = new CommandTester(new LintCommand(new CarveTools()));

        self::assertSame(Command::SUCCESS, $tester->execute(['paths' => [$this->dir]]));
        self::assertStringContainsString('No Carve problems found', $tester->getDisplay());
    }

    public function testFindingsInADirectoryFailWithLocationAndRule(): void
    {
        $this->file('nested/bad.crv', "**bold**\n");
        $this->file('ignored.md', "**bold**\n");
        $tester = new CommandTester(new LintCommand(new CarveTools()));

        self::assertSame(Command::FAILURE, $tester->execute(['paths' => [$this->dir]]));
        $display = $tester->getDisplay();
        self::assertStringContainsString('bad.crv:1:1', $display);
        self::assertStringNotContainsString('ignored.md', $display);
        self::assertStringContainsString('1 Carve problem(s) found', $display);
    }

    public function testIncludeRootLintsIncludedFiles(): void
    {
        $this->file('part.crv', "**bold**\n");
        $main = $this->file('main.crv', "# Main\n\n{{ part.crv }}\n");
        $tester = new CommandTester(new LintCommand(new CarveTools()));

        self::assertSame(Command::SUCCESS, $tester->execute(['paths' => [$main]]));
        self::assertSame(Command::FAILURE, $tester->execute(['paths' => [$main], '--include-root' => $this->dir]));
        self::assertStringContainsString('part.crv:1:1', $tester->getDisplay());
    }

    public function testAMissingPathOrBadRootFails(): void
    {
        $tester = new CommandTester(new LintCommand(new CarveTools()));

        self::assertSame(Command::FAILURE, $tester->execute(['paths' => [$this->dir . '/missing.crv']]));
        self::assertSame(Command::FAILURE, $tester->execute(['paths' => [$this->dir], '--include-root' => $this->dir . '/nope']));
    }
}
