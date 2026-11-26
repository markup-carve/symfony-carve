# Installation

Add this repository as a Composer VCS repository and require its `main` branch. Composer will fetch the pinned Carve engine commit declared in [composer.json](https://github.com/markup-carve/symfony-carve/blob/main/composer.json).

```sh
composer config repositories.symfony-carve vcs https://github.com/markup-carve/symfony-carve.git
composer require markup-carve/symfony-carve:dev-main
```

Register the bundle (Symfony Flex does this automatically; otherwise add it to `config/bundles.php`):

```php
return [
    // ...
    MarkupCarve\SymfonyCarve\CarveBundle::class => ['all' => true],
];
```
