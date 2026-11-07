<?php

declare(strict_types=1);

namespace LaravelSetup;

use FilesystemIterator;
use JsonException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Symfony\Component\Process\Process;

final class Resolver
{
    public function __construct(private readonly array $composerCommand = ['composer'], private readonly int $timeout = 120)
    {
    }

    public function resolve(string $path, array $plan): array
    {
        $planner = new Planner();
        $planner->validate($path, $plan);
        $snapshot = ProjectSnapshot::read($path, true);
        if ($snapshot['inputs'] !== $plan['inputs']) {
            throw new RuntimeException('Project changed before staging. Replan before proceeding.');
        }
        $manifest = $snapshot['manifest'];
        $this->guardManifest($manifest);
        // Decode as objects for staging: associative decoding turns {} into [].
        // Preserve Composer's object-valued fields, including empty objects.
        $stagedManifest = json_decode(
            $snapshot['manifest_contents'],
            flags: JSON_THROW_ON_ERROR
        );
        foreach ($plan['packages'] as $package) {
            $type = $package['dependency_type'];
            $stagedManifest->{$type} ??= new \\stdClass();
            $stagedManifest->{$type}->{$package['name']} = $package['constraint'];
        }
        $temporary = realpath(sys_get_temp_dir());
        if ($temporary === false || $temporary === $snapshot['root'] || str_starts_with($temporary, rtrim($snapshot['root'], DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('External temporary storage is required.');
        }
        $stage = $temporary.'/laravel-setup-resolve-'.bin2hex(random_bytes(12));
        if (!mkdir($stage, 0700)) {
            throw new RuntimeException('Private staging directory could not be created.');
        }
        try {
            mkdir($stage.'/home', 0700);
            mkdir($stage.'/cache', 0700);
            file_put_contents($stage.'/composer.json', json_encode($stagedManifest, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
            if ($snapshot['lock_contents'] !== null) {
                file_put_contents($stage.'/composer.lock', $snapshot['lock_contents']);
            }
            $arguments = [...$this->composerCommand, 'update', '--no-plugins', '--no-scripts', '--no-interaction', '--no-install', '--no-progress', '--no-audit', '--prefer-dist'];
            if ($snapshot['lock_contents'] !== null) {
                $arguments = [...$arguments, ...array_column($plan['packages'], 'name')];
            }
            $environment = [];
            foreach (array_keys(getenv()) as $key) {
                if (str_starts_with($key, 'COMPOSER_') || $key === 'COMPOSER' || str_starts_with($key, 'GIT_') || $key === 'SSH_ASKPASS') {
                    $environment[$key] = false;
                }
            }
            $ca = getenv('COMPOSER_CAFILE');
            $environment = array_merge($environment, [
                'COMPOSER' => 'composer.json',
                'COMPOSER_HOME' => $stage.'/home',
                'COMPOSER_CACHE_DIR' => $stage.'/cache',
                'COMPOSER_VENDOR_DIR' => $stage.'/vendor',
                'COMPOSER_AUTH' => '{}',
                'COMPOSER_PROCESS_TIMEOUT' => '60',
                'COMPOSER_MEMORY_LIMIT' => '256M',
                'COMPOSER_ROOT_VERSION' => '1.0.0',
            ]);
            if ($ca !== false) {
                $environment['COMPOSER_CAFILE'] = $ca;
            }
            $process = new Process($arguments, $stage, $environment, timeout: $this->timeout);
            $process->disableOutput();
            try {
                $process->run();
            } catch (RuntimeException) {
                throw new RuntimeException('Composer resolution failed or timed out. No target changes were applied.');
            }
            if (!$process->isSuccessful()) {
                throw new RuntimeException('Composer resolution failed. No target changes were applied.');
            }
            $planner->validate($path, $plan);
            $lockPath = $stage.'/composer.lock';
            if (!is_file($lockPath) || is_link($lockPath)) {
                throw new RuntimeException('Composer did not produce an acceptable lockfile.');
            }
            $lock = @file_get_contents($lockPath, false, null, 0, 16777217);
            if ($lock === false || strlen($lock) > 16777216) {
                throw new RuntimeException('Resolved lockfile is unreadable or exceeds 16 MiB.');
            }
            try {
                $data = json_decode($lock, true, flags: JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                throw new RuntimeException('Composer produced invalid lockfile JSON.');
            }
            if (!is_array($data) || !is_array($data['packages'] ?? null) || !is_array($data['packages-dev'] ?? null)) {
                throw new RuntimeException('Composer produced an unsupported lockfile.');
            }
            $packages = [];
            foreach (array_merge($data['packages'], $data['packages-dev']) as $package) {
                if (!is_array($package) || !is_string($package['name'] ?? null) || !preg_match('~^[a-z0-9_.-]+/[a-z0-9_.-]+$~D', $package['name']) || !is_string($package['version'] ?? null) || strlen($package['version']) > 128) {
                    throw new RuntimeException('Composer produced invalid package metadata.');
                }
                $packages[] = ['name' => $package['name'], 'version' => $package['version']];
            }
            usort($packages, static fn (array $left, array $right): int => strcmp($left['name'], $right['name']));

            return [
                'schema_version' => 1,
                'plan_sha256' => hash('sha256', json_encode($plan, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
                'resolved_packages' => $packages,
                'lock_sha256' => hash('sha256', $lock),
                'lock_retained' => false,
                'replayable' => false,
                'compatibility' => ['dependencies' => 'solved_in_staging', 'integration' => 'unverified'],
                'apply_ready' => false,
            ];
        } finally {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($stage, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($files as $file) {
                if ($file->isDir() && !$file->isLink()) {
                    rmdir($file->getPathname());
                } else {
                    unlink($file->getPathname());
                }
            }
            rmdir($stage);
        }
    }

    private function guardManifest(array $manifest): void
    {
        $allowed = ['$schema', 'name', 'description', 'type', 'keywords', 'license', 'authors', 'homepage', 'support', 'funding', 'require', 'require-dev', 'autoload', 'autoload-dev', 'scripts', 'extra', 'config', 'minimum-stability', 'prefer-stable', 'repositories'];
        if (array_diff(array_keys($manifest), $allowed) !== [] || ($manifest['repositories'] ?? []) !== []) {
            throw new RuntimeException('Unsupported manifest settings or custom repositories. Only default public Packagist is supported.');
        }
        $config = $manifest['config'] ?? [];
        if (!is_array($config) || array_diff(array_keys($config), ['optimize-autoloader', 'preferred-install', 'sort-packages', 'allow-plugins']) !== []) {
            throw new RuntimeException('Unsupported Composer configuration. Custom paths, platform overrides and transport hooks are not supported.');
        }
        if (isset($config['preferred-install']) && $config['preferred-install'] !== 'dist') {
            throw new RuntimeException('Only dist preference is supported.');
        }
        if (isset($manifest['minimum-stability']) && $manifest['minimum-stability'] !== 'stable') {
            throw new RuntimeException('Only stable minimum stability is supported.');
        }
    }
}
