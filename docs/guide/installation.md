# Installation

Require the bundle with Composer:

```sh
composer require markup-carve/symfony-carve:^0.2
```

Register the bundle (Symfony Flex does this automatically; otherwise add it to `config/bundles.php`):

```php
return [
    // ...
    MarkupCarve\SymfonyCarve\CarveBundle::class => ['all' => true],
];
```
