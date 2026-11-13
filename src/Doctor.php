<?php

declare(strict_types=1);

namespace LaravelSetup;

use Composer\Semver\Semver;
use JsonException;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use UnexpectedValueException;

final class Doctor
{
    public function inspect(string $path): array
    {
        $snapshot = ProjectSnapshot::read($path, true);
        $extensions = get_loaded_extensions();
        sort($extensions, SORT_STRING);
        $extensionNames = [];
        foreach ($extensions as $extension) {
            $extensionNames['ext-'.str_replace(' ', '-', strtolower($extension))] = $extension;
        }
        $requirements = [];
        foreach ($snapshot['manifest']['require'] as $name => $constraint) {
            if (!is_string($name)) {
                throw new RuntimeException('Composer requirement names must be strings.');
            }
            if ($name !== 'php' && !str_starts_with($name, 'ext-')) {
                continue;
            }
            if (!is_string($constraint)) {
                throw new RuntimeException('Runtime requirements must be string constraints.');
            }
            $extension = $extensionNames[$name] ?? null;
            $version = $name === 'php' ? PHP_VERSION : ($extension === null ? false : (phpversion($extension) ?: '0'));
            try {
                $satisfied = $version !== false && ($constraint === '*' || Semver::satisfies($version, $constraint));
            } catch (UnexpectedValueException) {
                throw new RuntimeException('Runtime requirement constraint or version is unsupported.');
            }
            $requirements[$name] = ['constraint' => $constraint, 'version' => $version === false ? null : $version, 'satisfied' => $satisfied];
        }
        ksort($requirements, SORT_STRING);
        $lockedLaravel = null;
        if ($snapshot['lock_contents'] !== null) {
            try {
                $lock = json_decode($snapshot['lock_contents'], true, flags: JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                throw new RuntimeException('Composer lock is not valid JSON.');
            }
            if (!is_array($lock) || !is_array($lock['packages'] ?? null) || !is_array($lock['packages-dev'] ?? null)) {
                throw new RuntimeException('Composer lock package lists are unsupported.');
            }
            foreach (array_merge($lock['packages'], $lock['packages-dev']) as $package) {
                if (!is_array($package) || !is_string($package['name'] ?? null) || !is_string($package['version'] ?? null)) {
                    throw new RuntimeException('Composer lock package metadata is unsupported.');
                }
                if ($package['name'] === 'laravel/framework') {
                    if ($lockedLaravel !== null) {
                        throw new RuntimeException('Composer lock contains duplicate Laravel entries.');
                    }
                    $lockedLaravel = $package['version'];
                }
            }
        }
        $tools = [];
        $finder = new ExecutableFinder();
        foreach (['composer', 'git', 'node', 'npm', 'pnpm', 'yarn', 'bun'] as $tool) {
            $tools[$tool] = ['available_on_path' => $finder->find($tool) !== null, 'version' => 'not_probed'];
        }
        $configurations = [];
        foreach (['pint.json', 'phpstan.neon', 'phpstan.neon.dist', 'rector.php', 'phpunit.xml', 'phpunit.xml.dist', 'tests/Pest.php', 'ENGINEERING.md', 'AGENTS.md', 'package.json'] as $file) {
            $configurations[$file] = is_file($snapshot['root'].'/'.$file) || is_link($snapshot['root'].'/'.$file);
        }

        return [
            'schema_version' => 1,
            'project' => [
                'root' => $snapshot['root'],
                'declared_laravel_constraint' => $snapshot['laravel_constraint'],
                'locked_laravel_version' => $lockedLaravel,
                'installed_versions' => 'not_inspected',
                'git' => ['metadata_present' => file_exists($snapshot['root'].'/.git') || is_link($snapshot['root'].'/.git'), 'worktree_state' => 'not_checked'],
                'configuration_presence' => $configurations,
            ],
            'runtime' => ['php' => PHP_VERSION, 'os_family' => PHP_OS_FAMILY, 'extensions' => $extensions],
            'tools' => $tools,
            'requirements' => $requirements,
            'requirements_satisfied' => !in_array(false, array_column($requirements, 'satisfied'), true),
            'inputs' => $snapshot['inputs'],
            'apply_ready' => false,
        ];
    }
}
