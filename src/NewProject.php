<?php

declare(strict_types=1);

namespace LaravelSetup;

use FilesystemIterator;
use RuntimeException;
use Symfony\Component\Process\Process;

final class NewProject
{
    private const SKELETON = '13.11.0';

    public function __construct(
        private readonly array $composerCommand = ['composer'],
        private readonly array $phpCommand = [PHP_BINARY],
        private readonly int $timeout = 600,
    ) {
    }

    public function plan(string $destination, string $engineering = 'none'): array
    {
        if (!in_array($engineering, ['none', 'strict'], true)) {
            throw new RuntimeException('Engineering selection must be none or strict.');
        }
        $name = basename($destination);
        $parent = realpath(dirname($destination));
        if (!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9 ._-]{0,63}$/D', $name) || trim($name) !== $name || $parent === false || !is_dir($parent)) {
            throw new RuntimeException('Use a simple application name inside an existing parent directory.');
        }
        $target = $parent.DIRECTORY_SEPARATOR.$name;
        if (file_exists($target) || is_link($target)) {
            throw new RuntimeException('Destination already exists; it will not be overwritten.');
        }

        return [
            'schema_version' => 1,
            'destination' => $target,
            'foundation' => 'raw-laravel',
            'engineering' => $engineering,
            'engineering_packages' => $engineering === 'strict' ? EngineeringPreset::PACKAGES : [],
            'engineering_changes' => $engineering === 'strict' ? 'Install selected runtime/dev tools, format new scaffold, generate constitution and CI; scoped PHPUnit unlock may select a Pest-compatible version.' : 'none',
            'skeleton_package' => 'laravel/laravel',
            'skeleton_version' => self::SKELETON,
            'composer_scripts' => $engineering === 'strict' ? ['fix', 'check'] : false,
            'composer_lifecycle_scripts' => false,
            'composer_plugins' => false,
            'database_migrations' => false,
            'frontend_installation' => false,
            'verification' => ['artisan_version', 'route_list', $engineering === 'strict' ? 'composer_check' : 'upstream_phpunit'],
            'retained' => ['upstream_application', 'upstream_dependencies', 'composer_lock', 'local_environment'],
            'note' => 'Apply executes upstream dependency code for boot and tests; fresh dependency resolution is time-sensitive.',
        ];
    }

    public function create(string $destination, string $engineering = 'none'): array
    {
        $plan = $this->plan($destination, $engineering);
        $target = $plan['destination'];
        $parent = dirname($target);
        $stage = $parent.'/.lsu-new-'.bin2hex(random_bytes(12));
        if (!mkdir($stage, 0700)) {
            throw new RuntimeException('Could not create private staging directory.');
        }
        $lock = null;
        try {
            $lock = $this->lock($target);
            $this->plan($target, $engineering);
            mkdir($stage.'/composer-home', 0700);
            mkdir($stage.'/composer-cache', 0700);
            $environment = [];
            foreach (array_keys(getenv()) as $key) {
                if ($key === 'COMPOSER' || str_starts_with($key, 'COMPOSER_') || str_starts_with($key, 'GIT_') || $key === 'SSH_ASKPASS') {
                    $environment[$key] = false;
                }
            }
            $environment = array_merge($environment, [
                'COMPOSER_HOME' => $stage.'/composer-home',
                'COMPOSER_CACHE_DIR' => $stage.'/composer-cache',
                'COMPOSER_AUTH' => '{}',
                'COMPOSER_PROCESS_TIMEOUT' => '120',
            ]);
            $ca = getenv('COMPOSER_CAFILE');
            if ($ca !== false) {
                $environment['COMPOSER_CAFILE'] = $ca;
            }
            $app = $stage.'/app';
            $this->run([...$this->composerCommand, 'create-project', 'laravel/laravel', $app, self::SKELETON, '--no-scripts', '--no-plugins', '--no-interaction', '--prefer-dist', '--no-progress'], $stage, $environment, 'Dependency installation');
            if (is_link($app) || !is_dir($app)) {
                throw new RuntimeException('Scaffold output is unsupported.');
            }
            $facts = (new Doctor())->inspect($app);
            if (!$facts['requirements_satisfied'] || $facts['project']['locked_laravel_version'] === null) {
                throw new RuntimeException('Generated application has unmet runtime requirements or no locked framework.');
            }
            $example = $app.'/.env.example';
            if (!is_file($example) || is_link($example) || file_exists($app.'/.env') || is_link($app.'/.env')) {
                throw new RuntimeException('Scaffold environment is unsupported.');
            }
            $env = file_get_contents($example, false, null, 0, 1048577);
            if ($env === false || strlen($env) > 1048576) {
                throw new RuntimeException('Scaffold environment exceeds supported limits.');
            }
            $key = 'base64:'.base64_encode(random_bytes(32));
            foreach (['APP_KEY' => $key, 'SESSION_DRIVER' => 'file', 'CACHE_STORE' => 'file', 'QUEUE_CONNECTION' => 'sync'] as $name => $value) {
                $count = 0;
                $env = preg_replace('/^'.preg_quote($name, '/').'=.*$/m', $name.'='.$value, $env, -1, $count);
                if ($count !== 1) {
                    throw new RuntimeException('Scaffold environment settings differ from the qualified recipe.');
                }
            }
            $handle = fopen($app.'/.env', 'x');
            if ($handle === false) {
                throw new RuntimeException('Could not prepare local environment.');
            }
            try {
                if (!chmod($app.'/.env', 0600) || fwrite($handle, $env) !== strlen($env)) {
                    throw new RuntimeException('Could not write private local environment.');
                }
            } finally {
                fclose($handle);
            }
            // Application-specific shell variables must not redirect bootstrap,
            // cache or storage into a different application on the host.
            $bootEnvironment = $environment;
            foreach (array_keys(getenv()) as $name) {
                if (!in_array($name, ['PATH', 'HOME', 'USER', 'LOGNAME', 'TMPDIR', 'TMP', 'TEMP', 'SystemRoot', 'SYSTEMROOT', 'WINDIR', 'PATHEXT', 'COMSPEC', 'LANG', 'LC_ALL', 'TZ', 'PHPRC', 'PHP_INI_SCAN_DIR', 'LD_LIBRARY_PATH', 'DYLD_LIBRARY_PATH', 'SSL_CERT_FILE', 'SSL_CERT_DIR', 'CURL_CA_BUNDLE'], true)
                    && !preg_match('/(?:^|_)proxy$/iD', $name)) {
                    $bootEnvironment[$name] = false;
                }
            }
            $bootEnvironment = array_merge($bootEnvironment, ['APP_ENV' => 'local', 'APP_KEY' => $key, 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'SESSION_DRIVER' => 'array', 'CACHE_STORE' => 'array', 'QUEUE_CONNECTION' => 'sync']);
            if ($engineering === 'strict') {
                (new EngineeringPreset())->prepare($app);
                $this->run([...$this->composerCommand, 'update', ...EngineeringPreset::PACKAGES, '--no-scripts', '--no-plugins', '--no-interaction', '--no-progress'], $app, $environment, 'Engineering dependency installation');
                $this->run([...$this->phpCommand, 'artisan', 'package:discover', '--no-ansi'], $app, $bootEnvironment, 'Engineering package discovery');
                $this->run([...$this->composerCommand, 'run-script', 'fix', '--no-plugins', '--no-interaction'], $app, array_merge($bootEnvironment, $environment), 'New scaffold formatting');
            }
            $this->run([...$this->phpCommand, 'artisan', '--version', '--no-ansi'], $app, $bootEnvironment, 'Application boot');
            $this->run([...$this->phpCommand, 'artisan', 'route:list', '--json', '--no-ansi'], $app, $bootEnvironment, 'Route verification');
            if ($engineering === 'strict') {
                $this->run([...$this->composerCommand, 'run-script', 'check', '--no-plugins', '--no-interaction'], $app, array_merge($bootEnvironment, $environment, ['APP_ENV' => 'testing']), 'Engineering verification');
            } else {
                $this->run([...$this->phpCommand, 'vendor/bin/phpunit'], $app, array_merge($bootEnvironment, ['APP_ENV' => 'testing']), 'Upstream tests');
            }
            $facts = (new Doctor())->inspect($app);
            if (!$facts['requirements_satisfied']) {
                throw new RuntimeException('Configured application has unmet runtime requirements.');
            }
            $this->cleanVerificationCaches($app);
            $this->plan($target, $engineering);
            // Reserve the destination atomically before rename so an ordinary
            // concurrent creator cannot have its empty directory replaced.
            if (!@mkdir($target, 0700)) {
                throw new RuntimeException('Destination was created concurrently; it was not overwritten.');
            }
            $reservation = lstat($target);
            if (!@rename($app, $target)) {
                clearstatcache(true, $target);
                $current = lstat($target);
                if ($current !== false && $reservation !== false && $current['ino'] === $reservation['ino'] && $current['dev'] === $reservation['dev']) {
                    @rmdir($target); // Only removes our reservation if still empty.
                }
                throw new RuntimeException('Could not publish verified application.');
            }

            return [
                'schema_version' => 1,
                'status' => 'created',
                'engineering' => $engineering,
                'destination' => $target,
                'skeleton_version' => self::SKELETON,
                'locked_laravel_version' => $facts['project']['locked_laravel_version'],
                'composer_lock_sha256' => hash_file('sha256', $target.'/composer.lock'),
                'checks' => $plan['verification'],
                'database_migrations' => false,
                'frontend_installation' => false,
                'installer_residue' => false,
            ];
        } finally {
            if (is_resource($lock)) {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
            self::removeStage($stage);
        }
    }

    private function run(array $command, string $directory, array $environment, string $step): void
    {
        $process = new Process($command, $directory, $environment, timeout: $this->timeout);
        $process->disableOutput();
        try {
            $process->run();
        } catch (RuntimeException) {
            throw new RuntimeException($step.' failed or timed out. Destination was not published.');
        }
        if (!$process->isSuccessful()) {
            throw new RuntimeException($step.' failed. Destination was not published.');
        }
    }

    private function cleanVerificationCaches(string $app): void
    {
        foreach (['storage', 'storage/framework'] as $directory) {
            if (is_link($app.'/'.$directory)) {
                throw new RuntimeException('Generated runtime directory must not be a symlink.');
            }
        }
        foreach (['storage/logs', 'storage/framework/views', 'storage/framework/sessions', 'storage/framework/cache'] as $directory) {
            $path = $app.'/'.$directory;
            if (is_link($path)) {
                throw new RuntimeException('Generated runtime directory must not be a symlink.');
            }
            if (is_dir($path)) {
                self::clearRuntimeDirectory($path);
            }
        }
        foreach (['.phpunit.result.cache', '.phpunit.cache'] as $cache) {
            if (file_exists($app.'/'.$cache) || is_link($app.'/'.$cache)) {
                self::removeStage($app.'/'.$cache);
            }
        }
    }

    private static function clearRuntimeDirectory(string $path): void
    {
        foreach (new FilesystemIterator($path) as $file) {
            if ($file->getFilename() === '.gitignore' && !$file->isLink()) {
                continue;
            }
            $child = $file->getPathname();
            if ($file->isDir() && !$file->isLink()) {
                self::clearRuntimeDirectory($child);
                if (count(scandir($child)) === 2) {
                    rmdir($child);
                }
            } else {
                unlink($child);
            }
        }
    }

    private function lock(string $target)
    {
        $directory = sys_get_temp_dir().'/lsu-new-locks-'.(function_exists('posix_geteuid') ? posix_geteuid() : hash('sha256', PHP_BINARY));
        if (!is_dir($directory) && !@mkdir($directory, 0700) && !is_dir($directory)) {
            throw new RuntimeException('Could not create external lock directory.');
        }
        if (is_link($directory) || (fileperms($directory) & 0777) !== 0700) {
            throw new RuntimeException('External lock directory is not private.');
        }
        $path = $directory.'/'.hash('sha256', $target).'.lock';
        if (is_link($path)) {
            throw new RuntimeException('External lock path is unsupported.');
        }
        $lock = fopen($path, 'c');
        if ($lock === false) {
            throw new RuntimeException('Could not open external lock.');
        }
        chmod($path, 0600);
        if (!flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);
            throw new RuntimeException('Another creation is running for this destination.');
        }

        return $lock;
    }

    private static function removeStage(string $path): void
    {
        if (is_link($path) || !is_dir($path)) {
            unlink($path);

            return;
        }
        foreach (new FilesystemIterator($path) as $file) {
            self::removeStage($file->getPathname());
        }
        rmdir($path);
    }
}
