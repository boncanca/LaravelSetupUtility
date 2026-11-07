# Laravel Setup Utility

A standalone, disposable Laravel ecosystem configurator.

## Current increment

The standalone CLI supports read-only manifest inspection, deterministic engineering package proposals and isolated Composer resolution. **Creating and configuring Laravel applications is not implemented yet.** See [the backlog](https://github.com/boncanca/LaravelSetupUtility/issues) for the remaining work.

Run `php bin/laravel-setup inspect /path/to/project` to print the canonical project root and PHP/Laravel constraints declared in `composer.json` as JSON. Inspection reads only that manifest; it does not execute application code or report installed versions.

```sh
php bin/laravel-setup plan /path/to/project --recipe=pint --recipe=phpstan > /outside/project/plan.json
php bin/laravel-setup check-plan /path/to/project /outside/project/plan.json
php bin/laravel-setup resolve-plan /path/to/project /outside/project/plan.json
```

Planning supports only the bundled stable `pint` and `phpstan` recipe policies for existing Laravel projects. It checks declared constraint intersections and the current PHP runtime, and hashes Composer inputs. `check-plan` detects mismatches against a recomputed canonical proposal; it does not authenticate approval or establish installability. Save redirected output outside the project to keep the target unchanged.

`resolve-plan` requires Composer in PATH and resolves dependencies in disposable private staging, using public Packagist without plugins, scripts or installation. It reports concrete package versions and a lock hash after checking target drift again. Custom repositories, platform overrides, custom Composer paths and unknown manifest/config settings are rejected. Solver diagnostics are redacted. The generated lock is deleted with staging: the summary is evidence, not a replayable install artifact. Recipe integration remains unverified and all outputs keep `apply_ready: false`.

## Development

Use PHP 8.3+ and Composer 2. The currently qualified runtime is Ubuntu 24.04 with PHP 8.3.6; other platforms remain unverified.

```sh
composer install --no-interaction
composer test
composer build
php build/laravel-setup.phar --help
php tests/cli.php "$PWD/build/laravel-setup.phar"
php tests/inspect.php "$PWD/build/laravel-setup.phar"
php tests/plan.php "$PWD/build/laravel-setup.phar"
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
