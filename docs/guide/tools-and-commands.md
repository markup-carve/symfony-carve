# Tools, Validation, and Commands

`CarveTools::format()` uses `Carve::format()`. `lint()` maps Carve diagnostics to `LintFinding` objects with `rule`, `message`, `file`, `line`, and `column`. `fromHtml()` uses the Carve HTML importer. `ValidCarve` uses Carve parse and render diagnostics for strict and profile validation; its `lint` flag calls the 0.2 lint catalog.

Commands remain `carve:render`, `carve:fmt`, `carve:lint`, `carve:convert`. `carve:convert` accepts `html`, `markdown`, `gfm` and `djot`. `markdown` and `gfm` both read GitHub Flavored Markdown, as the 0.1 command did, so tables, task lists and strikethrough convert; `CarveTools::fromMarkdown()` does the same.

The engine supplies Markdown, text, and ANSI writers for the renderer, Twig filters, and commands.
