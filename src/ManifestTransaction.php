<?php

declare(strict_types=1);

namespace LaravelSetup;

use Closure;
use Composer\Semver\Semver;
use JsonException;
use RuntimeException;
use Throwable;

final class ManifestTransaction
{
    private const NAMES = ['composer.json', 'composer.lock'];

    public function __construct(private readonly ?Closure $afterWrite = null)
    {
    }

    public static function retain(array $snapshot, array $plan, string $manifest, string $lock, string $destination): string
    {
        (new Planner())->validate($snapshot['root'], $plan);
        $destination = self::externalDirectory($destination, $snapshot['root'], true);
        if (file_exists($destination) || is_link($destination) || !mkdir($destination, 0700)) {
            throw new RuntimeException('Bundle destination must be a new external directory.');
        }
        try {
            $files = ['plan.json' => self::json($plan), 'original-composer.json' => $snapshot['composer_contents'], 'composer.json' => $manifest, 'composer.lock' => $lock];
            if ($snapshot['lock_contents'] !== null) {
                $files['original-composer.lock'] = $snapshot['lock_contents'];
            }
            $hashes = [];
            foreach ($files as $name => $contents) {
                self::privateWrite($destination.'/'.$name, $contents);
                $hashes[$name] = hash('sha256', $contents);
            }
            $modes = [];
            foreach (self::NAMES as $name) {
                $state = self::targetState($snapshot['root'].'/'.$name);
                $modes[$name] = $state['mode'] ?? 0600;
            }
            self::privateWrite($destination.'/bundle.json', self::json(['schema_version' => 1, 'root' => $snapshot['root'], 'hashes' => $hashes, 'original_lock_present' => $snapshot['lock_contents'] !== null, 'modes' => $modes]));
            self::load($snapshot['root'], $destination);
        } catch (Throwable $error) {
            foreach (glob($destination.'/*') as $file) {
                unlink($file);
            }
            rmdir($destination);
            throw new RuntimeException('Resolution bundle could not be retained safely.');
        }

        return $destination;
    }

