<?php

declare(strict_types=1);

namespace MarkupCarve\SymfonyCarve\Command;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 */
final class CarveFiles
{
    /**
     * Files named on the command line, plus every `*.crv` below a named
     * directory. A path that does not exist is reported and flags `$missing`.
     *
     * @param array<string> $paths
     * @param \Symfony\Component\Console\Style\SymfonyStyle $io
     * @param bool $missing
     *
     * @return list<string>
     */
    public static function collect(array $paths, SymfonyStyle $io, bool &$missing): array
    {
        $files = [];
        foreach ($paths as $path) {
            if (is_dir($path)) {
                $found = [];
                $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));
                foreach ($iterator as $file) {
                    if ($file instanceof SplFileInfo && $file->isFile() && $file->getExtension() === 'crv') {
                        $found[] = $file->getPathname();
                    }
                }
                sort($found);
                array_push($files, ...$found);
            } elseif (is_file($path)) {
                $files[] = $path;
            } else {
                $missing = true;
                $io->warning(sprintf('Skipping %s: not found.', $path));
            }
        }

        return array_values(array_unique($files));
    }

    /**
     * Write to a file, or to the console when no target is given.
     */
    public static function emit(string $output, ?string $target, SymfonyStyle $io): bool
    {
        if ($target === null || $target === '') {
            $io->write($output);

            return true;
        }
        if (@file_put_contents($target, $output) === false) {
            $io->error(sprintf('Cannot write to %s.', $target));

            return false;
        }
        $io->success(sprintf('Written to %s.', $target));

        return true;
    }
}
