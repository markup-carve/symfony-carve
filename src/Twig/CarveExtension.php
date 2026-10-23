<?php

declare(strict_types=1);

namespace MarkupCarve\SymfonyCarve\Twig;

use InvalidArgumentException;
use MarkupCarve\SymfonyCarve\CarveRenderer;
use MarkupCarve\SymfonyCarve\CarveTools;
use MarkupCarve\SymfonyCarve\RenderedCarve;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Exposes Carve to Twig:
 *   {{ source|carve }} / {{ carve(source) }}, both taking render options
 *   {{ source|carve_text }} and {{ source|carve_markdown }}
 *   {{ source|carve_format(align_tables = false) }}
 *   {{ carve_file('docs/page.crv') }} below the configured include_root
 *   {% set doc = carve_document(source) %} for the table of contents and frontmatter
 *   carve_lint(source) for lint findings
 *
 * HTML output is marked safe because the renderer already sanitizes per the
 * configured safe mode. Every other output uses Twig's normal escaping.
 */
final class CarveExtension extends AbstractExtension
{
    public function __construct(
        private readonly CarveRenderer $renderer,
        private readonly CarveTools $tools = new CarveTools(),
    ) {
    }

    /**
     * @return list<\Twig\TwigFilter>
     */
    public function getFilters(): array
    {
        return [
            new TwigFilter('carve', $this->render(...), ['is_safe' => ['html']]),
            new TwigFilter('carve_text', $this->renderText(...)),
            new TwigFilter('carve_markdown', $this->renderMarkdown(...)),
            new TwigFilter('carve_format', $this->format(...)),
        ];
    }

    /**
     * @return list<\Twig\TwigFunction>
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('carve', $this->render(...), ['is_safe' => ['html']]),
            new TwigFunction('carve_file', $this->renderFile(...), ['is_safe' => ['html']]),
            new TwigFunction('carve_document', $this->document(...)),
            new TwigFunction('carve_lint', $this->lint(...)),
        ];
    }

    /**
     * @param string $carve
     * @param array<string, mixed> $options
     */
    public function render(string $carve, array $options = []): string
    {
        return $this->renderer->withOptions($options)->render($carve);
    }

    /**
     * @param string $carve
     * @param array<string, mixed> $options
     */
    public function renderText(string $carve, array $options = []): string
    {
        return $this->renderer->withOptions($options)->renderText($carve);
    }

    /**
     * @param string $carve
     * @param array<string, mixed> $options
     */
    public function renderMarkdown(string $carve, array $options = []): string
    {
        return $this->renderer->withOptions($options)->renderMarkdown($carve);
    }

    /**
     * @param string $carve
     * @param array<string, mixed> $options
     */
    public function document(string $carve, array $options = []): RenderedCarve
    {
        return $this->renderer->withOptions($options)->renderResult($carve);
    }

    public function format(string $carve, bool $alignTables = false): string
    {
        return $this->tools->format($carve, $alignTables);
    }

    /**
     * Render a file below the configured include root. A relative path
     * resolves against that root; templates never read files without one.
     *
     * @param string $path
     * @param array<string, mixed> $options
     *
     * @throws \InvalidArgumentException
     */
    public function renderFile(string $path, array $options = []): string
    {
        $root = $this->renderer->getIncludeRoot();
        if ($root === null) {
            throw new InvalidArgumentException('carve_file() needs carve.include_root to be configured.');
        }
        if (!str_starts_with($path, '/') && preg_match('/^[A-Za-z]:[\\\\\/]/', $path) !== 1) {
            $path = $root . DIRECTORY_SEPARATOR . $path;
        }

        return $this->renderer->withOptions($options)->renderFile($path);
    }

    /**
     * @return list<array{line: int, column: int, rule: string, message: string}>
     */
    public function lint(string $carve): array
    {
        return array_map(static fn ($warning): array => [
            'line' => $warning->line,
            'column' => $warning->column,
            'rule' => $warning->rule,
            'message' => $warning->message,
        ], $this->tools->lint($carve));
    }
}
