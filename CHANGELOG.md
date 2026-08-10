# Changelog

## Unreleased

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
