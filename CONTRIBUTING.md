# Contributing

Thanks for helping improve symfony-carve.

## Getting started

```bash
git clone https://github.com/markup-carve/symfony-carve.git
cd symfony-carve
composer install
```

## Quality gates

Run all checks before opening a pull request:

```bash
composer test        # PHPUnit
composer stan        # PHPStan (level 8)
composer cs-check    # PHP CodeSniffer
composer cs-fix      # auto-fix code style
```

`composer check` runs the code-style check and the test suite together. CI runs the same gates on PHP 8.2-8.4 against Symfony 6.4 and 7.x.

## Guidelines

- Keep the bundle thin: it wraps [carve-php](https://github.com/markup-carve/carve-php); rendering behavior belongs there, not here.
- Add tests for any new service, Twig helper, or configuration option.
- Document new configuration keys in the README.
- Follow the existing code style (PhpCollective standard, enforced by `phpcs.xml`).
