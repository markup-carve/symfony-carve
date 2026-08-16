<?php

declare(strict_types=1);

namespace MarkupCarve\SymfonyCarve\Twig;

use MarkupCarve\SymfonyCarve\CarveRenderer;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Exposes Carve rendering to Twig:
 *   {{ source|carve }} filter
 *   {{ source|carve_text }} filter
 *   {{ source|carve_markdown }} filter
 *   {{ carve(source) }} function
 *
 * HTML output is marked safe because the renderer already sanitizes per the
 * configured safe mode. Text and Markdown output use Twig's normal escaping.
 */
final class CarveExtension extends AbstractExtension
{
    /**
     * @param \MarkupCarve\SymfonyCarve\CarveRenderer $renderer
     */
    public function __construct(private readonly CarveRenderer $renderer)
    {
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
        ];
    }

    /**
     * @return list<\Twig\TwigFunction>
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('carve', $this->render(...), ['is_safe' => ['html']]),
        ];
    }

    public function render(string $carve): string
    {
        return $this->renderer->render($carve);
    }

    public function renderText(string $carve): string
    {
        return $this->renderer->renderText($carve);
    }

    public function renderMarkdown(string $carve): string
    {
        return $this->renderer->renderMarkdown($carve);
    }
}
