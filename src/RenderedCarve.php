<?php

declare(strict_types=1);

namespace MarkupCarve\SymfonyCarve;

use JsonSerializable;
use Stringable;
use Symfony\Component\Yaml\Yaml;
use Throwable;

/**
 * A rendered document: the HTML plus the table of contents and frontmatter
 * collected while rendering it. Casting it to a string gives the HTML.
 */
final class RenderedCarve implements JsonSerializable, Stringable
{
    /**
     * @param string $source
     * @param string $html
     * @param list<array{level: int, text: string, html: string, id: string}> $toc
     * @param array{format: string, content: string}|null $frontmatter
     */
    public function __construct(
        public readonly string $source,
        public readonly string $html,
        public readonly array $toc = [],
        public readonly ?array $frontmatter = null,
    ) {
    }

    /**
     * @return list<array{level: int, text: string, html: string, id: string}>
     */
    public function toc(): array
    {
        return $this->toc;
    }

    public function hasFrontmatter(): bool
    {
        return $this->frontmatter !== null;
    }

    /**
     * Frontmatter parsed into an array, or one top-level key of it. JSON is
     * always supported; YAML needs symfony/yaml. Unsupported or invalid
     * content gives an empty array.
     */
    public function meta(?string $key = null, mixed $default = null): mixed
    {
        $meta = $this->parseFrontmatter();
        if ($key === null) {
            return $meta;
        }

        return array_key_exists($key, $meta) ? $meta[$key] : $default;
    }

    /**
     * @return array{source: string, html: string, toc: list<array{level: int, text: string, html: string, id: string}>, frontmatter: array{format: string, content: string}|null}
     */
    public function toArray(): array
    {
        return [
            'source' => $this->source,
            'html' => $this->html,
            'toc' => $this->toc,
            'frontmatter' => $this->frontmatter,
        ];
    }

    /**
     * @return array{source: string, html: string, toc: list<array{level: int, text: string, html: string, id: string}>, frontmatter: array{format: string, content: string}|null}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function __toString(): string
    {
        return $this->html;
    }

    /**
     * @return array<mixed>
     */
    private function parseFrontmatter(): array
    {
        if ($this->frontmatter === null) {
            return [];
        }
        ['format' => $format, 'content' => $content] = $this->frontmatter;

        try {
            $parsed = match ($format) {
                'json' => json_decode($content, true, flags: JSON_THROW_ON_ERROR),
                'yaml', 'yml' => class_exists(Yaml::class) ? Yaml::parse($content) : null,
                default => null,
            };
        } catch (Throwable) {
            return [];
        }

        return is_array($parsed) ? $parsed : [];
    }
}
