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

#[AsCommand(name: 'carve:trace', description: 'Print the block decision trace of a Carve file as JSON lines')]
final class TraceCommand extends Command
{
    public function __construct(private readonly CarveTools $tools)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('path', InputArgument::REQUIRED, 'The .crv file to trace')
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
        $target = $input->getOption('output');

        return CarveFiles::emit($this->tools->trace($source), is_string($target) ? $target : null, $io) ? Command::SUCCESS : Command::FAILURE;
    }
}
