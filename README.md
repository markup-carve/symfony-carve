# symfony-carve

Symfony bundle for Carve 0.2 through the unreleased carve-php Carve API. It provides Twig filters, services, validation, and console commands for rendering, formatting, linting, and HTML, Markdown, GFM, and Djot import.

Carve 0.1 users stay on the `0.1` branch.

## Install

```sh
composer require markup-carve/symfony-carve:^0.2
```

## Use

```twig
{{ '= Hello'|carve }}
```

Raw HTML is disabled and the Carve URL policy denies unsafe schemes by default. See the [documentation](https://markup-carve.github.io/symfony-carve/0.2/) for configuration, includes, commands, and current API gaps.
