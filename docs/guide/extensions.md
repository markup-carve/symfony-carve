# Extensions

Each entry is an extension type name, or a map with a `type` key and the extension's
constructor options in snake_case:

```yaml
carve:
    extensions:
        - autolink
        - smart_quotes
        - { type: heading_permalinks, symbol: '#' }
        - { type: table_of_contents, toc_class: page-toc }
        - { type: wikilinks, url_template: '/wiki/{page}' }
        - { type: mentions, user_url_template: '/users/{name}' }
```

Supported types: `admonition`, `ascii_heading_ids`, `autolink`, `citations`,
`code_callouts`, `code_group`, `color_swatch`, `default_attributes`, `details`,
`external_links`, `fenced_render`, `frontmatter`, `glossary`, `heading_level_shift`,
`heading_numbers`, `heading_permalinks`, `heading_reference`, `img_fence`, `index`,
`inline_footnotes`, `list_table`, `lowercase_heading_ids`, `math_block`, `mentions`,
`mermaid`, `plantuml`, `plus_bullet`, `semantic_span`, `smart_quotes`, `spoiler`,
`tab_normalize`, `table_of_contents`, `tabs`, `toc_placement`, `wikilinks`. A fully
qualified extension class name works too.

`url_template` on `wikilinks` slugs the page name into `{page}`: `[[Main Page]]` links
to `/wiki/main-page`. An unknown type or option fails at container build time instead of
being ignored. Every render builds fresh extension instances, so collected state such as
the table of contents never leaks between two snippets on one page.
