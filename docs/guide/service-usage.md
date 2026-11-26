# Service Usage

`CarveRenderer` renders with the bundle configuration and is autowirable:

```php
use MarkupCarve\SymfonyCarve\CarveRenderer;

public function show(CarveRenderer $carve): Response
{
    $html = $carve->render('# Hello *world*');
    $text = $carve->renderText('# Hello *world*');
    $markdown = $carve->renderMarkdown('# Hello *world*');
    $ansi = $carve->renderAnsi('# Hello *world*');

    // HTML plus the table of contents and frontmatter
    $result = $carve->renderResult($source);
    $result->html;
    $result->toc();          // [['level' => 1, 'text' => 'Hello', 'html' => ..., 'id' => ...]]
    $result->meta('title');  // JSON frontmatter, or YAML with symfony/yaml installed

    // One rendering context with other options
    $comment = $carve->withOptions(['preset' => 'comment', 'safe_mode' => 'strict']);

    return new Response($html);
}
```

Every HTML method returns the fragment without a final newline, as the Carve HTML API
specifies; add your own line ending when writing an output file.

`withOptions()` accepts `safe_mode`, `raw_html`, `profile` (or its alias `preset`),
`on_disallowed`, `mode`, `soft_break_mode`, `xhtml`, `source_lines` and
`smart_typography`. Any other key throws, so a typo never renders silently with the
defaults.

`CarveTools` holds the source-level operations:

```php
use MarkupCarve\SymfonyCarve\CarveTools;

$tools->format($source);                      // canonical source, as `carve fmt` prints it
$tools->format($source, alignTables: true);   // also pad pipe-table columns
$tools->isFormatted($source);
$tools->lint($source);                        // list of MarkupCarve\Carve\Lint\LintWarning
$tools->lint($source, '/srv/docs', '/srv/docs/main.crv'); // follow includes below a root
$tools->fromMarkdown($markdown);
$tools->fromHtml($html);
$tools->fromDjot($djot);
$tools->import($source, 'markdown');          // markdown, html or djot
$tools->trace($source);                       // block decision trace as JSON lines
```

`lint()` runs every rule of carve-php's `DocumentLinter`. With an include root,
findings in included files carry `file` and `includedBy`. Suppression comments in the
source (`%% carve-lint-disable`, `-enable`, `-disable-line`, `-disable-next-line`) apply.
