# Laravel Setup Utility

A standalone, disposable Laravel ecosystem configurator. The utility runs outside target applications; selected application packages and configuration remain, but no LSU dependency, provider, route, or runtime service is added to a target.

## Current status

The CLI now supports read-only inspection and diagnostics, deterministic package planning, isolated Composer dependency resolution, retained private resolution bundles, bounded `composer.json`/`composer.lock` apply and recovery, and creation of new raw Laravel applications. New applications can opt into a strict engineering preset. **Existing-app dependency installation and engineering configuration, additional application foundations, and the full MVP remain incomplete.** See the [backlog](https://github.com/boncanca/LaravelSetupUtility/issues) and [implementation plan](docs/implementation-plan.md).

## Feature checklist

### CLI and existing-project inspection

- [x] Standalone CLI and distributable PHAR packaging.
- [x] `inspect <path>` reports the canonical project root and declared PHP/Laravel constraints without executing target code.
- [x] `doctor <path>` reports runtime/extensions, production PHP/extension requirement checks, declared and locked Laravel versions, selected configuration presence, and executable availability. It does not bootstrap the app, read `.env`, or establish installed-vendor or full application readiness.
- [x] `plan <path> --recipe=pint --recipe=phpstan` and `check-plan` create and validate deterministic proposals for the bundled stable Pint/PHPStan policies.
- [x] `resolve-plan` resolves Composer dependencies in private external staging without installing dependencies or running scripts/plugins. Synthetic resolver fixtures are not a claim of upstream package integration.
- [x] Optional external private bundles retain the original plan and Composer files plus resolved manifest/lock artifacts.
- [x] `apply-resolution` and `recover-resolution` perform bounded, drift-checked transactions on only `composer.json` and `composer.lock`; recovery refuses user edits.

### New applications

- [x] `new <path>` previews creation of a fixed raw Laravel skeleton; `new <path> --apply` creates and verifies it in private staging before publication.
- [x] The new-app workflow disables Composer lifecycle scripts/plugins, prepares a private local environment, and checks application boot, routes, and upstream tests before publishing. It does not run migrations or install/build frontend assets.
- [x] `--engineering=strict` is an opt-in preset for new applications. It deliberately retains Essentials and selected Pest, Pint, Larastan/PHPStan and Rector tools, adds reviewed configuration, `ENGINEERING.md`, an `AGENTS.md` appendix and CI, and runs its explicit formatting/check steps on the private scaffold.
- [x] The strict preset preserves upstream tests, the existing Composer test command, and handwritten agent instructions. It is not an existing-app conversion.

### Not implemented / not yet qualified

- [ ] Install dependencies or apply engineering configuration to existing applications.
- [ ] Complete the wider application foundation and recipe catalog (authentication, presentation choices, admin panels, frontend stacks, and related integrations).
- [ ] Full MVP completion and broad platform/runtime qualification.
- [ ] Temporary browser UI, tenancy/SaaS configuration, or AI recommendations.

Checked items describe implemented bounded workflows, not universal compatibility. See command limits and verification notes below; successful dependency solving or file-only apply does not mean an application is fully ready.

## Inspect, plan, resolve, and recover

```sh
php bin/laravel-setup inspect /path/to/project
php bin/laravel-setup doctor /path/to/project

php bin/laravel-setup plan /path/to/project --recipe=pint --recipe=phpstan > /outside/project/plan.json
php bin/laravel-setup check-plan /path/to/project /outside/project/plan.json
php bin/laravel-setup resolve-plan /path/to/project /outside/project/plan.json
php bin/laravel-setup resolve-plan /path/to/project /outside/project/plan.json --bundle=/outside/project/new-bundle
php bin/laravel-setup apply-resolution /path/to/project /outside/project/new-bundle
php bin/laravel-setup recover-resolution /path/to/project /outside/project/new-bundle
```

Inspection reads the manifest; diagnostics read bounded project metadata and report facts without executing application code. Planning currently supports only the bundled **stable** `pint` and `phpstan` recipe policies. Stable/dev catalog selection is distinct from selecting prerelease upstream packages. Plans check declared constraint intersections and runtime facts, hash Composer inputs, and remain proposals—not signatures, solver results, or integration certification. Save plan output outside the project.

`resolve-plan` requires Composer in `PATH` and uses public Packagist in private staging with scripts, plugins and installation disabled. The current resolver rejects custom repositories, platform overrides, custom Composer paths and unsupported manifest/configuration settings. Resolution is not a sandbox and does not qualify package integration. A bundle's hashes detect inconsistency; they do not authenticate its author.

`apply-resolution` changes only the approved `composer.json` and `composer.lock`, under an external target lock and recovery journal. It rejects drift and target symlinks; repeat apply is idempotent. Recovery restores exact original file bytes/modes, including an originally absent lock, and refuses user edits. Dependencies are not installed (`dependencies_installed: false`), full application readiness remains `apply_ready: false`, and recovery does not cover vendor state, script effects, database changes, power loss, or arbitrary hostile filesystem mutation. This is not a universal rollback guarantee.

## Create a new raw Laravel application

```sh
# Read-only preview; the parent directory must already exist.
php bin/laravel-setup new /path/to/new-app

# Create, verify, then publish.
php bin/laravel-setup new /path/to/new-app --apply

# Preview or create with the opt-in strict engineering preset.
php bin/laravel-setup new /path/to/strict-app --engineering=strict
php bin/laravel-setup new /path/to/strict-app --engineering=strict --apply
```

The raw recipe uses a pinned Laravel skeleton and resolves upstream dependencies in a private sibling stage. Automatic Composer lifecycle scripts/plugins are disabled. The workflow creates a private local environment/key and verifies application boot, routes and upstream tests before publishing to an absent destination. It does not run migrations, install/build frontend assets, create a Git repository, deploy, or retrofit an existing application. Dependency resolution can change over time; the final lock identifies the generated dependency graph but does not make future resolutions byte-identical. Composer, PHP and upstream dependency code are trusted prerequisites. Normal failures clean the stage; abrupt termination or hostile filesystem changes can fall outside cleanup guarantees.

### Strict engineering preset

The preset is only for newly staged raw applications. It intentionally retains Essentials as an application runtime dependency and selected Pest, Pint, Larastan/PHPStan and Rector development tools, together with reviewed configurations, an engineering constitution, an AGENTS appendix and CI. It preserves upstream PHPUnit tests and the Composer test command. Runtime defaults include guarded/strict models and immutable dates; global unguarding, automatic eager loading and password-policy changes are not enabled. HTTPS forcing is production-only.

`composer check` is intended as a non-mutating check of formatting, static analysis, Rector dry-run and tests. The recipe runs its explicit `composer fix` once on the new private scaffold before checks; this deliberately rewrites first-party scaffold code. A scoped selected-package update includes upstream PHPUnit to allow a Pest-compatible version, without broad `-W` upgrades. Existing-app configuration and migration of existing PHPUnit suites are not supported by this preset. Compatibility is qualified per release; it is not inferred from solver success.

## Development and packaging tests

Use PHP 8.3+ and Composer 2. The currently documented utility qualification is Ubuntu 24.04 with PHP 8.3.6; other platforms remain unverified.

```sh
composer install --no-interaction
composer test
composer build
php build/laravel-setup.phar --help
php tests/cli.php "$PWD/build/laravel-setup.phar"
php tests/inspect.php "$PWD/build/laravel-setup.phar"
php tests/plan.php "$PWD/build/laravel-setup.phar"
php tests/doctor.php "$PWD/build/laravel-setup.phar"
php tests/new-cli.php "$PWD/build/laravel-setup.phar"
php tests/package.php
```

The PHAR bundles production dependencies and can be copied elsewhere and run with PHP; Composer and the source directory are not required at runtime. See the [architecture and verification evidence](docs/architecture.md) and [implementation plan](docs/implementation-plan.md) for boundaries and historical qualification details. Run the tests on the candidate commit before relying on them; this README update does not itself constitute a test run.

## Direction

- CLI-first, with one inspect/plan/apply/verify engine for a future browser UI.
- Keep the utility outside target applications; only selected application packages and configuration remain in targets.
- Keep stable/dev catalog selection distinct from upstream prerelease package selection.
- Prefer upstream Laravel installers and conventions; add dependencies only when used.
- Keep secrets and target data out of repository, diagnostics and AI requests.
- Add broader existing-app support conservatively, with explicit recovery limits and evidence.

A temporary browser interface, tenancy/SaaS configurator and optional AI recommendations are later work, not current features. No open-source license has been selected yet; public repository visibility does not grant a license, and Composer metadata remains `proprietary` until the owner selects one.
