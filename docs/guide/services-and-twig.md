# Services and Twig

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

The `carve_text` and `carve_markdown` Twig filters stay registered. They use the built-in Carve writers.
