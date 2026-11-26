# Installation

```sh
composer require markup-carve/symfony-carve
```

Register the bundle (Symfony Flex does this automatically; otherwise add it to `config/bundles.php`):

```php
return [
    // ...
    MarkupCarve\SymfonyCarve\CarveBundle::class => ['all' => true],
];
```
