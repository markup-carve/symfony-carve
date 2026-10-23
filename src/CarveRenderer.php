<?php

declare(strict_types=1);

namespace MarkupCarve\SymfonyCarve;

use Closure;
use InvalidArgumentException;
use MarkupCarve\Carve\CarveConverter;
use MarkupCarve\Carve\Extension\ExtensionInterface;
use MarkupCarve\Carve\Extension\FencedRenderExtension;
use MarkupCarve\Carve\Extension\FrontmatterExtension;
use MarkupCarve\Carve\Extension\TableOfContentsExtension;
use MarkupCarve\Carve\Node\Document;
use MarkupCarve\Carve\Parser\BlockParser;
use MarkupCarve\Carve\Profile;
use MarkupCarve\Carve\Renderer\AnsiRenderer;
use MarkupCarve\Carve\Renderer\MarkdownRenderer;
use MarkupCarve\Carve\Renderer\PlainTextRenderer;
use MarkupCarve\Carve\Renderer\RendererInterface;
use MarkupCarve\Carve\Renderer\RenderMode;
use MarkupCarve\Carve\Renderer\SoftBreakMode;
use MarkupCarve\Carve\SafeMode;
use MarkupCarve\Carve\Transform\FilesystemIncludeResolver;
use MarkupCarve\Carve\Transform\IncludeExpander;
use MarkupCarve\SymfonyCarve\Extension\ExtensionFactory;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Throwable;

/**
 * Renders Carve markup using the carve-php reference implementation.
 *
 * A fresh converter, with fresh extension instances, is built per render so
 * heading-id and extension state never leak between independent snippets
 * (e.g. two `|carve` filters on one page).
 */
final class CarveRenderer
{
    /**
     * Stand-in for an identity that names anything but a path inside the root.
     *
     * @var string
     */
    public const OUTSIDE_ROOT = '[outside-root]';

    /**
     * Render options a caller may override per call, keyed by their
     * snake_case spelling, mapped to the constructor argument they replace.
     *
     * @var array<string, string>
     */
    public const OPTIONS = [
        'safe_mode' => 'safeMode',
        'raw_html' => 'rawHtmlMode',
        'profile' => 'profile',
        'preset' => 'profile',
        'on_disallowed' => 'onDisallowed',
        'mode' => 'mode',
        'soft_break_mode' => 'softBreakMode',
        'xhtml' => 'xhtml',
        'source_lines' => 'sourceLines',
        'smart_typography' => 'smartTypography',
    ];

    private readonly ?FilesystemIncludeResolver $includeResolver;

    /**
     * Canonical containment root, or null while inclusion is disabled.
     */
    private readonly ?string $includeRootReal;

    private readonly ExtensionFactory $extensionFactory;

    private readonly ?CacheInterface $activeCache;

    private readonly string $cacheSignature;

    /**
     * @var array<string, mixed>
     */
    private readonly array $arguments;

