# CLI packaging decision

Use Symfony Console and Box directly. Laravel Zero is a viable Artisan-based alternative, but its application/container/config conventions add no benefit to this packaging increment. Add Symfony Process when execution needs it and Laravel Prompts when interactive selection exists. Do not install dependencies solely for later phases.

The source CLI exposes Symfony's help, list, completion and version commands plus read-only `inspect <path>`, `plan <path>` and `check-plan <path> <plan-file>`. It cannot create or configure Laravel applications yet. Version `0.1.0-dev` is a development identifier, not a published release.

Box is development-only. It includes production dependencies using Composer metadata. Explicit alias and timestamp remove two sources of build variation. Byte reproducibility is checked by two builds in the same environment; this does not establish cross-platform or cross-PHP byte identity.

## Runtime and build

Initial qualified environment: Ubuntu 24.04, PHP 8.3.6. PHP 8.3+ is required by the manifest; other operating systems and PHP versions remain unqualified until tested. Native Windows and WSL must be qualified separately.

Running the PHAR requires PHP CLI with Phar and iconv. Building additionally needs tokenizer, mbstring and the locked build dependencies' required extensions. Composer needs HTTPS/decompression support; enable OpenSSL, curl and zlib or provide equivalent Composer-supported transport/extraction tools. Build with `phar.readonly=0`. Use Composer 2; this increment was tested with 2.8.12. Commit and install from composer.lock; do not update dependencies during reproduction.

```
composer install --no-interaction
composer test
composer build
php tests/cli.php "$PWD/build/laravel-setup.phar"
php tests/inspect.php "$PWD/build/laravel-setup.phar"
php tests/plan.php "$PWD/build/laravel-setup.phar"
php tests/package.php
```

The PHAR can be moved away from source/vendor and run with PHP. It requires no Composer installation at runtime. It does not contain PHP itself.

## Acceptance boundary

Tests launch the source and PHAR from a fresh foreign directory with spaces, check exit/output contracts, and assert that directory stays unchanged. Packaging checks reject test/dev files. Future recipes need separate safety and recovery tests.

## Read-only inspection boundary (partial issue #4)

`inspect <path>` canonicalizes the project directory and reads its `composer.json` only. Success prints JSON keys `root`, `php_constraint` and `laravel_constraint`; constraints are declarations rather than installed versions or validated compatibility. Both PHP and `laravel/framework` must be nonempty strings in `require`; development-only Laravel dependencies are insufficient. Malformed JSON, missing/non-Laravel projects, non-string constraints, manifests larger than 1 MiB and manifest symlinks escaping the canonical project root are rejected with status 1 and a generic stderr diagnostic. No scripts, application bootstrap, `.env`, Composer commands or network requests run.

The inspection command is read-only; it is not a sandbox for untrusted concurrent filesystem changes.

## Deterministic planning boundary (partial issue #4)

`plan <path> --recipe=pint --recipe=phpstan` prints a canonical JSON proposal; `check-plan <path> <plan-file>` recomputes it and rejects any structural/content mismatch. Select at least one recipe. Only the bundled stable catalog is implemented; dev and unknown recipes are rejected. Repeated selections are sorted and deduplicated. Catalog revision is SHA-256 of the exact bundled policy definitions, with schema, utility and recipe versions included. No timestamps or random identifiers enter plans. Runtime facts include actual PHP version and PHP OS/family, so cross-runtime equality is not promised.

The small catalog proposes `laravel/pint` `^1.24` and `phpstan/phpstan` `^2.1` as development dependencies. Existing constraints and dependency categories are preserved after a possible-overlap check; ambiguous declarations in both Composer sections are rejected. PHP `^8.3` and Laravel `^12.0 || ^13.0` are curated utility policies, not claims about upstream package minimum requirements or tested integration support. Composer Semver parses constraints, checks range intersections and checks the actual PHP runtime against project/recipe constraints. Intersection establishes only possible compatibility. No dependency solver, package installation, configuration publishing or integration test runs against the target; dependencies remain unresolved, integration unverified and `apply_ready` is always false.

Plans hash exact `composer.json` bytes and `composer.lock` presence/content. The lockfile is read only as bytes (maximum 16 MiB), not interpreted as installed state. Input file symlinks must remain within the canonical project root, and dangling input symlinks are rejected. No package.json or other package manifests are used in this Composer-only proposal, and they are not represented as checked. Composer manifests and saved plans are limited to 1 MiB. `inspect` continues to read composer.json only. No app/bootstrap/scripts, Composer processes, `.env` reads or network requests occur during inspection/planning/checking.

Write proposal output outside the target. `check-plan` catches changed manifests, lockfile addition/removal/content changes, changed runtime/catalog, unsupported formats and modified proposal fields. It requires canonical key/list ordering after JSON parsing; whitespace is irrelevant. Recomputed equality is consistency checking, not a cryptographic signature, user approval or authenticity proof: a party able to replace both inputs and plan can generate a new matching proposal.

## Isolated dependency resolution (partial issue #4)

`resolve-plan <path> <plan-file>` validates a canonical proposal, snapshots approved Composer inputs and stages them outside the target in a private 0700 directory. It adds selected package requirements without moving existing dependency categories or widening their constraints. Symfony Process runs Composer with argument arrays, `update --no-install --no-plugins --no-scripts --no-interaction`, a 120-second process timeout and a 60-second Composer child-process timeout. With an existing lock, only selected package names are updated; no dependency-unlocking flag is added. Without a lock the isolated solve is necessarily a full dependency resolution.

The initial resolver supports default public Packagist, stable minimum stability and a conservative manifest/config allowlist. Standard metadata, autoload, scripts and extra data are copied, but scripts/plugins/autoload generation never execute. Nonempty repositories, unknown manifest keys, custom vendor/cache/bin paths, config.platform and transport overrides are rejected rather than discarded. A fresh COMPOSER_HOME and cache isolate global configuration; inherited COMPOSER_* flags/auth/path settings are masked and known safe staging values set. Session COMPOSER_CAFILE and inherited proxy/CA/runtime environment remain available. The selected PHP and Composer executables and their runtime configuration remain trusted prerequisites, not sandboxed code.

After a successful solve the original target proposal is recomputed to reject drift. Output contains sorted resolved package names/versions and the generated lock SHA-256, with integration unverified and `apply_ready: false`. Staging is removed in finally on normal success, exceptions and timeouts; abrupt process termination or host loss can leave private temporary residue. No target files, migrations, vendor install, authentication files or remote provisioning are written. Package solver diagnostics are suppressed to avoid disclosing credentials or repository data. Existing unrelated dependency conflicts fail instead of permitting broad locked upgrades.

The resolved lock is not retained (`lock_retained: false`, `replayable: false`): a hash/summary does not enable reproducible apply. Retaining a verified, reviewable resolution artifact is future apply-engine work. Auditing and upstream package integration are not established by a solver result. Automated evidence includes fake-subprocess plumbing/timeout/drift/guard tests and actual Composer resolution against offline synthetic metadata, including an unsatisfiable fixture; that fixture does not certify live Packagist or real Laravel/Pint integration.

Remaining issue #4 scope includes broader platform/Git inspection, approved resolution artifact retention and actual upstream integration qualification before proposals can become applicable. Plan inspection/resolution is not an authorization to mutate targets.

## Upstream references

- https://symfony.com/doc/current/console.html
- https://github.com/box-project/box/blob/main/doc/configuration.md
- https://laravel-zero.com/docs/distribute-as-a-phar-archive
- https://laravel.com/docs/13.x/prompts
