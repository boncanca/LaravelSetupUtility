<?php

declare(strict_types=1);

namespace LaravelSetup;

use Composer\Semver\Intervals;
use Composer\Semver\Semver;
use Composer\Semver\VersionParser;
use RuntimeException;
use UnexpectedValueException;

final class Planner
{
    private const CATALOG = [
        'phpstan' => ['version' => 1, 'php' => '^8.3', 'laravel' => '^12.0 || ^13.0', 'package' => 'phpstan/phpstan', 'constraint' => '^2.1'],
        'pint' => ['version' => 1, 'php' => '^8.3', 'laravel' => '^12.0 || ^13.0', 'package' => 'laravel/pint', 'constraint' => '^1.24'],
    ];

    public function create(string $path, array $recipes, string $channel): array
    {
        if ($channel !== 'stable') {
            throw new RuntimeException('Only the stable catalog is currently supported.');
        }
        if ($recipes === [] || array_filter($recipes, static fn ($recipe): bool => !is_string($recipe)) !== []) {
            throw new RuntimeException('Select at least one supported recipe.');
        }
        $recipes = array_values(array_unique($recipes));
        sort($recipes, SORT_STRING);
        foreach ($recipes as $recipe) {
            if (!array_key_exists($recipe, self::CATALOG)) {
                throw new RuntimeException('Unsupported recipe selection.');
            }
        }
        $snapshot = ProjectSnapshot::read($path, true);
        $parser = new VersionParser();
        $packages = [];
        $versions = [];
        try {
            $php = $parser->parseConstraints($snapshot['php_constraint']);
            $laravel = $parser->parseConstraints($snapshot['laravel_constraint']);
            if (!Semver::satisfies(PHP_VERSION, $snapshot['php_constraint'])) {
                throw new RuntimeException('Current PHP runtime does not satisfy the project constraint.');
            }
            foreach ($recipes as $recipe) {
                $definition = self::CATALOG[$recipe];
                if (!Intervals::haveIntersections($php, $parser->parseConstraints($definition['php']))
                    || !Semver::satisfies(PHP_VERSION, $definition['php'])
                    || !Intervals::haveIntersections($laravel, $parser->parseConstraints($definition['laravel']))) {
                    throw new RuntimeException('Project constraints are incompatible with the curated recipe policy.');
                }
                $category = 'require-dev';
                $constraint = $definition['constraint'];
                $present = false;
                foreach (['require', 'require-dev'] as $section) {
                    $requirements = $snapshot['manifest'][$section] ?? [];
                    if (!is_array($requirements)) {
                        throw new RuntimeException('Composer dependency sections must be objects.');
                    }
                    if (array_key_exists($definition['package'], $requirements)) {
                        if ($present) {
                            throw new RuntimeException('Selected package is declared in both dependency sections.');
                        }
                        $existing = $requirements[$definition['package']];
                        if (!is_string($existing) || !Intervals::haveIntersections($parser->parseConstraints($existing), $parser->parseConstraints($definition['constraint']))) {
                            throw new RuntimeException('Existing package constraint conflicts with the selected recipe.');
                        }
                        $present = true;
                        $category = $section;
                        $constraint = $existing;
                    }
                }
                $versions[$recipe] = $definition['version'];
                $packages[] = ['name' => $definition['package'], 'constraint' => $constraint, 'dependency_type' => $category];
            }
        } catch (UnexpectedValueException) {
            throw new RuntimeException('Composer constraint syntax is invalid or unsupported.');
        }

        return [
            'schema_version' => 1,
            'utility_version' => '0.1.0-dev',
            'catalog_channel' => $channel,
            'catalog_revision' => 'sha256:'.hash('sha256', json_encode(self::CATALOG, JSON_THROW_ON_ERROR)),
            'root' => $snapshot['root'],
            'runtime' => ['php' => PHP_VERSION, 'os_family' => PHP_OS_FAMILY, 'os' => PHP_OS],
            'recipes' => $recipes,
            'recipe_versions' => $versions,
            'inputs' => $snapshot['inputs'],
            'project_constraints' => ['php' => $snapshot['php_constraint'], 'laravel' => $snapshot['laravel_constraint']],
            'packages' => $packages,
            'compatibility' => ['policy' => 'curated_declared_constraint_intersections', 'runtime_php' => 'satisfied', 'dependencies' => 'unresolved', 'integration' => 'unverified'],
            'apply_ready' => false,
        ];
    }
}
