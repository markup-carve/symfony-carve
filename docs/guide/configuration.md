# Configuration

```yaml
# config/packages/carve.yaml
carve:
    safe_mode: true          # true | false | strict
    raw_html: strip          # strip | escape | allow
    profile: null            # null | full | article | comment | minimal
    on_disallowed: null      # null | strip | to_text | error
    mode: interactive        # interactive | static
    soft_break_mode: null    # null | newline | space | br
    xhtml: false
    source_lines: false
    smart_typography: null   # null | true | false
    symbols: {}              # trusted HTML for :name: symbols
    labels: {}
    extensions: []
    diagrams: []
    include_root: null       # absolute trusted filesystem root
    cache:
        enabled: false
        pool: cache.app
        ttl: null
        prefix: carve
        version: null
```

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `safe_mode` | bool\|`strict` | `true` | HTML sanitization. `strict` always strips raw HTML and also blocks `style`, `srcdoc` and `formaction` attributes. Keep it on for untrusted input. |
| `raw_html` | enum | `strip` | How raw HTML is handled when `safe_mode` is `true`: `strip`, `escape`, `allow`. |
| `profile` | enum\|null | `null` | Restrict markup features using `full`, `article`, `comment`, or `minimal`. |
| `on_disallowed` | enum\|null | `null` | What a profile does with disallowed markup: `strip`, `to_text` or `error`. |
| `mode` | enum | `interactive` | `static` applies graceful degradation for print, PDF, email and other script-free targets. |
| `soft_break_mode` | enum\|null | `null` | Render soft breaks as `newline`, `space` or `br`. |
| `xhtml` | bool | `false` | Emit XHTML-compatible self-closing tags. |
| `source_lines` | bool | `false` | Add 1-based `data-source-line` attributes for editor scroll-sync. |
| `smart_typography` | bool\|null | `null` | Force smart typography on or off. |
| `symbols` | map | `{}` | HTML replacements for `:name:` symbols. Inserted unescaped, so never fill it from user input. |
| `labels` | map | `{}` | Overrides for generated UI labels. |
| `extensions` | list | `[]` | carve-php extensions, see [Extensions](./extensions). |
| `diagrams` | string[] | `[]` | Diagram fenced-block presets to enable, see [Diagrams](./diagrams). |
| `include_root` | string\|null | `null` | Absolute containment root for file-backed rendering. |
| `cache.*` | map | off | Render caching, see [Caching](./caching). |

Setting `safe_mode: false` disables sanitization entirely. Only do this for fully trusted input.
Unknown values are rejected when the container compiles.
