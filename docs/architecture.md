# CLI packaging decision

Use Symfony Console and Box directly. Laravel Zero is a viable Artisan-based alternative, but its application/container/config conventions add no benefit to this packaging increment. Add Symfony Process when execution needs it and Laravel Prompts when interactive selection exists. Do not install dependencies solely for later phases.

The source CLI exposes Symfony's help, list, completion and version commands plus read-only `inspect <path>`. It cannot create or configure Laravel applications yet. Version `0.1.0-dev` is a development identifier, not a published release.

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
php tests/package.php
```

The PHAR can be moved away from source/vendor and run with PHP. It requires no Composer installation at runtime. It does not contain PHP itself.

## Acceptance boundary

Tests launch the source and PHAR from a fresh foreign directory with spaces, check exit/output contracts, and assert that directory stays unchanged. Packaging checks reject test/dev files. Future recipes need separate safety and recovery tests.

## Read-only inspection boundary (partial issue #4)

`inspect <path>` canonicalizes the project directory and reads its `composer.json` only. Success prints JSON keys `root`, `php_constraint` and `laravel_constraint`; constraints are declarations rather than installed versions or validated compatibility. Both PHP and `laravel/framework` must be nonempty strings in `require`; development-only Laravel dependencies are insufficient. Malformed JSON, missing/non-Laravel projects, non-string constraints, manifests larger than 1 MiB and manifest symlinks escaping the canonical project root are rejected with status 1 and a generic stderr diagnostic. No scripts, application bootstrap, `.env`, Composer commands or network requests run.

The planner, dependency resolution, platform/Git inspection and approved-plan drift checks remain unimplemented in issue #4. The inspection command is read-only; it is not a sandbox for untrusted concurrent filesystem changes.

## Upstream references

- https://symfony.com/doc/current/console.html
- https://github.com/box-project/box/blob/main/doc/configuration.md
- https://laravel-zero.com/docs/distribute-as-a-phar-archive
- https://laravel.com/docs/13.x/prompts
