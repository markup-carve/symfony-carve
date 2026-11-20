<?php

declare(strict_types=1);

namespace MarkupCarve\SymfonyCarve;

use Closure;
use InvalidArgumentException;
use MarkupCarve\Carve\Attrs;
use MarkupCarve\Carve\Carve;
use MarkupCarve\Carve\CarveException;
use MarkupCarve\Carve\Diagnostics\Diagnostic;
use MarkupCarve\Carve\Extension\Bundle;
use MarkupCarve\Carve\Extension\DocumentTransform;
use MarkupCarve\Carve\Extension\ExtensionDefinition;
use MarkupCarve\Carve\Extension\ExtensionProvider;
use MarkupCarve\Carve\Include\FileResolver;
use MarkupCarve\Carve\Include\IncludeResolver;
use MarkupCarve\Carve\Node;
use MarkupCarve\Carve\Output\WriterRegistry;
use MarkupCarve\Carve\RenderError;
use MarkupCarve\Carve\RenderResult;
use MarkupCarve\SymfonyCarve\Extension\ExtensionFactory;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Throwable;

final class CarveRenderer
{
    /**
     * @var string
     */
    public const OUTSIDE_ROOT = '[outside-root]';

    /**
     * @var array<string, string>
     */
    public const OPTIONS = [
        'raw_html' => 'rawHtml',
        'url_policy' => 'urlPolicy',
        'profile' => 'profile',
        'preset' => 'profile',
        'mode' => 'mode',
        'sections' => 'sections',
        'heading_ids' => 'headingIds',
        'source_lines' => 'sourceLines',
        'smart_typography' => 'smartTypography',
        'soft_break_mode' => 'softBreak',
    ];

    private readonly ?IncludeResolver $includeResolver;

    private readonly ?string $includeRootReal;

    private readonly ?CacheInterface $activeCache;

    private readonly string $cacheSignature;

    /**
     * @var array<string, mixed>
     */
    private readonly array $arguments;

    /**
     * @var array<string, mixed>
     */
    private readonly array $engineOptions;

