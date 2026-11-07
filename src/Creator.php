<?php

declare(strict_types=1);

namespace LaravelSetup;

use FilesystemIterator;
use RuntimeException;
use Symfony\Component\Process\Process;

/** Creates only a new official Laravel application; never mutates an existing one. */
final class Creator
{
    public const SKELETON = 'v12.12.2';

    private bool $cancelled = false;

    /** @param list<string> $composerCommand */
    public function __construct(private readonly array $composerCommand = [], private readonly int $timeout = 600) {}

    public function cancel(): void
    {
        $this->cancelled = true;
    }

    /** @return array{path: string, laravel_skeleton: string, verified: list<string>, engineering_preset: false, migrations_run: false} */
    public function create(string $path): array
    {
        $composerCommand = $this->composerCommand ?: [is_executable('/usr/local/bin/composer') ? '/usr/local/bin/composer' : '/usr/bin/composer'];
        $target = $this->target($path);
        $parent = dirname($target);
        $identity = stat($parent);
        $temporary = realpath(sys_get_temp_dir());
        if ($temporary === false || stat($temporary)['dev'] !== $identity['dev'] || $temporary === $target || str_starts_with($temporary, $target.'/')) {
            throw new RuntimeException('External temporary storage on the target filesystem is required (set TMPDIR).');
        }
        if (PHP_OS_FAMILY !== 'Linux' || PHP_VERSION_ID < 80300 || PHP_VERSION_ID >= 80500) {
            throw new RuntimeException('Creation currently supports Linux with PHP 8.3 or 8.4 only.');
        }
        foreach (['ctype', 'curl', 'dom', 'fileinfo', 'filter', 'hash', 'mbstring', 'openssl', 'pcre', 'PDO', 'pdo_sqlite', 'session', 'tokenizer', 'xml', 'pcntl', 'posix'] as $extension) {
            if (! extension_loaded($extension)) {
                throw new RuntimeException('Required PHP extension is unavailable: '.$extension.'.');
            }
        }
        if (! extension_loaded('zip') && ! is_executable('/usr/bin/unzip') && ! is_executable('/bin/unzip')) {
            throw new RuntimeException('Composer requires the zip extension or a trusted unzip executable.');
        }
        $mover = is_executable('/usr/bin/mv') ? '/usr/bin/mv' : '/bin/mv';
        if (! is_executable($mover) || ! is_executable(PHP_BINARY)) {
            throw new RuntimeException('Trusted PHP and GNU mv executables are required.');
        }
        $stage = $temporary.'/laravel-setup-create-'.bin2hex(random_bytes(12));
        if (! mkdir($stage, 0700)) {
            throw new RuntimeException('Private staging directory could not be created.');
        }
        try {
            mkdir($stage.'/home', 0700);
            mkdir($stage.'/cache', 0700);
            $environment = $this->environment($stage);
            $mv = $this->run([$mover, '--version'], $stage, $environment, 'GNU mv preflight', true);
            if (! preg_match('/mv \(GNU coreutils\) ([0-9]+\.[0-9]+)/', $mv, $match) || version_compare($match[1], '9.1', '<')) {
                throw new RuntimeException('GNU coreutils mv 9.1 or newer is required.');
            }
            $stat = is_executable('/usr/bin/stat') ? '/usr/bin/stat' : '/bin/stat';
            $filesystem = trim($this->run([$stat, '--file-system', '--format=%T', '--', $parent], $stage, $environment, 'Filesystem preflight', true));
            if (! in_array($filesystem, ['overlayfs', 'ext2/ext3'], true) || version_compare(php_uname('r'), '5.4', '<')) {
                throw new RuntimeException('Creation requires Linux 5.4+ on a qualified local overlay or ext filesystem.');
            }
            $version = $this->run([...$composerCommand, '--version', '--no-ansi'], $stage, $environment, 'Composer preflight', true);
            if (! preg_match('/Composer version 2\./', $version)) {
                throw new RuntimeException('Composer 2 is required.');
            }
            // Qualify no-clobber semantics on this filesystem before downloading anything.
            mkdir($stage.'/probe-source');
            mkdir($stage.'/probe-target');
            $this->run([$mover, '--no-clobber', '--no-target-directory', $stage.'/probe-source', $stage.'/probe-target'], $stage, $environment, 'Publication preflight');
            if (! is_dir($stage.'/probe-source') || ! is_dir($stage.'/probe-target')) {
                throw new RuntimeException('Atomic no-clobber directory publication is unavailable.');
            }
            $this->run([...$composerCommand, 'create-project', 'laravel/laravel', 'app', self::SKELETON, '--prefer-dist', '--no-interaction', '--no-progress', '--no-ansi', '--no-scripts', '--no-plugins'], $stage, $environment, 'Composer installation');
            $app = $stage.'/app';
            foreach (['', '/bootstrap', '/public', '/vendor'] as $directory) {
                if (! is_dir($app.$directory) || is_link($app.$directory)) {
                    throw new RuntimeException('Generated application has an unsafe directory.');
                }
            }
            foreach (['composer.json', 'composer.lock', 'artisan', '.env.example', 'bootstrap/app.php', 'public/index.php', 'vendor/autoload.php'] as $file) {
                if (! is_file($app.'/'.$file) || is_link($app.'/'.$file)) {
                    throw new RuntimeException('Generated application is missing an expected Laravel file.');
                }
            }
            $manifest = json_decode(file_get_contents($app.'/composer.json'), true, flags: JSON_THROW_ON_ERROR);
            $lock = json_decode(file_get_contents($app.'/composer.lock'), true, flags: JSON_THROW_ON_ERROR);
            if (($manifest['name'] ?? null) !== 'laravel/laravel' || ($manifest['require']['laravel/framework'] ?? null) !== '^12.0' || ! is_array($lock['packages'] ?? null)) {
                throw new RuntimeException('Generated Laravel manifest or lockfile is unsupported.');
            }
            foreach (array_merge($lock['packages'], $lock['packages-dev'] ?? []) as $package) {
                if (($package['name'] ?? '') === 'boncanca/laravel-setup-utility') {
                    throw new RuntimeException('Generated application contains a utility dependency.');
                }
            }
            if (file_exists($app.'/.env') || is_link($app.'/.env')) {
                throw new RuntimeException('Unexpected environment file in upstream skeleton.');
            }
            $env = file_get_contents($app.'/.env.example');
            // A usable local homepage without running any migrations or starting services.
            foreach (['SESSION_DRIVER' => 'file', 'CACHE_STORE' => 'file', 'QUEUE_CONNECTION' => 'sync'] as $key => $value) {
                $env = preg_replace('/^'.$key.'=.*$/m', $key.'='.$value, $env);
            }
            file_put_contents($app.'/.env', $env);
            chmod($app.'/.env', 0600);
            $this->run([PHP_BINARY, 'artisan', 'key:generate', '--force', '--no-interaction', '--no-ansi'], $app, $environment, 'Application key generation');
            $this->run([PHP_BINARY, 'artisan', 'package:discover', '--no-interaction', '--no-ansi'], $app, $environment, 'Laravel package discovery');
            $this->run([PHP_BINARY, '-r', self::smokeCode()], $app, $environment, 'Laravel HTTP smoke');
            $this->run([PHP_BINARY, 'artisan', 'test', '--no-ansi', '--do-not-cache-result'], $app, array_merge($environment, ['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:']), 'Laravel upstream tests');
            // Smoke/tests create runtime caches and logs containing private staging paths.
            foreach (['storage/logs', 'storage/framework/views', 'storage/framework/sessions', 'storage/framework/cache/data'] as $directory) {
                if (is_dir($app.'/'.$directory) && ! is_link($app.'/'.$directory)) {
                    foreach (new FilesystemIterator($app.'/'.$directory) as $file) {
                        if ($file->getFilename() !== '.gitignore') {
                            self::removeOwned($file->getPathname());
                        }
                    }
                }
            }
            foreach (['.phpunit.result.cache', '.phpunit.cache'] as $cache) {
                self::removeOwned($app.'/'.$cache);
            }
            if ($this->isCancelled()) {
                throw new RuntimeException('Creation cancelled before publication.');
            }
            $this->target($target);
            clearstatcache(true, $parent);
            $current = stat($parent);
            if ($current['dev'] !== $identity['dev'] || $current['ino'] !== $identity['ino']) {
                throw new RuntimeException('Target parent changed during installation.');
            }
            $appIdentity = stat($app);
            // GNU mv uses renameat2(RENAME_NOREPLACE) on qualified Linux filesystems.
            // Same device prevents fallback to copying. Never use PHP rename here.
            try {
                $this->run([$mover, '--no-clobber', '--no-target-directory', $app, $target], $stage, $environment, 'Application publication', cancellable: false);
            } catch (RuntimeException) {
                // A timeout can arrive after rename committed. Reconcile identity below.
            }
            clearstatcache();
            $published = @lstat($target);
            if (file_exists($app) || is_link($target) || $published === false || $published['dev'] !== $appIdentity['dev'] || $published['ino'] !== $appIdentity['ino']) {
                throw new RuntimeException('Destination appeared during installation; publication refused.');
            }

            return ['path' => $target, 'laravel_skeleton' => self::SKELETON, 'verified' => ['http_homepage', 'http_health', 'upstream_tests'], 'engineering_preset' => false, 'migrations_run' => false];
        } finally {
            self::removeOwned($stage);
        }
    }

