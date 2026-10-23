<?php

declare(strict_types=1);

namespace MarkupCarve\SymfonyCarve\Command;

use MarkupCarve\SymfonyCarve\CarveTools;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'carve:convert', description: 'Convert a Markdown, HTML or Djot file into Carve')]
final class ConvertCommand extends Command
{
    public function __construct(private readonly CarveTools $tools)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('path', InputArgument::REQUIRED, 'The Markdown, HTML or Djot file to convert')
            ->addOption('from', null, InputOption::VALUE_REQUIRED, 'markdown, html or djot (guessed from the file extension)')
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Write to this file instead of the console');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $path = (string)$input->getArgument('path');
        $source = is_file($path) ? @file_get_contents($path) : false;
        if ($source === false) {
            $io->error(sprintf('File %s is not readable.', $path));

            return Command::FAILURE;
        }

        $from = $input->getOption('from');
        $from = is_string($from) && $from !== '' ? $from : match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'md', 'markdown' => 'markdown',
            'html', 'htm' => 'html',
            'dj', 'djot' => 'djot',
            default => null,
        };
        if ($from === null || !in_array($from, CarveTools::IMPORT_FORMATS, true)) {
            $io->error('Cannot tell the input format. Pass --from=markdown, --from=html or --from=djot.');

            return Command::FAILURE;
        }
        $target = $input->getOption('output');

        return CarveFiles::emit($this->tools->import($source, $from), is_string($target) ? $target : null, $io) ? Command::SUCCESS : Command::FAILURE;
    }
}
