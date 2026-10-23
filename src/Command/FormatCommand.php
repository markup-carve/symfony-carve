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

#[AsCommand(name: 'carve:fmt', description: 'Print Carve files in canonical form, or rewrite or check them')]
final class FormatCommand extends Command
{
    public function __construct(private readonly CarveTools $tools)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('paths', InputArgument::REQUIRED | InputArgument::IS_ARRAY, 'Files or directories (directories are searched for *.crv)')
            ->addOption('write', 'w', InputOption::VALUE_NONE, 'Rewrite each file in place')
            ->addOption('check', 'c', InputOption::VALUE_NONE, 'Exit unsuccessfully if a file is not formatted, without changing it')
            ->addOption('align-tables', null, InputOption::VALUE_NONE, 'Pad pipe table cells so the columns line up');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $write = (bool)$input->getOption('write');
        $check = (bool)$input->getOption('check');
        if ($write && $check) {
            $io->error('--write and --check are mutually exclusive.');

            return Command::FAILURE;
        }
        $alignTables = (bool)$input->getOption('align-tables');

        $failed = false;
        $unformatted = 0;
        /** @var array<string> $paths */
        $paths = (array)$input->getArgument('paths');
        foreach (CarveFiles::collect($paths, $io, $failed) as $file) {
            $source = @file_get_contents($file);
            if ($source === false) {
                $failed = true;
                $io->error(sprintf('File %s is not readable.', $file));

                continue;
            }
            $formatted = $this->tools->format($source, $alignTables);
            if ($check) {
                if ($formatted !== $source) {
                    $unformatted++;
                    $io->writeln(sprintf('%s is not formatted', $file));
                }

                continue;
            }
            if ($write) {
                if ($formatted !== $source && @file_put_contents($file, $formatted) === false) {
                    $failed = true;
                    $io->error(sprintf('File %s could not be written.', $file));
                }

                continue;
            }
            $io->write($formatted);
        }

        if ($unformatted > 0) {
            $io->error(sprintf('%d file(s) not formatted.', $unformatted));

            return Command::FAILURE;
        }

        return $failed ? Command::FAILURE : Command::SUCCESS;
    }
}
