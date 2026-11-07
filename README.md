# Laravel Setup Utility

A standalone, disposable Laravel ecosystem configurator.

## Current increment

The CLI packaging foundation and read-only manifest inspection are implemented, with a standalone PHAR build. **Creating and configuring Laravel applications is not implemented yet.** See [the backlog](https://github.com/boncanca/LaravelSetupUtility/issues) for the remaining work.

Run `php bin/laravel-setup inspect /path/to/project` to print the canonical project root and PHP/Laravel constraints declared in `composer.json` as JSON. Inspection reads only that manifest; it does not execute application code or report installed versions.

## Development

Use PHP 8.3+ and Composer 2. The currently qualified runtime is Ubuntu 24.04 with PHP 8.3.6; other platforms remain unverified.

```sh
composer install --no-interaction
composer test
composer build
php build/laravel-setup.phar --help
php tests/cli.php "$PWD/build/laravel-setup.phar"
php tests/inspect.php "$PWD/build/laravel-setup.phar"
php tests/package.php
```

The PHAR bundles the CLI's production dependencies. Copy it elsewhere and run it with PHP; Composer and the source directory are not required at runtime. Build requirements and the architecture choice are documented in [docs/architecture.md](docs/architecture.md).

## Direction

- CLI-first with one inspect, plan, apply and verify engine.
- Only selected application packages and configuration remain in targets.
- Stable and dev recipe catalogs pinned per run.
- Conservative additive support for existing applications.
- Temporary browser interface, SaaS/tenancy and optional AI recommendations follow the core CLI.

No open-source license has been selected yet; public repository visibility does not grant a license. The Composer metadata uses `proprietary` until the owner selects one.