    /**
     * @param bool $rawHtml
     * @param array<string, mixed> $urlPolicy
     * @param string|null $profile
     * @param string|null $includeRoot
     * @param \Psr\Log\LoggerInterface|null $logger
     * @param string $mode
     * @param bool $sections
     * @param string $headingIds
     * @param array<string, string> $symbols
     * @param bool $sourceLines
     * @param bool $smartTypography
     * @param array<string, string> $labels
     * @param array<mixed> $extensions
     * @param array<string> $diagrams
     * @param \MarkupCarve\Carve\Output\WriterRegistry|null $writers
     * @param \Symfony\Contracts\Cache\CacheInterface|null $cache
     * @param int|null $cacheTtl
     * @param string $cachePrefix
     * @param string|null $cacheVersion
     * @param string $softBreak
     */
    public function __construct(
        private readonly bool $rawHtml = false,
        private readonly array $urlPolicy = [],
        private readonly ?string $profile = 'full',
        private readonly ?string $includeRoot = null,
        private readonly ?LoggerInterface $logger = null,
        private readonly string $mode = 'interactive',
        private readonly bool $sections = true,
        private readonly string $headingIds = 'keep-case',
        private readonly array $symbols = [],
        private readonly bool $sourceLines = false,
        private readonly bool $smartTypography = true,
        private readonly array $labels = [],
        private readonly array $extensions = [],
        private readonly array $diagrams = [],
        private readonly ?WriterRegistry $writers = null,
        private readonly ?CacheInterface $cache = null,
        private readonly ?int $cacheTtl = null,
        private readonly string $cachePrefix = 'carve',
        private readonly ?string $cacheVersion = null,
        private readonly string $softBreak = 'newline',
    ) {
        $this->arguments = get_defined_vars();
        if (preg_match('/[{}()\/\\\\@:]/', $cachePrefix) === 1) {
            throw new InvalidArgumentException('carve.cache.prefix contains a reserved character.');
        }
        if ($includeRoot === null) {
            $this->includeResolver = null;
            $this->includeRootReal = null;
        } else {
            try {
                $this->includeResolver = FileResolver::forRoot($includeRoot);
            } catch (Throwable $exception) {
                throw new InvalidArgumentException('carve.include_root: ' . $exception->getMessage(), 0, $exception);
            }
            $this->includeRootReal = rtrim((string)realpath($includeRoot), DIRECTORY_SEPARATOR);
        }
        foreach ($diagrams as $name) {
            if (!in_array($name, ExtensionFactory::TYPES, true)) {
                throw new InvalidArgumentException('Unknown Carve diagram: ' . $name);
            }
        }
        $diagramNames = $diagrams;
        $definitions = (new ExtensionFactory())->createAll(array_merge($extensions, $diagramNames));
        $engineOptions = [
            'rawHtml' => $rawHtml,
            'profile' => $profile ?? 'full',
            'mode' => $mode,
            'sections' => $sections,
            'headingIds' => $headingIds,
            'symbols' => $symbols,
            'sourceLines' => $sourceLines,
            'smartTypography' => $smartTypography,
            'softBreak' => $softBreak,
            'labels' => $labels,
            'extensions' => $definitions,
        ];
        if ($urlPolicy !== []) {
            $engineOptions['urlPolicy'] = $urlPolicy;
        }
        // Validate every value now, including those not used by a particular call.
        try {
            $this->renderEngine('', $engineOptions);
        } catch (CarveException $exception) {
            throw new InvalidArgumentException($exception->getMessage(), 0, $exception);
        }
        $this->engineOptions = $engineOptions;
        $cacheable = true;
        try {
            $extensionState = serialize([$extensions, $writers]);
        } catch (Throwable) {
            $extensionState = array_map(static fn (mixed $entry): string => is_object($entry) ? $entry::class : serialize($entry), $extensions);
            $cacheable = $cacheVersion !== null;
        }
        $this->activeCache = $cacheable ? $cache : null;
        $this->cacheSignature = hash('xxh3', serialize([
            'carve-php-next2-14a3fab', $rawHtml, $urlPolicy, $profile, $includeRoot, $mode, $sections,
            $headingIds, $symbols, $sourceLines, $smartTypography, $softBreak, $labels, $diagrams, $extensionState, $cacheVersion,
        ]));
    }

    /**
     * @param array<string, mixed> $options
     */
    public function withOptions(array $options): self
    {
        if ($options === []) {
            return $this;
        }
        $arguments = $this->arguments;
        foreach ($options as $key => $value) {
            if (!isset(self::OPTIONS[$key])) {
                throw new InvalidArgumentException(sprintf('Unknown Carve render option "%s".', $key));
            }
            $arguments[self::OPTIONS[$key]] = $value;
        }

        try {
            return new self(...$arguments);
        } catch (Throwable $exception) {
            throw new InvalidArgumentException('Invalid Carve render options: ' . $exception->getMessage(), 0, $exception);
        }
    }

    public function getIncludeRoot(): ?string
    {
        return $this->includeRootReal;
    }

    public function render(string $carve): string
    {
        return $this->cached('render', hash('xxh3', $carve), fn (): string => $this->renderEngine($carve, $this->engineOptions));
    }

    public function renderResult(string $carve): RenderedCarve
    {
        return $this->cached('result', hash('xxh3', $carve), function () use ($carve): RenderedCarve {
            $tree = $this->parse($carve);
            $markerName = 'data-carve-host-toc-' . bin2hex(random_bytes(8));
            $renderedTree = null;
            $capture = static function (Node $view) use (&$renderedTree, $markerName): Node {
                $index = 0;
                self::markHeadings($view, $index, $markerName);
                $renderedTree = $view;

                return $view;
            };
            $html = $this->renderEngineResult($tree, $this->engineOptions, $capture)->html;
            $toc = [];
            $frontmatter = null;
            $this->collectMetadata($renderedTree ?? $tree, $toc, $frontmatter);
            $shapes = self::markedHeadingShapes($html, $markerName, $this->sections);
            foreach ($toc as $index => &$heading) {
                if (isset($shapes[$index])) {
                    $heading['level'] = $shapes[$index]['level'];
                    $heading['id'] = $shapes[$index]['id'] ?? '';
                }
            }
            unset($heading);
            $html = self::stripHeadingMarkers($html, $markerName);

            return new RenderedCarve($carve, $html, $toc, $frontmatter);
        });
    }

