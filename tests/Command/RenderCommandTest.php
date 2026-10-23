<?php

declare(strict_types=1);

namespace MarkupCarve\SymfonyCarve\Tests\Command;

use MarkupCarve\SymfonyCarve\CarveRenderer;
use MarkupCarve\SymfonyCarve\Command\RenderCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class RenderCommandTest extends CommandTestCase
{
    public function testRendersHtmlByDefault(): void
    {
        $tester = new CommandTester(new RenderCommand(new CarveRenderer()));

        self::assertSame(Command::SUCCESS, $tester->execute(['path' => $this->file('a.crv', "# Title\n")]));
        self::assertStringContainsString('<h1>Title</h1>', $tester->getDisplay());
    }

    public function testRendersTextMarkdownAndAnsi(): void
    {
        $path = $this->file('a.crv', "# Title\n\n*bold*\n");
        $tester = new CommandTester(new RenderCommand(new CarveRenderer()));

        $tester->execute(['path' => $path, '--format' => 'markdown']);
        self::assertStringContainsString('**bold**', $tester->getDisplay());
        $tester->execute(['path' => $path, '--format' => 'text']);
        self::assertStringNotContainsString('<', $tester->getDisplay());
        $tester->execute(['path' => $path, '--format' => 'ansi']);
        self::assertStringContainsString("\e[", $tester->getDisplay(true));
    }

    public function testExpandsIncludesBelowTheConfiguredRoot(): void
    {
        $this->file('part.crv', "Included.\n");
        $tester = new CommandTester(new RenderCommand(new CarveRenderer(includeRoot: $this->dir)));

        $tester->execute(['path' => $this->file('main.crv', "{{ part.crv }}\n"), '--format' => 'text']);

        self::assertStringContainsString('Included.', $tester->getDisplay());
    }

    public function testACodeIncludeRendersTheFileAsACodeBlock(): void
    {
        $this->file('snippet.php', "<?php echo 1;\n");
        $tester = new CommandTester(new RenderCommand(new CarveRenderer(includeRoot: $this->dir)));

        $tester->execute(['path' => $this->file('main.crv', "{{ snippet.php @code:php }}\n")]);

        self::assertStringContainsString('<code class="language-php">&lt;?php echo 1;', $tester->getDisplay());
    }

    public function testPresetOptionAppliesAProfile(): void
    {
        $tester = new CommandTester(new RenderCommand(new CarveRenderer()));

        $tester->execute(['path' => $this->file('a.crv', "# Title\n"), '--preset' => 'comment']);

        self::assertStringNotContainsString('<h1', $tester->getDisplay());
    }

    public function testWritesToAnOutputFile(): void
    {
        $tester = new CommandTester(new RenderCommand(new CarveRenderer()));
        $target = $this->dir . '/out.html';

        self::assertSame(Command::SUCCESS, $tester->execute(['path' => $this->file('a.crv', "# T\n"), '--output' => $target]));
        self::assertStringContainsString('<h1>T</h1>', (string)file_get_contents($target));
    }

    public function testFailsOnAMissingFileOrUnknownFormat(): void
    {
        $tester = new CommandTester(new RenderCommand(new CarveRenderer()));

        self::assertSame(Command::FAILURE, $tester->execute(['path' => $this->dir . '/missing.crv']));
        self::assertSame(Command::FAILURE, $tester->execute(['path' => $this->file('a.crv', "x\n"), '--format' => 'pdf']));
        self::assertStringContainsString('Unknown Carve output format', $tester->getDisplay());
    }
}
