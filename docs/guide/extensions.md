# Extensions

Pass Carve `ExtensionDefinition` or `ExtensionProvider` objects in `extensions`. The bundle also accepts `tabs`, `mermaid`, and `plantuml` as shortcuts. `diagrams` accepts `mermaid`, `plantuml`, `d2`, `graphviz`, `wavedrom`, `vega_lite`, `chart`, and `abc`; unknown diagram names are rejected. The diagram shortcuts use 0.2 fence matchers and escape their body in the hydration element. Named 0.2 bundles, including `table_of_contents`, `autolink`, and `smart_quotes`, register through the engine. Options use snake case factory parameter names.

The `wikilinks` shortcut accepts `url_template` for explicit `:wiki[Page]` links. The engine handles bundle document transforms and heading permalinks. The `plantuml` shortcut accepts both `plantuml` and `puml` fences; `graphviz` accepts both `graphviz` and `dot`.
