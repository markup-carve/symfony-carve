<?php

declare(strict_types=1);

namespace MarkupCarve\SymfonyCarve;

use InvalidArgumentException;
use MarkupCarve\Carve\CarveConverter;
use MarkupCarve\Carve\Extension\FencedRenderExtension;
use MarkupCarve\Carve\Profile;
use MarkupCarve\Carve\SafeMode;

/**
 * Renders Carve markup using the carve-php reference implementation.
 *
 * A fresh converter is built per render so heading-id state never leaks
 * between independent snippets (e.g. two `|carve` filters on one page).
 */
final class CarveRenderer
{
    /**
     * @param bool $safeMode Whether to sanitize the rendered HTML.
     * @param string $rawHtmlMode One of the SafeMode::RAW_HTML_* constants.
     * @param array<string> $diagrams Diagram fenced-block presets to enable
     *   (e.g. `mermaid`, `plantuml`). Unknown names are ignored. Empty (default)
     *   keeps the bare converter, so nothing changes for existing users.
     * @param string|null $profile Feature restriction preset, or null for none.
     */
    public function __construct(
        private readonly bool $safeMode = true,
        private readonly string $rawHtmlMode = SafeMode::RAW_HTML_STRIP,
        private readonly array $diagrams = [],
        private readonly ?string $profile = null,
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

        return $this->configure($converter)->convert($carve);
    }

    public function renderText(string $carve): string
    {
        return $this->configure(CarveConverter::plainText())->convert($carve);
    }

    public function renderMarkdown(string $carve): string
    {
        return $this->configure(CarveConverter::markdown())->convert($carve);
    }

    private function configure(CarveConverter $converter): CarveConverter
    {
        if ($this->profile !== null) {
            $converter->setProfile(match ($this->profile) {
                'full' => Profile::full(),
                'article' => Profile::article(),
                'comment' => Profile::comment(),
                'minimal' => Profile::minimal(),
                default => throw new InvalidArgumentException(sprintf('Unknown Carve profile "%s".', $this->profile)),
            });
        }

        if ($this->diagrams !== []) {
            $factories = self::diagramPresetFactories();
            foreach ($this->diagrams as $name) {
                if (!isset($factories[$name])) {
                    continue;
                }

                $converter->addExtension($factories[$name]());
            }
        }

        return $converter;
    }

    /**
     * Map of config preset name to a factory producing the matching
     * {@see \MarkupCarve\Carve\Extension\FencedRenderExtension} instance.
     *
     * @return array<string, \Closure(): \MarkupCarve\Carve\Extension\FencedRenderExtension>
     */
    private static function diagramPresetFactories(): array
    {
        return [
            'mermaid' => static fn (): FencedRenderExtension => FencedRenderExtension::mermaid(),
            'd2' => static fn (): FencedRenderExtension => FencedRenderExtension::d2(),
            'graphviz' => static fn (): FencedRenderExtension => FencedRenderExtension::graphviz(),
            'wavedrom' => static fn (): FencedRenderExtension => FencedRenderExtension::wavedrom(),
            'abc' => static fn (): FencedRenderExtension => FencedRenderExtension::abc(),
            'vega_lite' => static fn (): FencedRenderExtension => FencedRenderExtension::vegaLite(),
            'chart' => static fn (): FencedRenderExtension => FencedRenderExtension::chart(),
            // The plantuml() preset factory only exists in newer carve-php; build
            // it directly (same config as that factory: claims `plantuml`/`puml`,
            // cssClass `plantuml`) so the option works on the pinned 0.1.x too.
            'plantuml' => static fn (): FencedRenderExtension => new FencedRenderExtension(
                language: ['plantuml', 'puml'],
                cssClass: 'plantuml',
            ),
        ];
    }
}
