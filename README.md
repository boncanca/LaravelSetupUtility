# Laravel Setup Utility

A standalone, disposable Laravel ecosystem configurator.

## Current increment

The standalone CLI supports manifest inspection, deterministic engineering proposals, isolated Composer resolution and bounded Composer manifest/lock apply/recovery. **Creating Laravel applications, configuring engineering tools and installing vendor dependencies are not implemented yet.** See [the backlog](https://github.com/boncanca/LaravelSetupUtility/issues) for the remaining work.

Run `php bin/laravel-setup inspect /path/to/project` to print the canonical project root and PHP/Laravel constraints declared in `composer.json` as JSON. Inspection reads only that manifest; it does not execute application code or report installed versions.

```sh
php bin/laravel-setup plan /path/to/project --recipe=pint --recipe=phpstan > /outside/project/plan.json
php bin/laravel-setup check-plan /path/to/project /outside/project/plan.json
php bin/laravel-setup resolve-plan /path/to/project /outside/project/plan.json
php bin/laravel-setup resolve-plan /path/to/project /outside/project/plan.json --bundle=/outside/project/new-bundle
php bin/laravel-setup apply-resolution /path/to/project /outside/project/new-bundle
php bin/laravel-setup recover-resolution /path/to/project /outside/project/new-bundle
```

Planning supports only the bundled stable `pint` and `phpstan` recipe policies for existing Laravel projects. It checks declared constraint intersections and the current PHP runtime, and hashes Composer inputs. `check-plan` detects mismatches against a recomputed canonical proposal; it does not authenticate approval or establish installability. Save redirected output outside the project to keep the target unchanged.

`resolve-plan` requires Composer in PATH and resolves dependencies in private staging, using public Packagist without plugins, scripts or installation. Custom repositories, platform overrides, custom Composer paths and unknown manifest/config settings are rejected. Solver diagnostics are redacted. Without `--bundle`, the generated lock is discarded. An optional new external bundle retains exact inputs, plan, resolved files and hashes with private permissions.

`apply-resolution` changes only the approved `composer.json` and `composer.lock`, under an external target lock and recovery journal. It rejects drift, tampered bundles and target symlinks; repeated apply skips already-applied bytes. Recovery restores exact original files, including an originally absent lock, and refuses user edits. Backups/journal remain outside the target. Dependencies are not installed and all summaries explicitly retain pending installation/integration status. Review/trust the bundle: hashes detect inconsistency and are not signatures or authorization. Recovery covers these two files, not vendor state, script effects or universal rollback.

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