    public function renderText(string $carve): string
    {
        return $this->writeTarget('text', $carve);
    }

    public function renderMarkdown(string $carve): string
    {
        return $this->writeTarget('markdown', $carve);
    }

    public function renderAnsi(string $carve): string
    {
        return $this->writeTarget('ansi', $carve);
    }

    public function parse(string $carve): Node
    {
        return Carve::parse($carve, $this->parseOptions());
    }

    public function renderFile(string $path): string
    {
        return $this->renderFileWithReport($path)['value'];
    }

    public function renderFileAs(string $path, string $format = 'html'): string
    {
        if (!in_array($format, ['html', 'text', 'markdown', 'ansi'], true)) {
            throw new InvalidArgumentException(sprintf('Unknown Carve output format "%s". Use html, text, markdown or ansi.', $format));
        }
        if ($format === 'html') {
            return $this->renderFile($path);
        }
        [$sourcePath, $source] = $this->readFile($path);

        return $this->writeTarget($format, $source, $sourcePath);
    }

    /**
     * @return array{value: string, warnings: array<mixed>, dependencies: array<mixed>, suppressedWarnings: int}
     */
    public function renderFileWithReport(string $path): array
    {
        [$sourcePath, $source] = $this->readFile($path);
        if ($this->includeRootReal === null) {
            return ['value' => $this->render($source), 'warnings' => [], 'dependencies' => [], 'suppressedWarnings' => 0];
        }
        $key = hash('xxh3', serialize([$sourcePath, hash('xxh3', $source)]));
        $compute = function () use ($sourcePath, $source): array {
            $options = $this->engineOptions + ['includes' => ['resolver' => $this->includeResolver, 'file' => $sourcePath]];
            $result = $this->renderEngineResult($source, $options);
            $deps = Carve::includeDependencies($source, $this->parseOptions() + ['includes' => ['resolver' => $this->includeResolver, 'file' => $sourcePath]]);
            $warnings = [];
            foreach ($result->diagnostics as $diagnostic) {
                if ($diagnostic->code === 'include-unresolved') {
                    $warnings[] = $this->warning($diagnostic, $sourcePath, $source);
                }
            }
            $dependencies = [];
            $targets = [];
            foreach ($deps as $dependency) {
                $target = $dependency['file'] ?? $this->missingTarget($dependency);
                $targets[] = $target;
                $dependencies[] = ['path' => $this->containedIdentity($target) ?? self::OUTSIDE_ROOT, 'resolved' => $dependency['read']];
            }
            $report = ['value' => $result->html, 'warnings' => $warnings, 'dependencies' => $dependencies, 'suppressedWarnings' => 0];

            return ['report' => $report, 'states' => $this->dependencyStates($targets)];
        };
        $entry = $this->cached('file', $key, $compute);
        if ($this->activeCache !== null && $entry['states'] !== $this->dependencyStates(array_keys($entry['states']))) {
            $this->activeCache->delete($this->cacheKey('file', $key));
            $entry = $this->cached('file', $key, $compute);
        }
        foreach ($entry['report']['warnings'] as $warning) {
            $this->logger?->warning('Carve include warning: {message}', $warning);
        }

        return $entry['report'];
    }

    /**
     * @param array<string, mixed> $dependency
     */
    private function missingTarget(array $dependency): string
    {
        $from = $dependency['from'] ?? null;
        $path = (string)$dependency['path'];
        if (str_starts_with($path, '/')) {
            return $path;
        }
        $base = is_string($from) ? dirname($from) : (string)$this->includeRootReal;

        return $base . DIRECTORY_SEPARATOR . $path;
    }