    public function apply(string $path, string $bundle): array
    {
        $root = realpath($path);
        if ($root === false || !is_dir($root)) {
            throw new RuntimeException('Project directory does not exist.');
        }
        $data = self::load($root, $bundle);
        $lock = self::acquire($root);
        try {
            $current = self::states($root);
            self::journal($data);
            if ($current === $data['resolved_states']) {
                self::saveJournal($data, 'applied');

                return self::summary('already_applied');
            }
            if ($current !== $data['original_states']) {
                throw new RuntimeException('Target changed or is partially applied. Recover or replan before applying.');
            }
            (new Planner())->validate($root, $data['plan']);
            self::saveJournal($data, 'prepared');
            try {
                foreach (self::NAMES as $name) {
                    self::replace($root.'/'.$name, $data['files'][$name], $data['modes'][$name], $data['original_states'][$name]);
                    if ($this->afterWrite !== null) {
                        ($this->afterWrite)($name);
                    }
                }
                self::saveJournal($data, 'applied');
            } catch (Throwable) {
                try {
                    self::restore($root, $data);
                    self::saveJournal($data, 'recovered');
                } catch (Throwable) {
                    throw new RuntimeException('Apply failed; automatic recovery refused changed files. External journal retained.');
                }
                throw new RuntimeException('Apply failed; original manifests restored.');
            }

            return self::summary('manifests_applied');
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function recover(string $path, string $bundle): array
    {
        $root = realpath($path);
        if ($root === false || !is_dir($root)) {
            throw new RuntimeException('Project directory does not exist.');
        }
        $data = self::load($root, $bundle, recovery: true);
        $lock = self::acquire($root);
        try {
            if (self::journal($data) === null) {
                throw new RuntimeException('No recovery journal exists for this bundle.');
            }
            self::restore($root, $data);
            self::saveJournal($data, 'recovered');

            return self::summary('manifests_recovered');
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private static function load(string $root, string $bundle, bool $recovery = false): array
    {
        $bundle = self::externalDirectory($bundle, $root, false);
        if ((fileperms($bundle) & 0777) !== 0700) {
            throw new RuntimeException('Bundle directory must have private 0700 permissions.');
        }
        $metaBytes = self::read($bundle.'/bundle.json', 1048576);
        $meta = self::decode($metaBytes);
        if (($meta['schema_version'] ?? null) !== 1 || ($meta['root'] ?? null) !== $root || !is_bool($meta['original_lock_present'] ?? null) || !is_array($meta['hashes'] ?? null) || !is_array($meta['modes'] ?? null)) {
            throw new RuntimeException('Unsupported resolution bundle.');
        }
        $names = ['plan.json', 'original-composer.json', 'composer.json', 'composer.lock'];
        if ($meta['original_lock_present']) {
            $names[] = 'original-composer.lock';
        }
        if (array_keys($meta['hashes']) !== $names) {
            throw new RuntimeException('Bundle member list is unsupported.');
        }
        $files = [];
        foreach ($names as $name) {
            $files[$name] = self::read($bundle.'/'.$name, str_ends_with($name, '.lock') ? 16777216 : 1048576);
            if ($meta['hashes'][$name] !== hash('sha256', $files[$name])) {
                throw new RuntimeException('Bundle integrity check failed.');
            }
        }
        $plan = null;
        $originalHashes = ['composer.json' => hash('sha256', $files['original-composer.json']), 'composer.lock' => isset($files['original-composer.lock']) ? hash('sha256', $files['original-composer.lock']) : null];

        // Recovery must work after PHP or the recipe catalog changes. It uses only
        // retained byte hashes, modes, the journal and current target file states.
        if (!$recovery) {
        $plan = self::decode($files['plan.json']);
        $original = self::decode($files['original-composer.json']);
        Resolver::guardManifest($original);
        $php = $original['require']['php'] ?? null;
        $laravel = $original['require']['laravel/framework'] ?? null;
        if (!is_string($php) || !is_string($laravel)) {
            throw new RuntimeException('Original bundle manifest is invalid.');
        }
        $snapshot = ['root' => $root, 'manifest' => $original, 'php_constraint' => $php, 'laravel_constraint' => $laravel, 'inputs' => $originalHashes];
        if (!is_array($plan['recipes'] ?? null) || !is_string($plan['catalog_channel'] ?? null) || (new Planner())->fromSnapshot($snapshot, $plan['recipes'], $plan['catalog_channel']) !== $plan) {
            throw new RuntimeException('Bundle is not bound to the original plan and current catalog/runtime.');
        }
        $expected = $original;
        foreach ($plan['packages'] as $package) {
            $expected[$package['dependency_type']][$package['name']] = $package['constraint'];
        }
        if (self::decode($files['composer.json']) !== $expected) {
            throw new RuntimeException('Resolved manifest contains changes outside approved package requirements.');
        }
        $resolved = self::decode($files['composer.lock']);
        if (!is_array($resolved['packages'] ?? null) || !is_array($resolved['packages-dev'] ?? null)) {
            throw new RuntimeException('Resolved lockfile shape is unsupported.');
        }
        $versions = [];
        $production = array_column($resolved['packages'], 'name');
        foreach (array_merge($resolved['packages'], $resolved['packages-dev']) as $package) {
            if (!is_array($package) || !is_string($package['name'] ?? null) || !is_string($package['version'] ?? null) || isset($versions[$package['name']])) {
                throw new RuntimeException('Resolved lockfile package metadata is invalid.');
            }
            $versions[$package['name']] = $package['version'];
        }
        foreach (['require', 'require-dev'] as $section) {
            $requirements = $expected[$section] ?? [];
            if (!is_array($requirements)) {
                throw new RuntimeException('Resolved dependency sections are invalid.');
            }
            foreach ($requirements as $name => $constraint) {
                if (is_string($name) && preg_match('~^(php(?:-.*)?|ext-.*|lib-.*|composer(?:-plugin-api|-runtime-api)?)$~D', $name)) {
                    continue;
                }
                try {
                    $satisfied = is_string($name) && is_string($constraint) && isset($versions[$name]) && Semver::satisfies($versions[$name], $constraint)
                        && ($section !== 'require' || in_array($name, $production, true));
                } catch (\UnexpectedValueException) {
                    $satisfied = false;
                }
                if (!$satisfied) {
                    throw new RuntimeException('Resolved lockfile does not directly satisfy all manifest requirements; virtual replacements are unsupported.');
                }
            }
        }
        }
        $originalStates = $resolvedStates = [];
        foreach (self::NAMES as $name) {
            $mode = $meta['modes'][$name] ?? null;
            if (!is_int($mode) || $mode < 0 || $mode > 0777) {
                throw new RuntimeException('Bundle permission metadata is invalid.');
            }
            $originalStates[$name] = $originalHashes[$name] === null ? null : ['hash' => $originalHashes[$name], 'mode' => $mode];
            $resolvedStates[$name] = ['hash' => hash('sha256', $files[$name]), 'mode' => $mode];
        }

        return ['directory' => $bundle, 'id' => hash('sha256', $metaBytes), 'plan' => $plan, 'files' => $files, 'modes' => $meta['modes'], 'original_states' => $originalStates, 'resolved_states' => $resolvedStates];
    }

    private static function restore(string $root, array $data): void
    {
        $states = self::states($root);
        foreach (self::NAMES as $name) {
            if ($states[$name] !== $data['original_states'][$name] && $states[$name] !== $data['resolved_states'][$name]) {
                throw new RuntimeException('Recovery refused user edits.');
            }
        }
        foreach (self::NAMES as $name) {
            if ($states[$name] === $data['original_states'][$name]) {
                continue;
            }
            $bytes = $data['files']['original-'.$name] ?? null;
            self::replace($root.'/'.$name, $bytes, $data['modes'][$name], $states[$name]);
        }
    }

    private static function replace(string $path, ?string $bytes, int $mode, ?array $expected): void
    {
        if (self::targetState($path) !== $expected) {
            throw new RuntimeException('Target changed immediately before write.');
        }
        if ($bytes === null) {
            if ($expected !== null && !unlink($path)) {
                throw new RuntimeException('Could not restore absent lockfile.');
            }

            return;
        }
        $temporary = dirname($path).'/.laravel-setup-write-'.bin2hex(random_bytes(12));
        try {
            self::privateWrite($temporary, $bytes);
            if (!chmod($temporary, $mode) || self::targetState($path) !== $expected || !rename($temporary, $path)) {
                throw new RuntimeException('Target changed or atomic manifest replacement failed.');
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }

    private static function states(string $root): array
    {
        return ['composer.json' => self::targetState($root.'/composer.json'), 'composer.lock' => self::targetState($root.'/composer.lock')];
    }

    private static function targetState(string $path): ?array
    {
        clearstatcache(true, $path);
        if (!file_exists($path) && !is_link($path)) {
            return null;
        }
        if (is_link($path) || !is_file($path) || (lstat($path)['nlink'] ?? 0) !== 1) {
            throw new RuntimeException('Manifest transaction requires regular, unlinked target files.');
        }

        return ['hash' => hash('sha256', self::read($path, 16777216)), 'mode' => fileperms($path) & 0777];
    }

    private static function acquire(string $root)
    {
        $temporary = realpath(sys_get_temp_dir());
        if ($temporary === false || self::inside($temporary, $root)) {
            throw new RuntimeException('External lock storage is required.');
        }
        // A private directory shared by every account leaves subsequent users
        // unable to acquire locks in the system-wide temporary directory.
        $probe = tmpfile();
        $owner = $probe === false ? null : (fstat($probe)['uid'] ?? null);
        if (is_resource($probe)) {
            fclose($probe);
        }
        if (!is_int($owner) || $owner < 0) {
            throw new RuntimeException('Effective lock owner could not be determined.');
        }
        $directory = $temporary.'/laravel-setup-target-locks-'.$owner;
        if (!file_exists($directory) && !mkdir($directory, 0700)) {
            throw new RuntimeException('External lock directory could not be created.');
        }
        if (is_link($directory) || !is_dir($directory) || (fileperms($directory) & 0777) !== 0700 || $owner === null || fileowner($directory) !== $owner) {
            throw new RuntimeException('External lock directory is unsafe.');
        }
        $path = $directory.'/'.hash('sha256', $root).'.lock';
        if (is_link($path) || (file_exists($path) && (!is_file($path) || fileowner($path) !== $owner || (lstat($path)['nlink'] ?? 0) !== 1))) {
            throw new RuntimeException('External target lock is unsafe.');
        }
        $lock = fopen($path, 'c+b');
        if ($lock === false || !chmod($path, 0600) || !flock($lock, LOCK_EX | LOCK_NB)) {
            if (is_resource($lock)) {
                fclose($lock);
            }
            throw new RuntimeException('Another manifest transaction holds the target lock.');
        }

        return $lock;
    }

    private static function journal(array $data): ?array
    {
        $file = $data['directory'].'/journal.json';
        if (!file_exists($file) && !is_link($file)) {
            return null;
        }
        $journal = self::decode(self::read($file, 1048576));
        if (($journal['bundle_id'] ?? null) !== $data['id'] || !in_array($journal['status'] ?? null, ['prepared', 'applied', 'recovered'], true)) {
            throw new RuntimeException('Recovery journal integrity is invalid.');
        }

        return $journal;
    }

    private static function saveJournal(array $data, string $status): void
    {
        $path = $data['directory'].'/journal.json';
        $temporary = $data['directory'].'/.journal-'.bin2hex(random_bytes(12));
        try {
            self::privateWrite($temporary, self::json(['schema_version' => 1, 'bundle_id' => $data['id'], 'status' => $status]));
            if (!rename($temporary, $path)) {
                throw new RuntimeException('Recovery journal could not be committed.');
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }

    private static function externalDirectory(string $path, string $root, bool $new): string
    {
        $path = str_starts_with($path, DIRECTORY_SEPARATOR) ? $path : getcwd().'/'.$path;
        for ($part = $path; dirname($part) !== $part; $part = dirname($part)) {
            if (is_link($part)) {
                throw new RuntimeException('Bundle paths may not contain symlinks.');
            }
        }
        $resolved = $new ? realpath(dirname($path)) : realpath($path);
        if ($resolved === false || !is_dir($resolved)) {
            throw new RuntimeException('Bundle parent or directory does not exist.');
        }
        $resolved = $new ? $resolved.'/'.basename($path) : $resolved;
        if (self::inside($resolved, $root)) {
            throw new RuntimeException('Bundle must remain outside the target project.');
        }

        return $resolved;
    }

    private static function inside(string $path, string $root): bool
    {
        return $path === $root || str_starts_with($path, rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR);
    }

    private static function read(string $path, int $limit): string
    {
        if (!is_file($path) || is_link($path)) {
            throw new RuntimeException('Required regular bundle or manifest file is unavailable.');
        }
        $bytes = @file_get_contents($path, false, null, 0, $limit + 1);
        if ($bytes === false || strlen($bytes) > $limit) {
            throw new RuntimeException('Bundle or manifest file exceeds supported limits or is unreadable.');
        }

        return $bytes;
    }

    private static function privateWrite(string $path, string $bytes): void
    {
        $stream = fopen($path, 'x+b');
        if ($stream === false) {
            throw new RuntimeException('Private transaction file could not be created.');
        }
        try {
            if (!chmod($path, 0600) || fwrite($stream, $bytes) !== strlen($bytes) || !fflush($stream) || !fsync($stream)) {
                throw new RuntimeException('Transaction data could not be safely written.');
            }
        } finally {
            fclose($stream);
        }
    }

    private static function decode(string $bytes): array
    {
        try {
            $data = json_decode($bytes, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new RuntimeException('Bundle JSON is invalid.');
        }
        if (!is_array($data)) {
            throw new RuntimeException('Bundle JSON must be an object.');
        }

        return $data;
    }

    private static function json(array $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    private static function summary(string $status): array
    {
        return ['status' => $status, 'dependencies_installed' => false, 'integration' => 'pending', 'apply_ready' => false];
    }
}