    private function target(string $path): string
    {
        if ($path === '' || str_contains($path, "\0") || str_contains($path, '\\')) {
            throw new RuntimeException('Unsafe target path.');
        }
        $absolute = str_starts_with($path, '/') ? $path : getcwd().'/'.$path;
        $parts = explode('/', $absolute);
        if (in_array('..', $parts, true) || in_array('.', $parts, true) || ! preg_match('/^[A-Za-z0-9][A-Za-z0-9 _.-]*$/D', basename($absolute))) {
            throw new RuntimeException('Unsafe target path or name.');
        }
        $component = '';
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            $component .= '/'.$part;
            if (is_link($component)) {
                throw new RuntimeException('Unsafe target: symlink components are refused.');
            }
        }
        if (file_exists($absolute) || is_link($absolute)) {
            throw new RuntimeException('Target already exists; empty directories are also refused.');
        }
        $parent = realpath(dirname($absolute));
        if ($parent === false || ! is_dir($parent) || ! is_writable($parent) || (fileperms($parent) & 0022) !== 0) {
            throw new RuntimeException('Unsafe target parent: an existing private writable parent is required.');
        }

        return $parent.'/'.basename($absolute);
    }

    /** @return array<string, string|false> */
    private function environment(string $stage): array
    {
        $environment = array_fill_keys(array_unique([...array_keys(getenv()), ...array_keys($_ENV), ...array_keys($_SERVER)]), false);
        foreach (['http_proxy', 'https_proxy', 'HTTP_PROXY', 'HTTPS_PROXY', 'no_proxy', 'NO_PROXY', 'COMPOSER_CAFILE', 'SSL_CERT_FILE', 'SSL_CERT_DIR'] as $key) {
            if (getenv($key) !== false) {
                $environment[$key] = getenv($key);
            }
        }

        return array_merge($environment, ['PATH' => '/usr/local/bin:/usr/bin:/bin', 'HOME' => $stage.'/home', 'COMPOSER_HOME' => $stage.'/home', 'COMPOSER_CACHE_DIR' => $stage.'/cache', 'COMPOSER_AUTH' => '{}', 'COMPOSER_PROCESS_TIMEOUT' => (string) $this->timeout, 'LANG' => 'C.UTF-8', 'TMPDIR' => $stage]);
    }

    /**
     * @param  list<string>  $command
     * @param  array<string, string|false>  $environment
     */
    private function run(array $command, string $directory, array $environment, string $phase, bool $capture = false, bool $cancellable = true): string
    {
        if (! str_starts_with($command[0], '/') || ! is_executable($command[0])) {
            throw new RuntimeException($phase.' requires a trusted absolute executable.');
        }
        $handshake = $environment['HOME'].'/process-'.bin2hex(random_bytes(8));
        $launcher = <<<'PHP'
if (posix_setsid() !== getmypid()) { exit(125); }
$handle = fopen($argv[1], 'x');
if ($handle === false) { exit(125); }
chmod($argv[1], 0600);
fwrite($handle, (string) getmypid());
fclose($handle);
pcntl_exec($argv[2], array_slice($argv, 3));
exit(126);
PHP;
        $process = new Process([PHP_BINARY, '-r', $launcher, $handshake, ...$command], $directory, $environment, timeout: $this->timeout);
        if (! $capture) {
            $process->disableOutput();
        }
        $group = null;
        $leader = null;
        try {
            if ($cancellable && $this->isCancelled()) {
                throw new RuntimeException('Cancelled.');
            }
            $process->start();
            $leader = $process->getPid();
            while ($process->isRunning()) {
                $group ??= $this->processGroup($handshake, $leader);
                if ($cancellable && $this->isCancelled()) {
                    throw new RuntimeException('Cancelled.');
                }
                $process->checkTimeout();
                usleep(50000);
            }
            $group ??= $this->processGroup($handshake, $leader);
            if (! $process->isSuccessful() || $group === null) {
                throw new RuntimeException('Unsuccessful process.');
            }

            return $capture ? $process->getOutput() : '';
        } catch (\Throwable) {
            throw new RuntimeException($phase.' failed, timed out or was cancelled. No unverified application was published.');
        } finally {
            $group ??= $this->processGroup($handshake, $leader);
            if ($group !== null) {
                // Kill the entire private session, including surviving extraction children.
                @posix_kill(-$group, SIGTERM);
                $deadline = microtime(true) + 0.25;
                while (@posix_kill(-$group, 0) && microtime(true) < $deadline) {
                    usleep(10000);
                }
                @posix_kill(-$group, SIGKILL);
            }
            if ($process->isRunning()) {
                $process->stop(1);
            }
            if (is_file($handshake)) {
                unlink($handshake);
            }
        }
    }

    private function processGroup(string $handshake, ?int $leader): ?int
    {
        if (! is_file($handshake) || is_link($handshake)) {
            return null;
        }
        $raw = file_get_contents($handshake, length: 32);
        if ($raw === false || ! ctype_digit($raw) || (int) $raw <= 1 || (int) $raw === posix_getpgrp() || ($leader !== null && (int) $raw !== $leader)) {
            return null;
        }

        return (int) $raw;
    }

    /** @phpstan-impure */
    private function isCancelled(): bool
    {
        if (function_exists('pcntl_signal_dispatch')) {
            pcntl_signal_dispatch();
        }

        return $this->cancelled;
    }

    public static function smokeCode(): string
    {
        return <<<'PHP'
require 'vendor/autoload.php';
foreach (['/', '/up'] as $uri) {
    $app = require 'bootstrap/app.php';
    if (!$app instanceof \Illuminate\Foundation\Application) { exit(10); }
    $kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
    $request = \Illuminate\Http\Request::create($uri);
    $response = $kernel->handle($request);
    if ($response->getStatusCode() !== 200) { exit(11); }
    $kernel->terminate($request, $response);
}
PHP;
    }

    private static function removeOwned(string $path): void
    {
        if (is_dir($path) && ! is_link($path)) {
            foreach (new FilesystemIterator($path) as $file) {
                self::removeOwned($file->getPathname());
            }
            rmdir($path);
        } elseif (file_exists($path) || is_link($path)) {
            unlink($path);
        }
    }
}