    /**
     * @param \MarkupCarve\Carve\SafeMode|string|bool $safeMode `true`, `false`, `"strict"` or a SafeMode instance.
     * @param string $rawHtmlMode One of the SafeMode::RAW_HTML_* constants, used when `$safeMode` is `true`.
     * @param array<string> $diagrams Diagram fenced-block presets to enable
     *   (e.g. `mermaid`, `plantuml`). Unknown names are ignored.
     * @param string|null $profile Feature restriction preset, or null for none.
     * @param string|null $includeRoot Absolute containment root for opt-in
     *   file includes, or null to keep every directive literal.
     * @param \Psr\Log\LoggerInterface|null $logger
     * @param string $mode Render mode: `interactive` or `static`.
     * @param string|null $softBreakMode `newline`, `space`, `br` or null for the engine default.
     * @param bool $xhtml
     * @param array<string, string> $symbols Trusted, unescaped HTML replacements for `:name:` symbols.
     * @param bool $sourceLines
     * @param bool|null $smartTypography
     * @param array<string, string> $labels
     * @param string|null $onDisallowed `strip`, `to_text` or `error` for markup the profile disallows.
     * @param array<\MarkupCarve\Carve\Extension\ExtensionInterface|array<string, mixed>|string> $extensions
     *   Extension entries, see {@see \MarkupCarve\SymfonyCarve\Extension\ExtensionFactory}.
     * @param \Symfony\Contracts\Cache\CacheInterface|null $cache
     * @param int|null $cacheTtl
     * @param string $cachePrefix
     * @param string|null $cacheVersion Application version that enables caching
     *   when an extension instance cannot be fingerprinted.
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(
        private readonly SafeMode|string|bool $safeMode = true,
        private readonly string $rawHtmlMode = SafeMode::RAW_HTML_STRIP,
        private readonly array $diagrams = [],
        private readonly ?string $profile = null,
        private readonly ?string $includeRoot = null,
        private readonly ?LoggerInterface $logger = null,
        private readonly string $mode = RenderMode::INTERACTIVE,
        private readonly ?string $softBreakMode = null,
        private readonly bool $xhtml = false,
        private readonly array $symbols = [],
        private readonly bool $sourceLines = false,
        private readonly ?bool $smartTypography = null,
        private readonly array $labels = [],
        private readonly ?string $onDisallowed = null,
        private readonly array $extensions = [],
        private readonly ?CacheInterface $cache = null,
        private readonly ?int $cacheTtl = null,
        private readonly string $cachePrefix = 'carve',
        private readonly ?string $cacheVersion = null,
    ) {
        $this->arguments = get_defined_vars();
        $this->extensionFactory = new ExtensionFactory();

        $this->resolveSafeMode();
        $this->resolveProfile();
        if (!in_array($mode, RenderMode::ALL, true)) {
            throw new InvalidArgumentException(sprintf('Unknown Carve render mode "%s".', $mode));
        }
        if ($softBreakMode !== null && SoftBreakMode::tryFrom($softBreakMode) === null) {
            throw new InvalidArgumentException(sprintf('Unknown Carve soft break mode "%s".', $softBreakMode));
        }
        foreach ($extensions as $extension) {
            $this->extensionFactory->create($extension);
        }
        if (preg_match('/[{}()\/\\\\@:]/', $cachePrefix) === 1) {
            throw new InvalidArgumentException('carve.cache.prefix must not contain any of {}()/\@:');
        }

        // The resolver owns the rule that a configured root must be absolute,
        // so a value it refuses never reaches a render. Naming the config key
        // here is the only thing added.
        if ($includeRoot === null) {
            $this->includeResolver = null;
            $this->includeRootReal = null;
        } else {
            try {
                $this->includeResolver = new FilesystemIncludeResolver($includeRoot);
            } catch (RuntimeException $exception) {
                throw new InvalidArgumentException('carve.include_root: ' . $exception->getMessage(), 0, $exception);
            }
            $this->includeRootReal = rtrim((string)realpath($includeRoot), DIRECTORY_SEPARATOR);
        }

        // Extension instances holding closures cannot be serialized, so caching
        // needs an explicit application version to stay correct for them.
        $cacheable = true;
        try {
            $extensionState = serialize([$safeMode, $extensions]);
        } catch (Throwable) {
            $extensionState = array_map(static fn ($extension): string => is_object($extension) ? $extension::class : serialize($extension), $extensions);
            $cacheable = $cacheVersion !== null;
        }
        $this->activeCache = $cacheable ? $cache : null;
        $this->cacheSignature = hash('xxh3', serialize([
            CarveConverter::LIB_VERSION,
            $rawHtmlMode, $diagrams, $profile, $onDisallowed, $includeRoot, $mode, $softBreakMode, $xhtml,
            $symbols, $sourceLines, $smartTypography, $labels, $extensionState, $cacheVersion,
        ]));
    }

    /**
     * A renderer with some render options replaced, for one rendering
     * context. Keys are those of {@see self::OPTIONS}.
     *
     * @param array<string, mixed> $options
     *
     * @throws \InvalidArgumentException
     */
    public function withOptions(array $options): self
    {
        if ($options === []) {
            return $this;
        }
        $arguments = $this->arguments;
        foreach ($options as $key => $value) {
            if (!isset(self::OPTIONS[$key])) {
                throw new InvalidArgumentException(sprintf(
                    'Unknown Carve render option "%s". Known options: %s',
                    $key,
                    implode(', ', array_keys(self::OPTIONS)),
                ));
            }
            $arguments[self::OPTIONS[$key]] = $value;
        }

        try {
            return new self(...$arguments);
        } catch (InvalidArgumentException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new InvalidArgumentException('Invalid Carve render options: ' . $exception->getMessage(), 0, $exception);
        }
    }

