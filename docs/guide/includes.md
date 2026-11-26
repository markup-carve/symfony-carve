# Includes and File Rendering

`include_root` must be an absolute existing directory. The bundle passes Carve's `FileResolver` to rendering and reports dependencies and include warnings from the 0.2 diagnostic channel. It refuses source files outside that root. Missing or changed included files invalidate cached file renders.

```php
$renderer = new CarveRenderer(includeRoot: '/srv/docs');
$html = $renderer->renderFile('/srv/docs/index.crv');
$report = $renderer->renderFileWithReport('/srv/docs/index.crv');
```

String rendering does not read files. `renderFileAs()` accepts `html`, `text`, `markdown`, and `ansi`. The latter three targets use the built-in Carve writers. A host can pass a `WriterRegistry` to the renderer constructor to provide its own writer under one of those names.
