# Changelog

## Unreleased

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