    /**
     * The canonical include root, or null while file includes are disabled.
     */
    public function getIncludeRoot(): ?string
    {
        return $this->includeRootReal;
    }

    public function render(string $carve): string
    {
        return $this->cached('render', hash('xxh3', $carve), fn (): string => $this->htmlConverter()->convert($carve));
    }

    /**
     * Render to HTML and collect the table of contents and frontmatter.
     */
    public function renderResult(string $carve): RenderedCarve
    {
        /** @var array{source: string, html: string, toc: list<array{level: int, text: string, html: string, id: string}>, frontmatter: array{format: string, content: string}|null} $data */
        $data = $this->cached('result', hash('xxh3', $carve), function () use ($carve): array {
            $converter = $this->htmlConverter(withToc: true);

            return $this->result($carve, $converter, $converter->parse($carve))->toArray();
        });

        return new RenderedCarve($data['source'], $data['html'], $data['toc'], $data['frontmatter']);
    }

    public function renderText(string $carve): string
    {
        return $this->converter(new PlainTextRenderer())->convert($carve);
    }

    public function renderMarkdown(string $carve): string
    {
        return $this->converter(new MarkdownRenderer())->convert($carve);
    }

    public function renderAnsi(string $carve): string
    {
        return $this->converter(new AnsiRenderer())->convert($carve);
    }

    public function parse(string $carve): Document
    {
        return $this->htmlConverter()->parse($carve);
    }

    public function renderFile(string $path): string
    {
        return $this->renderFileWithReport($path)['value'];
    }

    /**
     * Render a file as `html`, `text`, `markdown` or `ansi`, expanding
     * includes when an include root is configured.
     *
     * @throws \InvalidArgumentException
     */
    public function renderFileAs(string $path, string $format = 'html'): string
    {
        $renderer = match ($format) {
            'html' => null,
            'text' => new PlainTextRenderer(),
            'markdown' => new MarkdownRenderer(),
            'ansi' => new AnsiRenderer(),
            default => throw new InvalidArgumentException(sprintf('Unknown Carve output format "%s". Use html, text, markdown or ansi.', $format)),
        };
        if ($renderer === null) {
            return $this->renderFile($path);
        }
        [$sourcePath, $source] = $this->readFile($path);
        $converter = $this->converter($renderer);
        if ($this->includeResolver === null) {
            return $converter->convert($source);
        }

        return $converter->render($this->expand($converter, $sourcePath, $source)->document);
    }

