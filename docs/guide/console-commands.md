# Console Commands

```bash
bin/console carve:render docs/page.crv                     # HTML to stdout
bin/console carve:render docs/page.crv --format=text       # html, text, markdown or ansi
bin/console carve:render docs/page.crv --preset=comment -o page.html

bin/console carve:lint docs/                               # every *.crv below docs/
bin/console carve:lint docs/book.crv --include-root=docs   # also lint included files

bin/console carve:fmt docs/page.crv                        # print the canonical form
bin/console carve:fmt docs/ --check                        # fail if a file is not formatted
bin/console carve:fmt docs/ --write --align-tables         # rewrite in place, pad tables

bin/console carve:convert README.md -o README.crv          # markdown, html or djot
bin/console carve:convert page.html --from=html

bin/console carve:trace docs/page.crv                      # block decision trace, JSON lines
```

`carve:render` uses the bundle configuration and expands includes when
`include_root` is set. `carve:lint` runs every carve-php document lint rule and exits
non-zero on findings, so it fits a CI step. `carve:fmt --check` exits non-zero when a
file differs from its canonical form; `--write` and `--check` cannot be combined.
`carve:convert` guesses the input format from the extension (`.md`, `.html`, `.djot`)
unless `--from` names it. `carve:trace` prints the trace `carve trace` prints, one
JSON record per source line, which helps when a block does not parse as expected.
