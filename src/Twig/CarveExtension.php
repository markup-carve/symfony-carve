<?php

declare(strict_types=1);

namespace MarkupCarve\Symfony\Twig;

use MarkupCarve\Symfony\CarveRenderer;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Exposes Carve rendering to Twig:
 *   {{ source|carve }} filter
 *   {{ carve(source) }} function
 *
 * Output is marked safe because the renderer already sanitizes per the
 * configured safe mode.
 */
final class CarveExtension extends AbstractExtension
{
    /**
     * @param \MarkupCarve\Symfony\CarveRenderer $renderer
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
}
