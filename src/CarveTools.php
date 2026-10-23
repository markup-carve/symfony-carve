<?php

declare(strict_types=1);

namespace MarkupCarve\SymfonyCarve;

use InvalidArgumentException;
use MarkupCarve\Carve\CarveConverter;
use MarkupCarve\Carve\Converter\DjotToCarve;
use MarkupCarve\Carve\Converter\HtmlToCarve;
use MarkupCarve\Carve\Converter\MarkdownToCarve;
use MarkupCarve\Carve\Lint\DocumentLinter;
use MarkupCarve\Carve\Lint\IncludeLinter;
use MarkupCarve\Carve\Transform\FilesystemIncludeResolver;
use RuntimeException;

/**
 * Source-level Carve operations: format, lint and import.
 */
final class CarveTools
{
    /**
     * @var array<string>
     */
    public const IMPORT_FORMATS = ['markdown', 'html', 'djot'];

    /**
     * Canonical Carve source, as `carve fmt` prints it.
     */
    public function format(string $source, bool $alignTables = false): string
    {
        return CarveConverter::toCarve($source, $alignTables);
    }

    public function isFormatted(string $source, bool $alignTables = false): bool
    {
        return $this->format($source, $alignTables) === $source;
    }

    /**
     * Findings from every carve-php document lint rule. With an include root,
     * included files are linted too and their findings carry `file` and
     * `includedBy`.
     *
     * @throws \InvalidArgumentException
     *
     * @return list<\MarkupCarve\Carve\Lint\LintWarning>
     */
    public function lint(string $source, ?string $includeRoot = null, ?string $sourcePath = null): array
    {
        if ($includeRoot === null) {
            return (new DocumentLinter())->lint($source);
        }
        try {
            $resolver = new FilesystemIncludeResolver($includeRoot);
        } catch (RuntimeException $exception) {
            throw new InvalidArgumentException('Carve include root: ' . $exception->getMessage(), 0, $exception);
        }

        return (new IncludeLinter())->lint($source, [], $resolver, $sourcePath);
    }

    public function fromMarkdown(string $source): string
    {
        return (new MarkdownToCarve())->convert($source);
    }

    public function fromHtml(string $source): string
    {
        return (new HtmlToCarve())->convert($source);
    }

    public function fromDjot(string $source): string
    {
        return (new DjotToCarve())->convert($source);
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function import(string $source, string $from): string
    {
        return match ($from) {
            'markdown' => $this->fromMarkdown($source),
            'html' => $this->fromHtml($source),
            'djot' => $this->fromDjot($source),
            default => throw new InvalidArgumentException(sprintf('Unknown import format "%s". Use %s.', $from, implode(', ', self::IMPORT_FORMATS))),
        };
    }
}
