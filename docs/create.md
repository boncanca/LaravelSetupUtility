# New Laravel applications

```
php bin/laravel-setup create /private/projects/my-app
php build/laravel-setup.phar create /private/projects/my-app
```

The parent must already exist and must not be writable by other users/groups. All ancestors must be trusted against replacement by untrusted principals; a private owned directory below a standard sticky `/tmp` is acceptable. Existing destinations (including empty directories), symlink path components, `.`/`..` components and unsafe destination names are refused. Names may contain spaces, ASCII letters/digits, dots, underscores and hyphens. The command never modifies an existing application.

This increment qualifies Linux 5.4+ on local overlay/ext filesystems, GNU coreutils `mv` 9.1+, Composer 2, PHP 8.3/8.4 and the Laravel-required extensions plus SQLite, pcntl and posix. ZIP extraction requires ext-zip or a trusted `/usr/bin/unzip` or `/bin/unzip`. Native Windows, network filesystems, other filesystem types and PHP 8.5 are refused. The cloud execution evidence records the versions actually tested; the supported PHP range is additionally exercised by CI as configured. Node/npm are not needed: no frontend installation/build is performed.

A private external temporary directory must share the destination filesystem. Set `TMPDIR` to a private directory on that filesystem when the default temporary directory is on another mount. Temporary data, the isolated Composer home/cache and process handshakes stay outside the generated application.

## Official installation and verification

The command downloads the exact official `laravel/laravel` skeleton `v12.12.2` (upstream reference `945f4e5a9fd3695dc0ee512f497c650fb82cfbb8`) through Composer `create-project --prefer-dist --no-scripts --no-plugins`. Its upstream framework constraint remains `^12.0`; the generated lockfile records the concrete packages resolved at installation time. Fresh installations can resolve newer compatible framework/dependency releases. Reproduce a generated application's dependencies using its retained lockfile with Composer install, rather than recreating it with a fresh solve.

The upstream skeleton includes its own PHPUnit, Pint, Pail, Sail and other development dependencies. These are preserved official Laravel defaults. The utility adds no engineering preset packages or agent configuration in this increment. Selectable engineering tooling is a separate follow-up; no claim of complete issue #6 acceptance is made here.

All Composer scripts and plugins are disabled during installation. The upstream `setup` and `post-create-project-cmd` scripts run migrations and/or npm and are deliberately not executed. The command qualifies these explicit official initialization steps instead:

1. Copy upstream `.env.example` into a private `.env` (mode 0600), with file sessions, file cache and synchronous queue defaults so the homepage works without migrations or external services.
2. Run `php artisan key:generate --force` and `php artisan package:discover`.
3. Bootstrap the framework HTTP kernel and require status 200 for both `/` and `/up` under the actual application environment.
4. Run the skeleton's `php artisan test --no-ansi --do-not-cache-result`, using an in-memory SQLite test database. No migrations, seeds, Git operations or remote resources are started.
5. Remove generated smoke/test views, session/cache/log files and PHPUnit result caches before publishing. The application retains its ordinary Laravel files, installed dependencies and lockfile.

Child processes receive a small environment allowlist for trusted executable paths, official network proxy/CA settings and private Composer directories. Arbitrary caller credentials and Composer configuration are removed. Composer/application process output is suppressed on failure; errors identify the failed phase without reproducing potentially sensitive output.

## Publication and interruption

Every external command runs in a private process session created by PHP `posix_setsid`, with a private exclusive PID handshake before `pcntl_exec`. Timeout, SIGINT and SIGTERM cancellation terminate the entire session (TERM then KILL), including surviving extraction children, before temporary cleanup. Each command has a finite timeout (ten minutes by default). SIGKILL, machine loss and filesystem failures cannot guarantee cleanup; inspect external temporary storage after those events.

Only a verified application is published. Parent identity is rechecked, and GNU `mv --no-clobber --no-target-directory` performs same-device publication; the source must disappear and the destination must retain the staged directory's device/inode. GNU coreutils 9.1 uses `renameatu(..., RENAME_NOREPLACE)` in [src/mv.c](https://github.com/coreutils/coreutils/blob/v9.1/src/mv.c) and [src/copy.c](https://github.com/coreutils/coreutils/blob/v9.1/src/copy.c). Filesystem/kernel qualification is necessary because unsupported platforms may use a fallback. The preflight no-clobber probe additionally checks ordinary refusal semantics; it alone is not evidence of atomic race safety.

Cancellation is honored before publication. Once the verified rename begins, the command completes/reconciles publication rather than cancelling the commit point. A verified destination may therefore exist if the caller loses the process exactly during publication. Rerun always refuses that existing path. Existing user files and destinations are never deleted or overwritten; cleanup applies only to this invocation's private staging tree.

## Tests

`composer test` includes deterministic orchestration fixtures, labelled synthetic: path safety, secret filtering, Composer failure, timeout, orphaned descendants, early leader exit, signal cancellation, successful publication, rerun and a destination appearing during verification. These do not constitute upstream Laravel compatibility evidence.

```
# Explicitly downloads official Laravel and its upstream Composer dependencies.
RUN_CREATE_INTEGRATION=1 php tests/create-real.php
RUN_CREATE_INTEGRATION=1 php tests/create-real.php "$PWD/build/laravel-setup.phar"
```

The real integration harness boots both endpoints after publication, reruns both upstream example tests, verifies rerun preservation and scans for utility dependencies, staging paths and verification residue. It deletes only its own isolated fixture directory. GitHub CI runs this integration harness against the PHAR, along with source tests, packaging inspection, build reproducibility, PHPStan and Pint. Cloud wrapper usage is documented in [cloud-environment.md](cloud-environment.md); put environment assignments after the wrapper's `env` argument so they enter the container.
