<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

use LaravelSetup\NewProject;

$root = sys_get_temp_dir().'/lsu new '.bin2hex(random_bytes(6));
mkdir($root, 0700);
try {
    $service = new NewProject();
    $plan = $service->plan($root.'/fresh app');
    if ($plan['skeleton_version'] !== '13.11.0' || $plan['database_migrations'] !== false || $plan['composer_scripts'] !== false || count(scandir($root)) !== 2) { throw new RuntimeException('New plan must be read-only with explicit boundaries.'); }
    mkdir($root.'/existing');
    foreach ([$root.'/existing', $root.'/missing/child', $root.'/.', $root.'/bad;name'] as $invalid) {
        try { $service->plan($invalid); throw new LogicException('Unsafe destination accepted.'); }
        catch (RuntimeException) { }
    }
    $fake = $root.'/composer.php';
    file_put_contents($fake, <<<'FAKE'
<?php
$path = $argv[3];
$expected = ['create-project', 'laravel/laravel', $path, '13.11.0', '--no-scripts', '--no-plugins', '--no-interaction', '--prefer-dist', '--no-progress'];
if (array_slice($argv, 1) !== $expected || getenv('COMPOSER_AUTH') !== '{}' || !str_starts_with(getenv('COMPOSER_HOME'), dirname($path))) { exit(21); }
mkdir($path, 0700);
file_put_contents($path.'/composer.json', json_encode(['require' => ['php' => '^8.3', 'laravel/framework' => '^13.0']]));
file_put_contents($path.'/composer.lock', '{"packages":[{"name":"laravel/framework","version":"v13.0.0"}],"packages-dev":[]}');
file_put_contents($path.'/.env.example', "APP_ENV=local\nAPP_KEY=\nSESSION_DRIVER=database\nCACHE_STORE=database\nQUEUE_CONNECTION=database\n");
file_put_contents($path.'/artisan', '<?php if (getenv("APP_CONFIG_CACHE") !== false || getenv("LARAVEL_STORAGE_PATH") !== false || getenv("VIEW_COMPILED_PATH") !== false) { exit(29); } if ($argv[1] === "--version") { echo "Laravel Framework 13.0.0"; } elseif ($argv[1] === "route:list") { echo "[]"; } else { exit(22); }');
mkdir($path.'/vendor', 0700); mkdir($path.'/vendor/bin', 0700);
mkdir($path.'/storage/framework/views', 0700, true);
mkdir($path.'/storage/framework/cache/phpstan', 0700, true);
mkdir($path.'/storage/framework/cache/data', 0700, true);
file_put_contents($path.'/storage/framework/cache/data/.gitignore', "*\n!.gitignore\n");
file_put_contents($path.'/storage/framework/views/compiled.php', $path);
file_put_contents($path.'/storage/framework/cache/phpstan/cache.php', $path);
file_put_contents($path.'/.phpunit.result.cache', $path);

file_put_contents($path.'/vendor/bin/phpunit', '<?php exit(0);');
FAKE);
    $php = [PHP_BINARY, '-c', php_ini_loaded_file() ?: ''];
    $runner = new NewProject([...$php, $fake], $php, 10);
    $target = $root.'/fresh app';
    putenv('APP_CONFIG_CACHE=/outside/application/config.php');
    putenv('LARAVEL_STORAGE_PATH=/outside/storage');
    putenv('VIEW_COMPILED_PATH=/outside/views');
    $report = $runner->create($target);
    if ($report['status'] !== 'created' || $report['database_migrations'] !== false || $report['installer_residue'] !== false || !is_file($target.'/composer.lock')) { throw new RuntimeException('New app handoff incomplete.'); }
    $env = file_get_contents($target.'/.env');
    if (!preg_match('~^APP_KEY=base64:[A-Za-z0-9+/]{43}=$~m', $env) || !str_contains($env, 'SESSION_DRIVER=file') || str_contains(json_encode($report), 'base64:')) { throw new RuntimeException('Generated environment must be usable and private.'); }
    if (glob($root.'/.lsu-new-*') !== []) { throw new RuntimeException('Staging residue after success.'); }
    foreach (['storage/framework/views/compiled.php', 'storage/framework/cache/phpstan', '.phpunit.result.cache'] as $cache) {
        if (file_exists($target.'/'.$cache)) { throw new RuntimeException('Verification cache retained private staging paths.'); }
    }
    if (!is_file($target.'/storage/framework/cache/data/.gitignore')) { throw new RuntimeException('Cleanup removed upstream cache scaffolding.'); }

    $failed = $root.'/fail.php'; file_put_contents($failed, '<?php echo "SECRET"; exit(1);');
    try { (new NewProject([...$php, $failed], $php, 10))->create($root.'/failure'); throw new LogicException('Failed dependency install accepted.'); }
    catch (RuntimeException $e) { if (str_contains($e->getMessage(), 'SECRET')) { throw new RuntimeException('Process output disclosed.'); } }
    if (file_exists($root.'/failure') || glob($root.'/.lsu-new-*') !== []) { throw new RuntimeException('Failure must leave destination absent and clean its stage.'); }
    symlink($root.'/existing', $root.'/linked');
    try { $service->plan($root.'/linked'); throw new LogicException('Symlink destination accepted.'); }
    catch (RuntimeException) { }
    $broken = $root.'/broken.php';
    file_put_contents($broken, str_replace("'<?php exit(0);'", "'<?php exit(23);'", file_get_contents($fake)));
    try { (new NewProject([...$php, $broken], $php, 10))->create($root.'/bad-test'); throw new LogicException('Failing upstream test published.'); }
    catch (RuntimeException $e) { if (!str_contains($e->getMessage(), 'Upstream tests failed.')) { throw $e; } }
    if (file_exists($root.'/bad-test') || glob($root.'/.lsu-new-*') !== []) { throw new RuntimeException('Failed tests must not publish.'); }
    $concurrent = $root.'/concurrent.php';
    $createdDuringTests = '<?php mkdir('.var_export($root.'/appeared', true).', 0700); exit(0);';
    file_put_contents($concurrent, str_replace("'<?php exit(0);'", var_export($createdDuringTests, true), file_get_contents($fake)));
    try { (new NewProject([...$php, $concurrent], $php, 10))->create($root.'/appeared'); throw new LogicException('Concurrent destination overwritten.'); }
    catch (RuntimeException $e) { if (!str_contains($e->getMessage(), 'Destination already exists')) { throw $e; } }
    if (!is_dir($root.'/appeared') || count(scandir($root.'/appeared')) !== 2 || glob($root.'/.lsu-new-*') !== []) { throw new RuntimeException('Concurrent destination must be preserved.'); }
    echo "PASS: read-only new plan, safe paths, staged creation, private environment, publication and failure cleanup (synthetic subprocess fixture).\n";
} finally {
    $remove = static function (string $path) use (&$remove): void {
        if (is_link($path) || !is_dir($path)) { unlink($path); return; }
        foreach (new FilesystemIterator($path) as $file) { $remove($file->getPathname()); }
        rmdir($path);
    };
    foreach (['APP_CONFIG_CACHE', 'LARAVEL_STORAGE_PATH', 'VIEW_COMPILED_PATH'] as $variable) { putenv($variable); }
    $remove($root);
}
