# symfony-carve

[![CI](https://github.com/markup-carve/symfony-carve/actions/workflows/ci.yml/badge.svg)](https://github.com/markup-carve/symfony-carve/actions/workflows/ci.yml)
[![PHP](https://img.shields.io/packagist/php-v/markup-carve/symfony-carve)](https://packagist.org/packages/markup-carve/symfony-carve)
[![License](https://img.shields.io/packagist/l/markup-carve/symfony-carve)](LICENSE)

Symfony bundle that renders [Carve](https://github.com/markup-carve/carve) markup to HTML using [carve-php](https://github.com/markup-carve/carve-php).

Carve is "Djot minus the footguns": a lightweight markup language with consistent, unambiguous syntax.

## Installation

```bash
composer require markup-carve/symfony-carve
```

Register the bundle (Symfony Flex does this automatically; otherwise add it to `config/bundles.php`):

```php
return [
    // ...
    MarkupCarve\Symfony\CarveBundle::class => ['all' => true],
];
```

## Usage

### Service

```php
use MarkupCarve\Symfony\CarveRenderer;

public function show(CarveRenderer $carve): Response
{
    $html = $carve->render('# Hello *world*');

    return new Response($html);
}
```

### Twig

```twig
{# filter #}
{{ article.body|carve }}

{# function #}
{{ carve('# Inline /snippet/') }}
```

Output is marked safe, so Twig does not double-escape it. The renderer sanitizes input according to the configured safe mode before that point.

## Configuration

```yaml
# config/packages/carve.yaml
carve:
    safe_mode: true      # sanitize HTML (default: true)
    raw_html: strip      # strip | escape | allow (default: strip)
```

| Key         | Type   | Default | Description                                                                 |
|-------------|--------|---------|-----------------------------------------------------------------------------|
| `safe_mode` | bool   | `true`  | Enable HTML sanitization. Keep this on for untrusted input.                 |
| `raw_html`  | enum   | `strip` | How raw HTML is handled when `safe_mode` is on: `strip`, `escape`, `allow`. |

Setting `safe_mode: false` disables sanitization entirely. Only do this for fully trusted input.

## Demo

A full runnable demo app lives at [symfony-carve-demo](https://github.com/markup-carve/symfony-carve-demo): the Twig filter and function, the `CarveRenderer` service, a live editor, a safe-mode comparison, and a syntax gallery.

[![symfony-carve demo](https://raw.githubusercontent.com/markup-carve/symfony-carve-demo/main/docs/screenshots/twig-filter.png)](https://github.com/markup-carve/symfony-carve-demo)

## License

MIT
