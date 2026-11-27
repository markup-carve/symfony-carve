# Changelog

## Unreleased

## 0.2.0 - 2026-11-27

### Breaking

- Port the bundle to Carve 0.2 (carve-php `main`). Engine imports are `MarkupCarve\Carve\Carve`; the temporary `Carve2` names and 0.1 code paths are gone.
- Replace the 0.1 safety and render settings with `raw_html`, `url_policy`, `sections`, and `heading_ids`. Raw HTML remains disabled by default.
- Use the 0.2 formatter, lint diagnostics, HTML importer, tree, and include resolver. The 0.2 formatter writes `=` headings and 0.2 table syntax.
- Unknown names in `diagrams` are rejected instead of ignored (#39).
- Remove the decision trace service and `carve:trace` command; the 0.2 engine does not provide this API.

### Fixes

- Key the render cache on the installed carve-php commit, so an engine upgrade no longer serves HTML from the previous engine (#43).

### Improvements

- Text, Markdown, and ANSI output and the `carve_text` and `carve_markdown` Twig filters use the engine's built-in writers (#37, #39).
- Static rendering works with registered extensions (#37).
- `fromMarkdown()` and `carve:convert` read GitHub Flavored Markdown, and accept `gfm` as a name for the same import; Djot import works too (#39, #41).
- Named 0.2 extension bundles such as `table_of_contents`, `autolink`, and `smart_quotes` register through the engine, with snake case factory options (#39).
- Port tab and diagram registration to 0.2 extension definitions; `plantuml` also matches `puml` fences and `graphviz` matches `dot` (#39).
- `soft_break_mode` maps to the engine `softBreak` option again, also per call through `withOptions()` (#39).
- Extract heading and frontmatter data from the 0.2 tree.

## 0.1.7 - 2026-11-12

### Breaking

- HTML from `render()`, `renderResult()`, `renderFile()` and `renderFileWithReport()`
  no longer ends with a final line feed, as the Carve HTML API specifies (#31)
- HTML import in `CarveTools` and `carve:convert` no longer reads `data-djot-*`
  attributes (markup-carve/carve#5735)
- The lint rule alias `a11y/heading-jump` is gone; use `heading-level-skip`

### Improvements

- Add `carve:trace` and `CarveTools::trace()`, which print carve-php's decision
  trace as JSON lines (#31)
- `format()`, `carve_format` and `carve:fmt` write `|=` header cells, `***` thematic
  breaks and braced table alignment (`|={>}`); the old `|=>` spelling is still read
  and is reported by the `deprecated-cell-alignment` lint rule (#31)
- Require carve-php `^0.1.17` (#31)

## 0.1.6 - 2026-10-23

### Improvements

- Expose every render option in the bundle configuration: `safe_mode: strict`,
  `on_disallowed`, `mode`, `soft_break_mode`, `xhtml`, `source_lines`,
  `smart_typography`, `symbols` and `labels`, plus `CarveRenderer::withOptions()` for
  one rendering context (#25)
- Configure carve-php extensions under `extensions`, by type name or with options,
  including a `url_template` for wiki links (#25)
- Cache rendered HTML in a Symfony cache pool (`cache.enabled`), invalidated by option,
  engine version and, for file renders, by every include target (#25)
- Add `CarveRenderer::renderResult()` with the table of contents and frontmatter, and
  `renderAnsi()` (#25)
- Add a `CarveTools` service for `format()` (with `alignTables`), `lint()` (every
  `DocumentLinter` rule, optionally following includes) and Markdown, HTML and Djot
  import (#25)
- Twig: render options on `carve`, plus `carve_format`, `carve_file`,
  `carve_document` and `carve_lint` (#25)
- Add the console commands `carve:render`, `carve:lint` (with `--include-root`),
  `carve:fmt` (with `--write`, `--check` and `--align-tables`) and `carve:convert` (#26)
- Add a `ValidCarve` validator constraint with `preset`, `strict`, `lint` and
  `maxLength` options (needs `symfony/validator`) (#26)
- Require carve-php `^0.1.14`, plus `symfony/console` and `symfony/cache-contracts` (#25, #26)

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
