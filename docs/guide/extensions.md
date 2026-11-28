# Extensions

Pass Carve `ExtensionDefinition` or `ExtensionProvider` objects in `extensions`. The bundle also accepts `tabs`, `mermaid`, and `plantuml` as shortcuts. `diagrams` accepts `mermaid`, `plantuml`, `d2`, `graphviz`, `wavedrom`, `vega_lite`, `chart`, and `abc`; unknown diagram names are rejected. The diagram shortcuts use 0.2 fence matchers and escape their body in the hydration element. Named 0.2 bundles, including `table_of_contents`, `autolink`, and `smart_quotes`, register through the engine. Options use snake case factory parameter names.

The `wikilinks` shortcut accepts `url_template` for explicit `:wiki[Page]` links. The engine handles bundle document transforms and heading permalinks. The `plantuml` shortcut accepts both `plantuml` and `puml` fences; `graphviz` accepts both `graphviz` and `dot`.

`tabs` accepts `mode` (`css` or `aria`), and `external_links` accepts `internal_hosts`, a list of host names whose links stay in the same window.

YAML configuration names extensions by string. In PHP, for example when you build a `CarveRenderer` yourself, you can pass the `MarkupCarve\Carve\Extension\Name` cases from carve-php instead, either alone or as `type` of an entry with options:

```php
use MarkupCarve\Carve\Extension\Name;
use MarkupCarve\SymfonyCarve\CarveRenderer;

$renderer = new CarveRenderer(extensions: [
    'table_of_contents',
    Name::SmartQuotes,
    ['type' => Name::ExternalLinks, 'internal_hosts' => ['example.com']],
]);
```

`Name::values()` lists every name the engine accepts. The bundle adds `heading_levels` and `heading_level_shift` on top.
