<?php

declare(strict_types=1);

namespace MarkupCarve\SymfonyCarve;

use InvalidArgumentException;
use MarkupCarve\Carve\CarveConverter;
use MarkupCarve\Carve\Extension\FencedRenderExtension;
use MarkupCarve\Carve\Profile;
use MarkupCarve\Carve\SafeMode;
use MarkupCarve\Carve\Transform\FilesystemIncludeResolver;
use MarkupCarve\Carve\Transform\IncludeExpander;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Renders Carve markup using the carve-php reference implementation.
 *
 * A fresh converter is built per render so heading-id state never leaks
 * between independent snippets (e.g. two `|carve` filters on one page).
 */
final class CarveRenderer
{
    /**
     * Stand-in for an identity that names anything but a path inside the root.
     *
     * @var string
     */
    public const OUTSIDE_ROOT = '[outside-root]';

    private readonly ?FilesystemIncludeResolver $includeResolver;

    /**
     * Canonical containment root, or null while inclusion is disabled.
     */
    private readonly ?string $includeRootReal;

    /**
     * @param bool $safeMode Whether to sanitize the rendered HTML.
     * @param string $rawHtmlMode One of the SafeMode::RAW_HTML_* constants.
     * @param array<string> $diagrams Diagram fenced-block presets to enable
     *   (e.g. `mermaid`, `plantuml`). Unknown names are ignored. Empty (default)
     *   keeps the bare converter, so nothing changes for existing users.
     * @param string|null $profile Feature restriction preset, or null for none.
     * @param \Psr\Log\LoggerInterface|null $logger
     * @param string|null $includeRoot Absolute containment root for opt-in
     *   file includes, or null to keep every directive literal.
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(
        private readonly bool $safeMode = true,
        private readonly string $rawHtmlMode = SafeMode::RAW_HTML_STRIP,
        private readonly array $diagrams = [],
        private readonly ?string $profile = null,
        ?string $includeRoot = null,
        private readonly ?LoggerInterface $logger = null,
    ) {
        // The resolver owns the rule that a configured root must be absolute,
        // so a value it refuses never reaches a render. Naming the config key
        // here is the only thing added.
        if ($includeRoot === null) {
            $this->includeResolver = null;
            $this->includeRootReal = null;

            return;
        }

        try {
            $this->includeResolver = new FilesystemIncludeResolver($includeRoot);
        } catch (RuntimeException $exception) {
            throw new InvalidArgumentException('carve.include_root: ' . $exception->getMessage(), 0, $exception);
        }
        $this->includeRootReal = rtrim((string)realpath($includeRoot), DIRECTORY_SEPARATOR);
    }

    public function render(string $carve): string
    {
        return $this->htmlConverter()->convert($carve);
    }

    public function renderText(string $carve): string
    {
        return $this->configure(CarveConverter::plainText())->convert($carve);
    }

    public function renderMarkdown(string $carve): string
    {
        return $this->configure(CarveConverter::markdown())->convert($carve);
    }

    public function renderFile(string $path): string
    {
        return $this->renderFileWithReport($path)['value'];
    }

    /**
     * @throws \InvalidArgumentException
     *
     * @return array{
     *   value: string,
     *   warnings: list<array{rule: string|null, message: string, file: string|null, line: int, column: int}>,
     *   dependencies: list<array{path: string, resolved: bool}>,
     *   suppressedWarnings: int
     * }
     */
    public function renderFileWithReport(string $path): array
    {
        $sourcePath = realpath($path);
        if ($sourcePath === false || !is_file($sourcePath)) {
            throw new InvalidArgumentException(sprintf('Carve source is not a readable file: %s', $path));
        }

        $source = file_get_contents($sourcePath);
        if ($source === false) {
            throw new InvalidArgumentException(sprintf('Carve source is not readable: %s', $path));
        }
        $root = $this->includeRootReal;
        if ($root === null || $this->includeResolver === null) {
            return ['value' => $this->render($source), 'warnings' => [], 'dependencies' => [], 'suppressedWarnings' => 0];
        }

        if ($sourcePath !== $root && !str_starts_with($sourcePath, $root . DIRECTORY_SEPARATOR)) {
            throw new InvalidArgumentException('Carve source must be inside carve.include_root.');
        }

        $converter = $this->htmlConverter();
        $expander = new IncludeExpander(
            resolver: $this->includeResolver,
            currentPath: $sourcePath,
            source: $source,
        );
        $document = $converter->transform($converter->parse($source), $expander);
        $warnings = array_map(fn ($warning): array => [
            'rule' => $warning->getRule(),
            'message' => $warning->getMessage(),
            'file' => $this->containedIdentity($warning->getFile()),
            'line' => $warning->getLine(),
            'column' => $warning->getColumn(),
        ], $expander->getWarnings());
        $dependencies = array_map(fn ($dependency): array => [
            'path' => $this->containedIdentity($dependency->getTarget()) ?? self::OUTSIDE_ROOT,
            'resolved' => $dependency->isResolved(),
        ], $expander->getDependencies());

        foreach ($warnings as $warning) {
            $this->logger?->warning('Carve include warning: {message}', $warning);
        }

        return [
            'value' => $converter->render($document),
            'warnings' => $warnings,
            'dependencies' => $dependencies,
            'suppressedWarnings' => $expander->getSuppressedWarnings(),
        ];
    }

    private function htmlConverter(): CarveConverter
    {
        $converter = new CarveConverter();
        if ($this->safeMode) {
            $converter->setSafeMode(SafeMode::defaults()->setRawHtmlMode($this->rawHtmlMode));
        } else {
            $converter->setSafeMode(false);
        }

        return $this->configure($converter);
    }

    /**
     * The identity as a path relative to the root, or a fixed marker when it
     * names anything else. A resolver message may embed an absolute path, and
     * a denial keeps the directive's own spelling, so neither is reported.
     */
    private function containedIdentity(?string $identity): ?string
    {
        $root = $this->includeRootReal;
        if ($identity === null || $root === null) {
            return $identity;
        }
        if ($identity === $root) {
            return '.';
        }
        if (str_starts_with($identity, $root . DIRECTORY_SEPARATOR)) {
            $identity = substr($identity, strlen($root) + 1);
        } elseif (str_starts_with($identity, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $identity) === 1) {
            return self::OUTSIDE_ROOT;
        }
        $identity = str_replace('\\', '/', $identity);

        return in_array('..', explode('/', $identity), true) ? self::OUTSIDE_ROOT : $identity;
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
