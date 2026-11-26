# Diagrams

By default a fenced block like ```` ``` plantuml ```` renders as a plain code block. Listing a
preset under `diagrams` turns that fence into a hydration element for a client-side renderer:

```yaml
# config/packages/carve.yaml
carve:
    diagrams: ['plantuml', 'mermaid']
```

Now ```` ``` plantuml ```` renders as `<pre class="plantuml">...</pre>` and ```` ``` mermaid ````
as `<pre class="mermaid">...</pre>`, ready for a browser library to pick up.

| Preset      | Fence word(s)        | Output                                                    |
|-------------|----------------------|-----------------------------------------------------------|
| `mermaid`   | `mermaid`            | `<pre class="mermaid">`                                   |
| `plantuml`  | `plantuml`, `puml`   | `<pre class="plantuml">`                                  |
| `d2`        | `d2`                 | `<pre class="d2">`                                        |
| `graphviz`  | `dot`, `graphviz`    | `<pre class="graphviz">`                                  |
| `wavedrom`  | `wavedrom`           | `<pre class="wavedrom">`                                  |
| `vega_lite` | `vega-lite`          | `<div class="vega-lite"><script type="application/json">` |
| `chart`     | `chart`              | `<div class="chart"><script type="application/json">`     |
| `abc`       | `abc`                | `<pre class="abc">`                                       |

The bundle only emits the markup - it does **not** ship or load any renderer. You supply the
client side:

- **Graphviz, D2** render fully offline (no server, no external call) with the WebAssembly helpers
  from [`@markup-carve/carve-grammars`](https://github.com/markup-carve/carve-grammars):
  `renderDiagrams` (or `renderGraphvizDiagrams` / `renderD2Diagrams`).
- **PlantUML** has no practical in-browser renderer; render it via a [Kroki](https://kroki.io)
  server with the same package's `renderKrokiDiagrams` helper.
  > ⚠️ **Privacy / GDPR:** the default Kroki server is the public `https://kroki.io`, so the
  > PlantUML source is sent to a third party outside your domain. For sensitive content, or to
  > stay offline, point the helper's `server` option at a self-hosted or localhost Kroki, and
  > disclose the external call to end users where required.
- **Mermaid, WaveDrom, Vega-Lite, Chart.js, ABC** each need their own browser library loaded on
  the page (mermaid.js, wavedrom, vega-embed, chart.js, abcjs).

Unknown names in the whitelist are rejected by config validation; the accepted values are exactly
the presets above.
