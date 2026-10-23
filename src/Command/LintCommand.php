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

#[AsCommand(name: 'carve:lint', description: 'Report Carve constructs that probably do not mean what the author intended')]
final class LintCommand extends Command
{
    public function __construct(private readonly CarveTools $tools)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('paths', InputArgument::REQUIRED | InputArgument::IS_ARRAY, 'Files or directories (directories are searched for *.crv)')
            ->addOption('include-root', null, InputOption::VALUE_REQUIRED, 'Also lint the files that include directives pull in from below this root');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $includeRoot = $input->getOption('include-root');
        $includeRoot = is_string($includeRoot) && $includeRoot !== '' ? $includeRoot : null;
        if ($includeRoot !== null) {
            $resolved = realpath($includeRoot);
            if ($resolved === false || !is_dir($resolved)) {
                $io->error(sprintf('Include root %s is not a directory.', $includeRoot));

                return Command::FAILURE;
            }
            $includeRoot = $resolved;
        }

        $missing = false;
        /** @var array<string> $paths */
        $paths = (array)$input->getArgument('paths');
        $rows = [];
        foreach (CarveFiles::collect($paths, $io, $missing) as $file) {
            $source = @file_get_contents($file);
            if ($source === false) {
                $missing = true;
                $io->error(sprintf('File %s is not readable.', $file));

                continue;
            }
            $path = $includeRoot === null ? $file : ((string)realpath($file) ?: $file);
            foreach ($this->tools->lint($source, $includeRoot, $includeRoot === null ? null : $path) as $warning) {
                $location = sprintf('%s:%d:%d', $warning->file ?? $path, $warning->line, $warning->column);
                // A file that is both linted and included by another one would report twice.
                $rows[$location . "\0" . $warning->rule . "\0" . $warning->message] = [$location, $warning->rule, $warning->message];
            }
        }

        if ($rows === []) {
            $io->success('No Carve problems found.');

            return $missing ? Command::FAILURE : Command::SUCCESS;
        }
        $io->table(['Location', 'Rule', 'Message'], array_values($rows));
        $io->error(sprintf('%d Carve problem(s) found.', count($rows)));

        return Command::FAILURE;
    }
}
