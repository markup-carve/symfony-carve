# File Includes

Twig filters and `CarveRenderer::render()` accept anonymous strings, so they
never read files. Set an absolute `include_root` and call the explicit file API
for trusted, file-backed content:

```php
$report = $renderer->renderFileWithReport('/srv/docs/book/main.crv');
$html = $report['value'];
$warnings = $report['warnings'];
$dependencies = $report['dependencies'];
```

`renderFile()` is the HTML-only convenience form. Relative includes resolve
from the file containing each directive and cannot traverse or follow a symlink
outside the configured root. Warning reports omit resolver details and replace
outside paths before they reach the optional PSR logger.

`include_root` has to be an absolute path, and the renderer refuses a relative
one rather than resolving it against the working directory, which is arbitrary
with respect to the document.

The dependency list contains resolved and attempted targets. Applications that
cache rendered HTML must include those identities in their invalidation policy,
including missing targets, so creating a formerly missing file invalidates its
parent. The bundle's own cache does this already.
