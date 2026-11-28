# Configuration

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
  renderers: {}
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

The 0.1 `safe_mode`, three-way `raw_html`, `on_disallowed`, and `xhtml` settings have no Carve equivalents. Use `raw_html`, `url_policy`, and `profile` for content policy. `profile: null` selects the full profile. `soft_break_mode` maps to the engine `softBreak` option. Per-call `withOptions()` accepts `raw_html`, `url_policy`, `profile` or `preset`, `mode`, `sections`, `heading_ids`, `source_lines`, `smart_typography`, `soft_break_mode`, and `renderers`.

`renderers` maps extension names to host renderers or `standard`. The bundle registers `carve.toc` and `carve.tocPlacement` as `standard` when their respective extensions are configured. A host entry for either name takes precedence. `symbols` and `labels` are engine render maps. `source_lines` adds source line attributes. `sections` controls section wrappers around headings. `smart_typography` defaults to true. Cache keys include every render setting and a pinned engine identity. When a registered extension or renderer contains a closure, set `cache.version` to opt in to caching and change it whenever the closure changes.
