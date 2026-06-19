# symfony-carve

Symfony bundle that renders [Carve](https://github.com/markup-carve/carve) markup to HTML using the [carve-php](https://github.com/markup-carve/carve-php) reference implementation.

Carve is "Djot minus the footguns": a lightweight markup language with consistent, unambiguous syntax.

> [!NOTE]
> carve-php is not yet tagged on Packagist. Until it is, this bundle pulls it as `dev-main` straight from GitHub (the VCS repository is declared in `composer.json`).

## Installation

```bash
composer require markup-carve/symfony-carve:dev-main
```

If your project does not already allow dev stability, add the VCS repository and stability flags to your root `composer.json`:

```json
{
    "minimum-stability": "dev",
    "prefer-stable": true,
    "repositories": [
        { "type": "vcs", "url": "https://github.com/markup-carve/carve-php" }
    ]
}
```

Register the bundle (Symfony Flex does this automatically; otherwise add it to `config/bundles.php`):

```php
return [
    // ...
    Carve\Symfony\CarveBundle::class => ['all' => true],
];
```

## Usage

### Service

```php
use Carve\Symfony\CarveRenderer;

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