    /**
     * @return array{
     *   value: string,
     *   warnings: list<array{rule: string|null, message: string, file: string|null, line: int, column: int}>,
     *   dependencies: list<array{path: string, resolved: bool}>,
     *   suppressedWarnings: int
     * }
     */
    public function renderFileWithReport(string $path): array
    {
        [$sourcePath, $source] = $this->readFile($path);
        if ($this->includeRootReal === null || $this->includeResolver === null) {
            return ['value' => $this->render($source), 'warnings' => [], 'dependencies' => [], 'suppressedWarnings' => 0];
        }

        $key = hash('xxh3', serialize([$sourcePath, hash('xxh3', $source)]));
        $compute = function () use ($sourcePath, $source): array {
            $converter = $this->htmlConverter();
            $expansion = $this->expand($converter, $sourcePath, $source);
            $report = [
                'value' => $converter->render($expansion->document),
                'warnings' => $expansion->warnings,
                'dependencies' => $expansion->dependencies,
                'suppressedWarnings' => $expansion->suppressedWarnings,
            ];

            return ['report' => $report, 'states' => $this->dependencyStates($expansion->targets)];
        };

        /** @var array{report: array{value: string, warnings: list<array{rule: string|null, message: string, file: string|null, line: int, column: int}>, dependencies: list<array{path: string, resolved: bool}>, suppressedWarnings: int}, states: array<string, string|null>} $entry */
        $entry = $this->cached('file', $key, $compute);
        if ($this->activeCache !== null && $entry['states'] !== $this->dependencyStates(array_keys($entry['states']))) {
            $this->activeCache->delete($this->cacheKey('file', $key));
            /** @var array{report: array{value: string, warnings: list<array{rule: string|null, message: string, file: string|null, line: int, column: int}>, dependencies: list<array{path: string, resolved: bool}>, suppressedWarnings: int}, states: array<string, string|null>} $entry */
            $entry = $this->cached('file', $key, $compute);
        }

        foreach ($entry['report']['warnings'] as $warning) {
            $this->logger?->warning('Carve include warning: {message}', $warning);
        }

        return $entry['report'];
    }

    /**
     * @template T
     *
     * @param string $kind
     * @param string $key
     * @param \Closure(): T $compute
     *
     * @return T
     */
    private function cached(string $kind, string $key, Closure $compute): mixed
    {
        if ($this->activeCache === null) {
            return $compute();
        }

        return $this->activeCache->get($this->cacheKey($kind, $key), function (ItemInterface $item) use ($compute): mixed {
            if ($this->cacheTtl !== null) {
                $item->expiresAfter($this->cacheTtl);
            }

            return $compute();
        });
    }

    private function cacheKey(string $kind, string $key): string
    {
        return $this->cachePrefix . '.' . $kind . '.' . $this->cacheSignature . '.' . $key;
    }

    /**
     * @throws \InvalidArgumentException
     *
     * @return array{string, string}
     */
    private function readFile(string $path): array
    {
        $sourcePath = realpath($path);
        if ($sourcePath === false || !is_file($sourcePath)) {
            throw new InvalidArgumentException(sprintf('Carve source is not a readable file: %s', $path));
        }
        $source = @file_get_contents($sourcePath);
        if ($source === false) {
            throw new InvalidArgumentException(sprintf('Carve source is not readable: %s', $path));
        }
        $root = $this->includeRootReal;
        if ($root !== null && $sourcePath !== $root && !str_starts_with($sourcePath, $root . DIRECTORY_SEPARATOR)) {
            throw new InvalidArgumentException('Carve source must be inside carve.include_root.');
        }

        return [$sourcePath, $source];
    }