    /**
     * @return array{rule: string, message: string, file: string|null, line: int, column: int}
     */
    private function warning(Diagnostic $diagnostic, string $sourcePath, string $source): array
    {
        $file = $diagnostic->file ?? $sourcePath;
        $content = $file === $sourcePath ? $source : @file_get_contents((string)$this->includeRootReal . '/' . $file);
        $prefix = is_string($content) && $diagnostic->startOffset >= 0 ? substr($content, 0, $diagnostic->startOffset) : '';
        $line = substr_count($prefix, "\n") + 1;
        $last = strrpos($prefix, "\n");
        $column = strlen($last === false ? $prefix : substr($prefix, $last + 1)) + 1;

        return [
            'rule' => $diagnostic->code,
            'message' => $diagnostic->message,
            'file' => $this->containedIdentity($file),
            'line' => $line,
            'column' => $column,
        ];
    }

    private function writeTarget(string $target, string $source, ?string $path = null): string
    {
        $registry = $this->writers ?? Carve::writers();
        $writerTarget = $target === 'text' && !$registry->has('text') ? 'plain' : $target;
        if (!$registry->has($writerTarget)) {
            throw new RuntimeException(sprintf('Carve %s writer not available yet.', $target));
        }
        $options = $this->parseOptions();
        if ($path !== null && $this->includeResolver !== null) {
            $options['includes'] = ['resolver' => $this->includeResolver, 'file' => $path];
        }

        $writerOptions = $writerTarget === $target && !in_array($target, ['markdown', 'plain', 'ansi'], true)
            ? $this->engineOptions
            : array_intersect_key($this->engineOptions, array_flip(['smartTypography', 'urlPolicy', 'strictLosses']));

        return Carve::writeTo($writerTarget, Carve::parse($source, $options), $writerOptions, $registry);
    }

    /**
     * @param \MarkupCarve\Carve\Node|string $source
     * @param array<string, mixed> $options
     */
    private function renderEngine(Node|string $source, array $options): string
    {
        return $this->renderEngineResult($source, $options)->html;
    }

    /**
     * @param \MarkupCarve\Carve\Node|string $source
     * @param array<string, mixed> $options
     * @param \Closure|null $capture
     *
     * @throws \MarkupCarve\Carve\RenderError
     */
    private function renderEngineResult(Node|string $source, array $options, ?Closure $capture = null): RenderResult
    {
        [$options['extensions'], $transforms] = self::orderedExtensions($options['extensions']);
        if ($capture !== null) {
            array_push($transforms, new DocumentTransform('symfony.metadata', $capture));
        }

        $result = Carve::renderWithDocumentTransforms($source, $transforms, $options);
        if (($options['strictLosses'] ?? false) === true) {
            $losses = array_values(array_filter($result->diagnostics, static fn (Diagnostic $d): bool => $d->channel === 'render-loss'));
            if ($losses !== []) {
                throw new RenderError($losses);
            }
        }

        return $result;
    }

    /**
     * @param array<mixed> $extensions
     *
     * @return array{array<\MarkupCarve\Carve\Extension\ExtensionDefinition>, array<\MarkupCarve\Carve\Extension\DocumentTransform>}
     */
    private static function orderedExtensions(array $extensions): array
    {
        $definitions = [];
        $transforms = [];
        foreach ($extensions as $extension) {
            $entries = $extension instanceof ExtensionProvider ? $extension->definitions() : [$extension];
            foreach ($entries as $entry) {
                if (!$entry instanceof ExtensionDefinition) {
                    continue;
                }
                if ($entry->documentTransform !== null) {
                    $transforms[] = new DocumentTransform($entry->name, $entry->documentTransform);
                    $entry = new ExtensionDefinition($entry->name, $entry->version, $entry->slots, $entry->matcher, $entry->parent, $entry->transform, $entry->renderer, $entry->buildTimeRenderer, $entry->semanticKeys, $entry->quotes);
                }
                $definitions[] = $entry;
            }
            if ($extension instanceof Bundle) {
                array_push($transforms, ...$extension->transforms());
            }
        }

        return [$definitions, $transforms];
    }

