<?php

declare(strict_types=1);

namespace Carve\Symfony\Twig;

use Carve\Symfony\CarveRenderer;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Exposes Carve rendering to Twig:
 *   {{ source|carve }}      filter
 *   {{ carve(source) }}     function
 *
 * Output is marked safe because the renderer already sanitizes per the
 * configured safe mode.
 */
final class CarveExtension extends AbstractExtension
{
    public function __construct(private readonly CarveRenderer $renderer)
    {
    }

    /**
     * @return list<TwigFilter>
     */
    public function getFilters(): array
    {
        return [
            new TwigFilter('carve', $this->render(...), ['is_safe' => ['html']]),
        ];
    }

    /**
     * @return list<TwigFunction>
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
