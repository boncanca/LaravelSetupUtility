# Independent cloud development

The issue #6 development session runs on an included managed cloud executor,
using its local Docker daemon. It does not use the user's computer. No paid
resource was provisioned. GitHub credentials belong to the outer executor;
application installation processes receive a restricted environment and no
GitHub token. Never put credentials in images or repository files.

## Reproduce

The PHP base and Composer image are pinned by digest in
`infrastructure/cloud/Dockerfile`. Build explicitly, then install the locked
utility dependencies explicitly:

```sh
infrastructure/cloud/build
infrastructure/cloud/dev php --version
infrastructure/cloud/dev composer --version
infrastructure/cloud/dev composer install --no-interaction --prefer-dist
infrastructure/cloud/dev composer test
infrastructure/cloud/dev composer build
infrastructure/cloud/dev php tests/cli.php "$PWD/build/laravel-setup.phar"
infrastructure/cloud/dev php tests/package.php
```

The wrapper requires the included local Docker socket, a workspace mounted at
`/workspace`, and the system CA bundle. `LSU_WORKSPACE`, `LSU_RUNTIME_DIR`, and
`LSU_TMPDIR` can select other workspace locations. Commands run as the caller's
UID/GID. Docker's configured proxy defaults are preserved, and the outer CA
bundle is mounted read-only for verified HTTPS. No dependency installation
happens implicitly when invoking the wrapper. Node/npm are unnecessary for the
Laravel-only PHP journey; frontend assets are not built.

Temporary applications and Composer state live under private directories in
`/workspace/work`, outside the utility and generated applications. `TMPDIR`
must use the destination filesystem for atomic directory publication. Do not
use a network filesystem or a destination parent writable by an untrusted user.

## Reproducibility boundary

Utility dependencies use the committed `composer.lock`. Exact base-image
digests and Composer version are fixed. Alpine packages are installed from the
configured official repositories at image build time; this is a reproducible
build recipe, not a promise of byte-identical operating-system rebuilds after
repository updates. Retain the built image and recorded package versions for
exact runtime replay. The Laravel skeleton is pinned separately by the create
engine; its first dependency resolution uses current compatible stable packages
and the generated lockfile records that resolution.

The cloud runtime is available for this session. This repository setup does not
publish a reusable platform environment snapshot or guarantee future scheduled
worker availability. Each resumed worker must recheck runtime, credentials,
repository state and exclusive ownership before writing.

## Isolated code quality tools

PHPStan and Pint used for the new create engine are development tools in a
separate locked Composer project. They do not become utility runtime or generated
application dependencies. Install and run them explicitly:

```sh
infrastructure/cloud/dev composer install --working-dir=infrastructure/quality --no-interaction
infrastructure/cloud/dev php infrastructure/quality/vendor/bin/phpstan analyse --autoload-file=vendor/autoload.php --configuration=phpstan-create.neon --no-progress
infrastructure/cloud/dev php infrastructure/quality/vendor/bin/pint --test src/Creator.php src/CreateCommand.php tests/create.php tests/create-real.php
```

The scoped checks cover the new create engine; existing baseline files are not
silently reformatted or claimed to satisfy a new repository-wide rule set.

## Qualified session runtime (2026-11-07)

The built image is
`sha256:33d9713d5c0c94a3ee31eaa7e34637c26f24efdff76078eece6456799348eaf6`:
PHP 8.4.14, Composer 2.11.1, GNU mv 9.7, pcntl and the required Laravel
extensions. `infrastructure/cloud/qualified-runtime.txt` records its extensions
and installed Alpine packages. Archive baseline `74bb584` passed all six
existing suites, strict manifest validation, platform checks, PHAR build and
contracts, archive exclusions, and repeated-build byte comparison inside this
image before create implementation was verified. This does not qualify all
PHP 8.3+ versions or other operating systems.

Composer 2.11.1 replaces the initial 2.8.12 pin after GitHub setup reported
[GHSA-f9f8-rm49-7jv2](https://github.com/advisories/GHSA-f9f8-rm49-7jv2).
The affected token handling is fixed from Composer 2.9.8. Installation child
processes still receive no caller credentials; the version update supplements
that isolation. Earlier baseline logs using 2.8.12 remain historical evidence.
