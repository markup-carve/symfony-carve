# Twig

```twig
{# filter and function, both taking optional render options #}
{{ article.body|carve }}
{{ comment.body|carve({preset: 'comment', safe_mode: 'strict'}) }}
{{ carve('# Inline /snippet/') }}

{# plain text and Markdown filters (escaped normally by Twig) #}
{{ article.body|carve_text }}
{{ article.body|carve_markdown }}

{# canonical Carve source, optionally with aligned tables #}
{{ draft|carve_format(align_tables = true) }}

{# a file below carve.include_root, with its includes expanded #}
{{ carve_file('handbook/index.crv') }}

{# table of contents and frontmatter #}
{% set doc = carve_document(article.body) %}
<title>{{ doc.meta('title') }}</title>
{% for heading in doc.toc %}<a href="#{{ heading.id }}">{{ heading.text }}</a>{% endfor %}
{{ doc.html|raw }}

{# lint findings: rule, message, line, column #}
{% for finding in carve_lint(draft) %}{{ finding.line }}: {{ finding.message }}{% endfor %}
```

Only HTML from `carve` and `carve_file` is marked safe, so Twig does not double-escape
it. Every other output is escaped normally. Safe mode only affects HTML rendering;
profiles apply to every output format.

`carve_file()` throws unless `include_root` is configured. A relative path resolves
against that root, and a path outside it is refused, so a template never reads an
arbitrary file.