    /**
     * @return array<string, mixed>
     */
    private function parseOptions(): array
    {
        return ['extensions' => $this->engineOptions['extensions'], 'headingIds' => $this->headingIds];
    }

    /**
     * @param \MarkupCarve\Carve\Node $node
     * @param list<array{level: int, text: string, html: string, id: string}> $toc
     * @param array{format: string, content: string}|null $frontmatter
     */
    private function collectMetadata(Node $node, array &$toc, ?array &$frontmatter): void
    {
        if ($node->type === 'frontmatter') {
            $frontmatter = ['format' => (string)$node->f['format'], 'content' => (string)$node->f['value']];
        }
        if ($node->type === 'heading') {
            $text = $this->plainText($node);
            $toc[] = ['level' => (int)$node->f['level'], 'text' => $text, 'html' => htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), 'id' => (string)($node->f['generatedId'] ?? $node->f['id'] ?? '')];
        }
        foreach ($node->children ?? [] as $child) {
            $this->collectMetadata($child, $toc, $frontmatter);
        }
    }

    private function plainText(Node $node): string
    {
        $data = $node->f['data'] ?? null;
        if (is_array($data) && ($data['carve.headingPermalinks'] ?? null) === 'chrome') {
            return '';
        }
        $text = (string)($node->f['value'] ?? '');
        foreach ($node->children ?? [] as $child) {
            $text .= $this->plainText($child);
        }

        return $text;
    }

    private static function markHeadings(Node $node, int &$index, string $markerName): void
    {
        if ($node->type === 'heading') {
            $attrs = $node->f['attrs'] ?? null;
            $attrs = $attrs instanceof Attrs ? clone $attrs : new Attrs();
            $attrs->setKey($markerName, (string)$index++);
            $node->f['attrs'] = $attrs;
        }
        foreach ($node->children ?? [] as $child) {
            self::markHeadings($child, $index, $markerName);
        }
    }

    /**
     * @return array<int, array{level: int, id: string|null}>
     */
    private static function markedHeadingShapes(string $html, string $markerName, bool $sectionsEnabled): array
    {
        preg_match_all('/<\/?section\b[^>]*>|<h[1-6]\b[^>]*>/i', $html, $matches);
        $sections = [];
        $shapes = [];
        foreach ($matches[0] as $tag) {
            if (str_starts_with($tag, '</section')) {
                array_pop($sections);
            } elseif (str_starts_with($tag, '<section')) {
                $sections[] = self::tagId($tag);
            } elseif (preg_match('/^<h([1-6])\b/i', $tag, $level) === 1 && preg_match('/\s' . preg_quote($markerName, '/') . '="(\d+)"/', $tag, $marker) === 1) {
                $id = self::tagId($tag);
                if ($id === null && $sectionsEnabled && $sections !== []) {
                    $id = $sections[array_key_last($sections)];
                }
                $shapes[(int)$marker[1]] = ['level' => (int)$level[1], 'id' => $id];
            }
        }

        return $shapes;
    }

    private static function stripHeadingMarkers(string $html, string $markerName): string
    {
        return preg_replace_callback('/<h[1-6]\b[^>]*>/i', static fn (array $match): string => preg_replace('/\s' . preg_quote($markerName, '/') . '="\d+"/', '', $match[0]) ?? $match[0], $html) ?? $html;
    }

    private static function tagId(string $tag): ?string
    {
        return preg_match('/(?:^|\s)id="([^"]*)"/i', $tag, $match) === 1
            ? html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8') : null;
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

    /**
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
            $candidate = $contained === null || $contained === self::OUTSIDE_ROOT ? null : $root . DIRECTORY_SEPARATOR . $contained;
            $resolved = $candidate === null ? false : realpath($candidate);
            $inside = $resolved !== false && ($resolved === $root || str_starts_with($resolved, $root . DIRECTORY_SEPARATOR));
            $states[$target] = $inside && is_file($resolved) ? (hash_file('xxh3', $resolved) ?: null) : null;
        }

        return $states;
    }

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
}
