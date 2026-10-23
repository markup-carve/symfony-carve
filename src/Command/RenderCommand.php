<?php

declare(strict_types=1);

namespace MarkupCarve\SymfonyCarve\Command;

use MarkupCarve\SymfonyCarve\CarveRenderer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

#[AsCommand(name: 'carve:render', description: 'Render a Carve file as HTML, plain text, Markdown or ANSI')]
final class RenderCommand extends Command
{
    public function __construct(private readonly CarveRenderer $renderer)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('path', InputArgument::REQUIRED, 'The .crv file to render')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'html, text, markdown or ansi', 'html')
            ->addOption('preset', null, InputOption::VALUE_REQUIRED, 'Render with this profile instead of the configured one')
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Write to this file instead of the console');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $path = (string)$input->getArgument('path');
        $preset = $input->getOption('preset');

        try {
            $renderer = is_string($preset) ? $this->renderer->withOptions(['preset' => $preset]) : $this->renderer;
            $rendered = $renderer->renderFileAs($path, (string)$input->getOption('format'));
        } catch (Throwable $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }
        $target = $input->getOption('output');

        return CarveFiles::emit($rendered, is_string($target) ? $target : null, $io) ? Command::SUCCESS : Command::FAILURE;
    }
}
