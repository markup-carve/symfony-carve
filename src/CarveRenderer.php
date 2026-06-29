<?php

declare(strict_types=1);

namespace MarkupCarve\Symfony;

use MarkupCarve\Carve\CarveConverter;
use MarkupCarve\Carve\SafeMode;

/**
 * Renders Carve markup to HTML using the carve-php reference implementation.
 *
 * A fresh converter is built per render so heading-id state never leaks
 * between independent snippets (e.g. two `|carve` filters on one page).
 */
final class CarveRenderer
{
    /**
     * @param bool $safeMode Whether to sanitize the rendered HTML.
     * @param string $rawHtmlMode One of the SafeMode::RAW_HTML_* constants.
     */
    public function __construct(
        private readonly bool $safeMode = true,
        private readonly string $rawHtmlMode = SafeMode::RAW_HTML_STRIP,
    ) {
    }

    public function render(string $carve): string
    {
        $converter = new CarveConverter();

        if ($this->safeMode) {
            $converter->setSafeMode(SafeMode::defaults()->setRawHtmlMode($this->rawHtmlMode));
        } else {
            $converter->setSafeMode(false);
        }

        return $converter->convert($carve);
    }
}
