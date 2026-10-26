# symfony-carve

[![CI](https://github.com/markup-carve/symfony-carve/actions/workflows/ci.yml/badge.svg)](https://github.com/markup-carve/symfony-carve/actions/workflows/ci.yml)
[![PHP](https://img.shields.io/packagist/php-v/markup-carve/symfony-carve)](https://packagist.org/packages/markup-carve/symfony-carve)
[![License](https://img.shields.io/packagist/l/markup-carve/symfony-carve)](LICENSE)

Symfony bundle that renders Carve through carve-php 0.1.14 or later. Twig filters, services, and console commands share the same renderer. Read the [Carve documentation](https://markup-carve.github.io/carve/) for the language syntax.

## Install

```sh
composer require markup-carve/symfony-carve
```

## Usage

```twig
{{ article.body|carve }}
```

## Documentation

- [Carve documentation](https://markup-carve.github.io/carve/)
- [Documentation index](docs/README.md)

## License

[MIT](LICENSE)
