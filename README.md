# symfony-carve

Symfony bundle for Carve 0.2 through the unreleased carve-php Carve API. It provides Twig filters, services, validation, and console commands for rendering, formatting, linting, and HTML, Markdown, GFM, and Djot import.

This branch is a preview. Its Composer dependency is pinned to a specific carve-php `main` commit and is not a registry release. Carve 0.1 users stay on the `0.1` branch.

## Install from git

Add this repository as a Composer VCS repository and require its `main` branch. Composer will fetch the pinned Carve engine commit declared in [composer.json](composer.json).

```sh
composer config repositories.symfony-carve vcs https://github.com/markup-carve/symfony-carve.git
composer require markup-carve/symfony-carve:dev-main
```

## Use

```twig
{{ '= Hello'|carve }}
```

Raw HTML is disabled and the Carve URL policy denies unsafe schemes by default. See the [guide](docs/guide.md) for configuration, includes, commands, and current API gaps.
