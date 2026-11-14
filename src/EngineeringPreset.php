<?php

declare(strict_types=1);

namespace LaravelSetup;

use RuntimeException;

/** Only used inside a newly generated, private staging application. */
final class EngineeringPreset
{
    public const PACKAGES = ['nunomaduro/essentials', 'pestphp/pest', 'pestphp/pest-plugin-laravel', 'larastan/larastan', 'rector/rector', 'driftingly/rector-laravel', 'phpunit/phpunit', 'laravel/pint'];

    public function prepare(string $path): void
    {
        $snapshot = ProjectSnapshot::read($path);
        $root = $snapshot['root'];
        $files = $this->files();
        foreach (['tests', 'tests/Feature', 'config', '.github', '.github/workflows'] as $directory) {
            $full = $root.'/'.$directory;
            if (is_link($full) || (file_exists($full) && !is_dir($full))) {
                throw new RuntimeException('Engineering output directories must be ordinary directories.');
            }
        }
        foreach (array_keys($files) as $file) {
            if (file_exists($root.'/'.$file) || is_link($root.'/'.$file)) {
                throw new RuntimeException('Engineering configuration already exists; no files were changed.');
            }
        }
        if (is_link($root.'/AGENTS.md')) {
            throw new RuntimeException('Agent instructions must not be a symlink.');
        }
        $agents = is_file($root.'/AGENTS.md') ? file_get_contents($root.'/AGENTS.md', false, null, 0, 1048577) : '';
        if ($agents === false || strlen($agents) > 1048576) {
            throw new RuntimeException('Agent instructions exceed supported limits.');
        }
        $manifest = json_decode($snapshot['manifest_contents'], flags: JSON_THROW_ON_ERROR);
        $manifest->{'require-dev'} ??= new \stdClass();
        $manifest->scripts ??= new \stdClass();
        if (!is_object($manifest->{'require-dev'}) || !is_object($manifest->scripts) || isset($manifest->scripts->check) || isset($manifest->scripts->fix)) {
            throw new RuntimeException('Engineering scripts conflict with scaffold configuration.');
        }
        $manifest->require->{'nunomaduro/essentials'} = '^1.2';
        foreach (['pestphp/pest' => '^4.7', 'pestphp/pest-plugin-laravel' => '^4.1', 'larastan/larastan' => '^3.15', 'rector/rector' => '^2.8', 'driftingly/rector-laravel' => '^2.7'] as $package => $constraint) {
            $manifest->{'require-dev'}->{$package} = $constraint;
        }
        $manifest->scripts->check = ['@php vendor/bin/pint --test', '@php vendor/bin/phpstan analyse --no-progress', '@php vendor/bin/rector process --dry-run --no-progress-bar', '@php vendor/bin/pest --compact'];
        $manifest->scripts->fix = ['@php vendor/bin/rector process --no-progress-bar', '@php vendor/bin/pint'];
        $files['composer.json'] = json_encode($manifest, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
        $files['AGENTS.md'] = $agents."\n\n## Project engineering\n\nSetup is complete. Follow ENGINEERING.md and run `composer check` before declaring work complete. `composer fix` intentionally changes first-party code. No additional agent package is selected; do not automatically install Boost. These project selections supersede upstream bootstrap suggestions to install agent tools. Preserve handwritten instructions and any future Boost-managed block.\n";
        foreach ($files as $name => $contents) {
            $directory = dirname($root.'/'.$name);
            if (!is_dir($directory) && !mkdir($directory, 0755, true)) {
                throw new RuntimeException('Could not create engineering output directory.');
            }
            if (file_put_contents($root.'/'.$name, $contents) !== strlen($contents)) {
                throw new RuntimeException('Could not write engineering configuration.');
            }
        }
    }

    private function files(): array
    {
        return [
            'pint.json' => "{\n  \"preset\": \"laravel\",\n  \"rules\": {\"declare_strict_types\": true}\n}\n",
            'phpstan.neon' => "includes:\n    - vendor/larastan/larastan/extension.neon\n    - vendor/nesbot/carbon/extension.neon\n\nparameters:\n    level: max\n    paths:\n        - app\n        - routes\n        - database\n    tmpDir: storage/framework/cache/phpstan\n",
            'rector.php' => <<<'PHP'
<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use RectorLaravel\Set\LaravelSetList;

return RectorConfig::configure()
    ->withPaths([__DIR__.'/app', __DIR__.'/routes', __DIR__.'/tests'])
    ->withSets([LaravelSetList::LARAVEL_CODE_QUALITY])
    ->withPreparedSets(deadCode: true, codeQuality: true, typeDeclarations: true, earlyReturn: true)
    ->withPhpSets(php83: true);
PHP,
            'tests/Pest.php' => "<?php\n\ndeclare(strict_types=1);\n\npest()->extend(Tests\\TestCase::class)->in('Feature');\n",
            'tests/Feature/EngineeringDefaultsTest.php' => <<<'PHP'
<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;

test('strict runtime defaults preserve guarded models and immutable dates', function (): void {
    expect(Date::now())->toBeInstanceOf(CarbonImmutable::class)
        ->and(Model::isUnguarded())->toBeFalse()
        ->and(Model::preventsLazyLoading())->toBeTrue()
        ->and(Model::preventsSilentlyDiscardingAttributes())->toBeTrue()
        ->and(Model::preventsAccessingMissingAttributes())->toBeTrue();
});
PHP,
            'config/essentials.php' => <<<'PHP'
<?php

declare(strict_types=1);

use NunoMaduro\Essentials\Configurables;

return [
    Configurables\AggressivePrefetching::class => false,
    Configurables\AutomaticallyEagerLoadRelationships::class => false,
    Configurables\FakeSleep::class => true,
    Configurables\ForceScheme::class => true,
    'environments' => [Configurables\ForceScheme::class => ['production']],
    Configurables\ImmutableDates::class => true,
    Configurables\PreventStrayRequests::class => true,
    Configurables\ProhibitDestructiveCommands::class => true,
    Configurables\SetDefaultPassword::class => false,
    Configurables\ShouldBeStrict::class => true,
    Configurables\Unguard::class => false,
];
PHP,
            'ENGINEERING.md' => <<<'MD'
# Engineering constitution

Simplicity scales. Complexity fails.

Use the smallest readable solution that meets correctness, security and performance requirements. Follow Laravel conventions. Validate input, authorize protected actions, protect tenant boundaries and use transactions where consistency requires them. Keep dependencies intentional and never commit secrets.

## Stack and commands

Laravel 13, PHP 8.4 qualified runtime. Raw Blade application; no auth, tenancy, billing or agent integration has been selected. Pest preserves existing PHPUnit tests. Essentials is an intentional runtime dependency with strict guarded models and immutable dates; automatic relationship loading is disabled and HTTPS generation is production-only.

- `composer check`: non-mutating Pint, maximum-level Larastan/PHPStan for app/routes/database, Rector dry-run, then Pest.
- `composer fix`: intentionally applies Rector/Pint changes; review the diff.
- `composer test`: preserves the upstream test command.

Tests should prove observable behavior and forbidden actions. No blanket coverage percentage substitutes for correctness. Add explicit types and load relationships deliberately. Do not run migrations, seeds or production operations as ordinary setup. Extend authorization and tenant-isolation tests when those capabilities are introduced.

The selected development tools/configuration belong to this application. No installer dependency, provider, route or process is required. Update this file and AGENTS.md when the actual stack changes. Exceptions require a documented reason and reviewer agreement.
MD,
            '.github/workflows/checks.yml' => <<<'YAML'
name: Engineering checks
on: [push, pull_request]
permissions:
  contents: read
jobs:
  checks:
    runs-on: ubuntu-24.04
    timeout-minutes: 10
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'
          extensions: mbstring, dom, xml, xmlwriter, pdo_sqlite
          coverage: none
      - run: composer install --no-interaction --no-progress --no-scripts --no-plugins
      - run: cp .env.example .env
      - run: php artisan key:generate --ansi
      - run: php artisan package:discover --ansi
      - run: composer check
YAML,
        ];
    }
}
