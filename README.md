# Laravel Setup Utility

A standalone, disposable Laravel ecosystem configurator.

## Current increment

The standalone CLI supports manifest inspection, deterministic engineering proposals, isolated Composer resolution and bounded Composer manifest/lock apply/recovery. **New raw Laravel creation is available as a bounded workflow. Existing-app dependency installation and engineering configuration remain incomplete.** See [the backlog](https://github.com/boncanca/LaravelSetupUtility/issues) for the remaining work.

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

## New raw Laravel application

```sh
# Read-only preview; parent directory must already exist.
php bin/laravel-setup new /path/to/new-app
# Execute the displayed fixed recipe, install upstream code and verify in staging.
php bin/laravel-setup new /path/to/new-app --apply
```

The recipe pins `laravel/laravel` skeleton 13.11.0, resolves and retains Composer dependencies/lock, disables Composer scripts/plugins, writes a private `.env` and key, then runs Artisan boot/route checks and the upstream PHPUnit suite. Sessions/cache use files and queues run synchronously, so the welcome page does not require database setup. No database migrations, frontend install/build, authentication kit, engineering preset, Git repository or deployment are performed.

A private sibling directory holds staging; the destination appears only after checks pass. Existing paths, including symlinks, are refused. Failed/timeout runs clean their owned stage and do not publish; abrupt host/process termination may leave a private sibling stage requiring manual removal. Cooperating new-project runs use an external lock. Arbitrary hostile filesystem changes and power-loss durability are outside this guarantee. The parent directory must be trusted.

Dependency solving is time-sensitive: the pinned skeleton and saved final lock identify the generated app; this is not byte-identical generation across future resolutions. Composer/PHP and upstream dependency code are trusted prerequisites. Subprocess output is suppressed to avoid exposing secrets; errors name the failed step. At present this recipe supports only raw Laravel, with no existing-app conversion.

## Read-only runtime diagnostics

`php bin/laravel-setup doctor /path/to/project` returns JSON containing actual PHP/extensions, production PHP/extension requirement checks, declared and locked Laravel versions, known configuration presence, and executable availability on PATH. It does not execute discovered tools, application bootstrap, Composer scripts, Git hooks, or read `.env`. A lock version is not proof of installed vendor state. Tool versions, Git cleanliness, transitive/dev platform requirements and frontend manifests are not validated yet.

Exit 0 means the declared production PHP/extension requirements checked by doctor are satisfied; exit 1 with a report means requirements are unmet. Invalid input fails on stderr without echoing file contents. Every report retains `apply_ready: false`. Configuration presence does not mean a file is valid or an escaping symlink is trusted for later writes.

The approved roadmap and issue mapping are in [docs/implementation-plan.md](docs/implementation-plan.md).

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
