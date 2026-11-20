# Symfony Carve 0.2 guide

This bundle targets the Carve public API in the unreleased `next2` branch of carve-php. The engine is pinned by commit in Composer. Carve 0.1 remains available on this repository's `main` branch.

## Services and Twig

```php
use MarkupCarve\SymfonyCarve\CarveRenderer;
use MarkupCarve\SymfonyCarve\CarveTools;

$html = $renderer->render("= Hello\n");
$document = $renderer->parse("= Hello\n"); // Carve Node
$result = $renderer->renderResult("= Hello\n");
$result->toc();
$result->meta('title');

$canonical = $tools->format("# Hello\n"); // "= Hello\n"
$findings = $tools->lint("**bold**\n"); // LintFinding objects
$source = $tools->fromHtml('<p>Hello</p>');
```

The Twig `carve` filter and `carve()` function render HTML. `carve_document()` returns a `RenderedCarve` with HTML, heading data, and frontmatter. `carve_format()` and `carve_lint()` use the 0.2 formatter and lint catalog. `carve_file()` reads a file beneath the configured `include_root` and expands its includes. HTML output is a fragment without a final line feed.

The `carve_text` and `carve_markdown` Twig filters stay registered. They report `writer not available yet` until the corresponding Carve writer is registered.

## Configuration

```yaml
carve:
  raw_html: false
  url_policy: []
  profile: full
  mode: interactive
  sections: true
  heading_ids: keep-case
  source_lines: false
  smart_typography: true
  symbols: {}
  labels: {}
  extensions: []
  diagrams: []
  include_root: null
  cache:
    enabled: false
    pool: cache.app
    ttl: null
    prefix: carve
    version: null
```

`raw_html: false` is the secure default. Enabling it passes `rawHtml: true` to Carve and should be reserved for trusted input. An empty `url_policy` uses the engine's secure default. A policy map can supply `allow` or `deny` scheme lists. `profile` accepts `full`, `article`, `comment`, or `minimal`. `mode` accepts `interactive` or `static`. `heading_ids` accepts `off`, `keep-case`, `lower`, `ascii`, `ascii-lower`, `ascii-strict`, or `ascii-strict-lower`.

The 0.1 `safe_mode`, three-way `raw_html`, `on_disallowed`, `soft_break_mode`, and `xhtml` settings have no Carve equivalents. Use `raw_html`, `url_policy`, and `profile` for content policy. Per-call `withOptions()` accepts `raw_html`, `url_policy`, `profile` or `preset`, `mode`, `sections`, `heading_ids`, `source_lines`, and `smart_typography`.

`symbols` and `labels` are engine render maps. `source_lines` adds source line attributes. `sections` controls section wrappers around headings. `smart_typography` defaults to true. Cache keys include every render setting and a pinned engine identity. When a registered extension contains a closure, set `cache.version` to opt in to caching and change it whenever the extension changes.

## Includes and file rendering

`include_root` must be an absolute existing directory. The bundle passes Carve's `FileResolver` to rendering and reports dependencies and include warnings from the 0.2 diagnostic channel. It refuses source files outside that root. Missing or changed included files invalidate cached file renders.

```php
$renderer = new CarveRenderer(includeRoot: '/srv/docs');
$html = $renderer->renderFile('/srv/docs/index.crv');
$report = $renderer->renderFileWithReport('/srv/docs/index.crv');
```

String rendering does not read files. `renderFileAs()` accepts `html`, `text`, `markdown`, and `ansi`. The latter three targets use the Carve writer registry and currently report that their writer is unavailable. A host can pass a `WriterRegistry` to the renderer constructor to provide its own writer under one of those names.

## Extensions

Pass Carve `ExtensionDefinition` or `ExtensionProvider` objects in `extensions`. The bundle also accepts `tabs`, `mermaid`, and `plantuml` as shortcuts. `diagrams` accepts `mermaid`, `plantuml`, `d2`, `graphviz`, `wavedrom`, `vega_lite`, `chart`, and `abc`; unknown diagram names are ignored as before. The diagram shortcuts use 0.2 fence matchers and escape their body in the hydration element. Frontmatter, table of contents, autolinks, and smart typography are core 0.2 features and need no extension registration.

The `wikilinks` shortcut accepts `url_template` for explicit `:wiki[Page]` links. Other 0.1 extension classes do not run on the 0.2 path. The Carve document transform, heading permalink, and static extension renderer hooks are pending. Static rendering with registered extensions reports an availability error until D3 lands. See carve-php issues 5238, 5236, and 5245.

## Tools, validation, and commands

`CarveTools::format()` uses `Carve::format()`. `lint()` maps Carve diagnostics to `LintFinding` objects with `rule`, `message`, `file`, `line`, and `column`. `fromHtml()` uses the Carve HTML importer. `ValidCarve` uses Carve parse and render diagnostics for strict and profile validation; its `lint` flag calls the 0.2 lint catalog.

Commands remain `carve:render`, `carve:fmt`, `carve:lint`, `carve:convert`. `carve:convert` accepts `html`, `markdown`, `gfm` and `djot`. `markdown` and `gfm` both read GitHub Flavored Markdown, as the 0.1 command did, so tables, task lists and strikethrough convert; `CarveTools::fromMarkdown()` does the same.

The Markdown, text, and ANSI output writers are pending in carve-php issue 5235. The bundle keeps `renderMarkdown()`, `renderText()`, `renderAnsi()`, the Twig filters, and the command formats so callers can use them when writers arrive. Tests for those upstream gaps remain named skips.
