<?php

declare(strict_types=1);

namespace MarkupCarve\SymfonyCarve\Tests\Command;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

abstract class CommandTestCase extends TestCase
{
    protected string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/symfony-carve-cmd-' . bin2hex(random_bytes(4));
        mkdir($this->dir . '/nested', 0777, true);
    }

    protected function tearDown(): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo) {
                $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            }
        }
        rmdir($this->dir);
    }

    protected function file(string $name, string $content): string
    {
        $path = $this->dir . '/' . $name;
        file_put_contents($path, $content);

        return $path;
    }
}
