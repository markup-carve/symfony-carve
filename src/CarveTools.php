<?php

declare(strict_types=1);

namespace MarkupCarve\SymfonyCarve;

use InvalidArgumentException;
use MarkupCarve\Carve\Carve;
use MarkupCarve\Carve\Include\FileResolver;
use Throwable;

final class CarveTools
{
    /**
     * @var array<string>
     */
    public const IMPORT_FORMATS = ['markdown', 'gfm', 'html', 'djot'];

    public function format(string $source, bool $alignTables = false): string
    {
        return Carve::format($source, ['alignTables' => $alignTables]);
    }

    public function isFormatted(string $source, bool $alignTables = false): bool
    {
        return $this->format($source, $alignTables) === $source;
    }

    /**
     * @return list<\MarkupCarve\SymfonyCarve\LintFinding>
     */
    public function lint(string $source, ?string $includeRoot = null, ?string $sourcePath = null): array
    {
        if ($includeRoot === null) {
            return $this->findings(Carve::lint($source), $source);
        }
        try {
            $resolver = FileResolver::forRoot($includeRoot);
        } catch (Throwable $exception) {
            throw new InvalidArgumentException('Carve include root: ' . $exception->getMessage(), 0, $exception);
        }

        return $this->findings(Carve::lint($source, ['includes' => ['resolver' => $resolver, 'file' => $sourcePath]]), $source, $includeRoot);
    }

    /**
     * @param array<\MarkupCarve\Carve\Diagnostics\Diagnostic> $diagnostics
     * @param string|null $root
     * @param string $source
     *
     * @return list<\MarkupCarve\SymfonyCarve\LintFinding>
     */
    private function findings(array $diagnostics, string $source, ?string $root = null): array
    {
        $result = [];
        foreach ($diagnostics as $diagnostic) {
            $file = $diagnostic->file;
            $content = $file === null ? $source : @file_get_contents((string)$root . '/' . $file);
            $before = is_string($content) ? substr($content, 0, max(0, $diagnostic->startOffset)) : '';
            $last = strrpos($before, "\n");
            $result[] = new LintFinding(
                $diagnostic->code,
                $diagnostic->message,
                $file,
                substr_count($before, "\n") + 1,
                strlen($last === false ? $before : substr($before, $last + 1)) + 1,
            );
        }

        return $result;
    }

    /**
     * Reads GitHub Flavored Markdown, as the 0.1 importer did; the engine's `markdown` format is CommonMark only.
     */
    public function fromMarkdown(string $source): string
    {
        return Carve::importDocument($source, ['from' => 'gfm'])->source;
    }

    public function fromHtml(string $source): string
    {
        return Carve::importHtml($source)->source;
    }

    public function fromDjot(string $source): string
    {
        return Carve::importDocument($source, ['from' => 'djot'])->source;
    }

    public function import(string $source, string $from): string
    {
        return match ($from) {
            'markdown', 'gfm' => $this->fromMarkdown($source),
            'html' => $this->fromHtml($source),
            'djot' => $this->fromDjot($source),
            default => throw new InvalidArgumentException(sprintf('Unknown import format "%s". Use %s.', $from, implode(', ', self::IMPORT_FORMATS))),
        };
    }
}
