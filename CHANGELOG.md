# Changelog

## Unreleased

### Improvements

- Expose every render option in the bundle configuration: `safe_mode: strict`,
  `on_disallowed`, `mode`, `soft_break_mode`, `xhtml`, `source_lines`,
  `smart_typography`, `symbols` and `labels`, plus `CarveRenderer::withOptions()` for
  one rendering context.
- Configure carve-php extensions under `extensions`, by type name or with options,
  including a `url_template` for wiki links.
- Cache rendered HTML in a Symfony cache pool (`cache.enabled`), invalidated by option,
  engine version and, for file renders, by every include target.
- Add `CarveRenderer::renderResult()` with the table of contents and frontmatter, and
  `renderAnsi()`.
- Add a `CarveTools` service for `format()` (with `alignTables`), `lint()` (every
  `DocumentLinter` rule, optionally following includes) and Markdown, HTML and Djot
  import.
- Twig: render options on `carve`, plus `carve_format`, `carve_file`,
  `carve_document` and `carve_lint`.
- Add the console commands `carve:render`, `carve:lint` (with `--include-root`),
  `carve:fmt` (with `--write`, `--check` and `--align-tables`) and `carve:convert`.
- Add a `ValidCarve` validator constraint with `preset`, `strict`, `lint` and
  `maxLength` options (needs `symfony/validator`).
- Require carve-php `^0.1.14`.

## 0.1.5 - 2026-09-20

### Added

- Add opt-in file-backed rendering: an absolute `include_root`, plus
  `CarveRenderer::renderFile()` and `renderFileWithReport()`. The Twig filters
  and `render()` still take anonymous strings and never read files. A report
  carries the resolved and attempted include targets so an application can key
  its own cache on them, and warnings reaching the logger omit resolver
  details (#13)

### Changed

- Require carve-php `^0.1.9`, the first tag carrying the include pass the file
  API runs on, and add `psr/log` as a runtime requirement for the optional
  warning logger (#13)

## 0.1.4 - 2026-08-18

### Security

- Require carve-php `^0.1.5`, which probes **every** candidate in a list-valued
  URL attribute instead of trusting the value's leading scheme.
  `srcset="safe.png 1x, javascript:alert(1) 2x"` passed the probe on its second
  entry. Upgrade if you render untrusted Carve or import untrusted HTML.

### Changed

- The `carve_markdown` filter's output changes: carve-php 0.1.5 escapes `<` only
  where it would open markup and leaves a bare ampersand alone, so `<tag>` now
  renders as `\<tag>` rather than as an HTML entity. A list-table header cell
  also carries `scope` now.

### Added

- Add rendering profiles and plain-text and Markdown service/Twig output targets.

## 0.1.3 - 2026-08-10

- Require carve-php `^0.1.4`, the current security and parser/writer
  convergence release.
- Move CI and drift workflows to the current GitHub Actions runtime.

## 0.1.2 - 2026-07-27

- Add a `diagrams` config option for FencedRender presets (e.g. plantuml) on the bundle and renderer.
- Require `markup-carve/carve-php` ^0.1.3 (the cross-engine convergence release: strict column-0, unresolved footnote-ref, tight-item trailing text, list looseness).

## 0.1.1 - 2026-07-09

- BREAKING: namespace renamed from `MarkupCarve\Symfony` to
  `MarkupCarve\SymfonyCarve`, aligning framework packages on repo-name
  symmetry (laravel-carve ships `MarkupCarve\LaravelCarve`). Update
  imports and the bundle entry in `config/bundles.php`.

## 0.1.0 - 2026-07-09

Initial tagged release: `CarveRenderer` service, `carve` Twig filter and
`carve()` function, configurable safe mode.