    private function expand(CarveConverter $converter, string $sourcePath, string $source): IncludeExpansion
    {
        $expander = new IncludeExpander(
            resolver: $this->includeResolver,
            currentPath: $sourcePath,
            source: $source,
            extensions: $converter->getExtensions(),
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
        $targets = array_map(static fn ($dependency): string => $dependency->getTarget(), $expander->getDependencies());

        return new IncludeExpansion($document, $warnings, $dependencies, $targets, $expander->getSuppressedWarnings());
    }

    /**
     * Content hash per include target, or null where the target is absent or
     * outside the root. A missing target keeps an entry on purpose: creating
     * the file is what makes the directive start working, so it has to
     * invalidate the cached render.
     *
     * @param array<string> $targets
     *
     * @return array<string, string|null>
     */
    private function dependencyStates(array $targets): array
    {
        $root = (string)$this->includeRootReal;
        $states = [];
        foreach ($targets as $target) {
            $contained = $this->containedIdentity($target);
            $candidate = $contained === null || $contained === self::OUTSIDE_ROOT
                ? null
                : $root . DIRECTORY_SEPARATOR . $contained;
            $states[$target] = $candidate !== null && is_file($candidate)
                ? (hash_file('xxh3', $candidate) ?: null)
                : null;
        }

        return $states;
    }

    private function result(string $source, CarveConverter $converter, Document $document): RenderedCarve
    {
        $html = $converter->render($document);
        $toc = [];
        $frontmatter = null;
        foreach ($converter->getExtensions() as $extension) {
            if ($extension instanceof TableOfContentsExtension) {
                $toc = $extension->getToc();
            }
            if ($extension instanceof FrontmatterExtension && $extension->hasFrontmatter()) {
                $frontmatter = ['format' => (string)$extension->getFormat(), 'content' => (string)$extension->getContent()];
            }
        }

        return new RenderedCarve($source, $html, $toc, $frontmatter);
    }

    private function htmlConverter(bool $withToc = false): CarveConverter
    {
        $converter = $this->converter(null, $this->resolveSafeMode());
        if ($withToc && !array_filter($converter->getExtensions(), static fn ($extension): bool => $extension instanceof TableOfContentsExtension)) {
            $converter->addExtension(new TableOfContentsExtension());
        }

        return $converter;
    }

    /**
     * Safe mode only applies to HTML; the other output targets share every
     * other option.
     */
    private function converter(?RendererInterface $renderer, SafeMode|bool|null $safeMode = null): CarveConverter
    {
        $converter = new CarveConverter(
            xhtml: $this->xhtml,
            safeMode: $safeMode,
            profile: $this->resolveProfile(),
            softBreakMode: $this->softBreakMode !== null ? SoftBreakMode::from($this->softBreakMode) : null,
            smartTypography: $this->smartTypography,
            mode: $this->mode,
            symbols: $this->symbols,
            labels: $this->labels,
            // Non-HTML targets track positions, as carve-php's own plainText()
            // and markdown() factories do.
            parser: $renderer === null ? null : new BlockParser(false, false, $this->sourceLines, true),
            renderer: $renderer,
            sourceLines: $this->sourceLines,
        );

        if ($this->diagrams !== []) {
            $factories = self::diagramPresetFactories();
            foreach ($this->diagrams as $name) {
                if (isset($factories[$name])) {
                    $converter->addExtension($factories[$name]());
                }
            }
        }
        foreach ($this->extensions as $extension) {
            $converter->addExtension($this->freshExtension($extension));
        }

        return $converter;
    }

    /**
     * @param \MarkupCarve\Carve\Extension\ExtensionInterface|array<string, mixed>|string $entry
     */
    private function freshExtension(ExtensionInterface|array|string $entry): ExtensionInterface
    {
        return $this->extensionFactory->create($entry);
    }

    /**
     * @throws \InvalidArgumentException
     */
    private function resolveSafeMode(): SafeMode|bool
    {
        return match ($this->safeMode) {
            'strict' => SafeMode::strict(),
            true, 'true', '1' => SafeMode::defaults()->setRawHtmlMode($this->rawHtmlMode),
            false, 'false', '0' => false,
            default => $this->safeMode instanceof SafeMode
                ? $this->safeMode
                : throw new InvalidArgumentException('carve.safe_mode must be true, false or "strict".'),
        };
    }

    /**
     * @throws \InvalidArgumentException
     */
    private function resolveProfile(): ?Profile
    {
        if ($this->profile === null) {
            return null;
        }
        $profile = match ($this->profile) {
            'full' => Profile::full(),
            'article' => Profile::article(),
            'comment' => Profile::comment(),
            'minimal' => Profile::minimal(),
            default => throw new InvalidArgumentException(sprintf('Unknown Carve profile "%s".', $this->profile)),
        };
        if ($this->onDisallowed === null) {
            return $profile;
        }
        if (!in_array($this->onDisallowed, [Profile::ACTION_STRIP, Profile::ACTION_TO_TEXT, Profile::ACTION_ERROR], true)) {
            throw new InvalidArgumentException(sprintf('Unknown Carve on_disallowed action "%s".', $this->onDisallowed));
        }

        return $profile->onDisallowed($this->onDisallowed);
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
            'plantuml' => static fn (): FencedRenderExtension => FencedRenderExtension::plantuml(),
        ];
    }
}
