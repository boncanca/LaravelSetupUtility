<?php

declare(strict_types=1);

namespace LaravelSetup;

use JsonException;
use RuntimeException;

final class ProjectSnapshot
{
    public static function read(string $path, bool $includeLock = false): array
    {
        $root = realpath($path);
        if ($root === false || !is_dir($root)) {
            throw new RuntimeException('Project directory does not exist.');
        }
        $contents = self::readFile($root, 'composer.json', 1048576);
        try {
            $data = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new RuntimeException('Composer manifest is not valid JSON.');
        }
        $requirements = is_array($data) ? ($data['require'] ?? null) : null;
        $php = is_array($requirements) ? ($requirements['php'] ?? null) : null;
        $laravel = is_array($requirements) ? ($requirements['laravel/framework'] ?? null) : null;
        if (!is_string($php) || trim($php) === '' || !is_string($laravel) || trim($laravel) === '') {
            throw new RuntimeException('Composer manifest must declare PHP and laravel/framework string constraints in require.');
        }
        $inputs = ['composer.json' => hash('sha256', $contents)];
        $lock = null;
        if ($includeLock) {
            $lockPath = $root.'/composer.lock';
            $lock = file_exists($lockPath) || is_link($lockPath) ? self::readFile($root, 'composer.lock', 16777216) : null;
            $inputs['composer.lock'] = $lock === null ? null : hash('sha256', $lock);
        }

        return ['root' => $root, 'php_constraint' => $php, 'laravel_constraint' => $laravel, 'manifest' => $data, 'inputs' => $inputs, 'lock_contents' => $lock];
    }

    private static function readFile(string $root, string $name, int $limit): string
    {
        $resolved = realpath($root.'/'.$name);
        if ($resolved === false || !is_file($resolved)) {
            throw new RuntimeException('Project '.$name.' is missing or is not a file.');
        }
        if (!str_starts_with($resolved, rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('Project '.$name.' resolves outside the project.');
        }
        $contents = @file_get_contents($resolved, false, null, 0, $limit + 1);
        if ($contents === false || strlen($contents) > $limit) {
            throw new RuntimeException('Project '.$name.' is unreadable or exceeds the size limit.');
        }

        return $contents;
    }
}
