<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

use LaravelSetup\Planner;
use LaravelSetup\Resolver;

$root = sys_get_temp_dir().'/laravel real solver test '.bin2hex(random_bytes(6));
mkdir($root, 0700);
$project = $root.'/app';
mkdir($project, 0700);
$wrapper = $root.'/local-solver.php';
$source = <<<'PHP'
<?php
// Test-only public package metadata fixture; production rejects custom repositories.
$manifest = json_decode(file_get_contents('composer.json'), true, flags: JSON_THROW_ON_ERROR);
$manifest['repositories'] = [
    ['type' => 'package', 'package' => ['name' => 'laravel/framework', 'version' => '13.0.0', 'require' => ['php' => '^8.3']]],
    ['type' => 'package', 'package' => ['name' => 'laravel/pint', 'version' => '1.24.0', 'require' => ['php' => '^8.3']]],
    ['packagist.org' => false],
];
file_put_contents('composer.json', json_encode($manifest, JSON_THROW_ON_ERROR));
$process = proc_open(['composer', ...array_slice($argv, 1)], [0 => STDIN, 1 => STDOUT, 2 => STDERR], $pipes);
exit(is_resource($process) ? proc_close($process) : 99);
PHP;
file_put_contents($wrapper, $source);
$manifest = ['require' => ['php' => '^8.3', 'laravel/framework' => '^13.0']];
file_put_contents($project.'/composer.json', json_encode($manifest, JSON_THROW_ON_ERROR));
$original = file_get_contents($project.'/composer.json');
try {
    $resolver = new Resolver([PHP_BINARY, '-c', php_ini_loaded_file() ?: '', $wrapper], 30);
    $plan = (new Planner())->create($project, ['pint'], 'stable');
    $result = $resolver->resolve($project, $plan);
    if ($result['resolved_packages'] !== [['name' => 'laravel/framework', 'version' => '13.0.0'], ['name' => 'laravel/pint', 'version' => '1.24.0']]
        || $result['apply_ready'] !== false || $result['lock_retained'] !== false || file_get_contents($project.'/composer.json') !== $original || count(scandir($project)) !== 3) {
        throw new RuntimeException('Real Composer fixture did not produce expected isolated resolution.');
    }
    $manifest['require']['fixture/no-such-package'] = '^1.0';
    file_put_contents($project.'/composer.json', json_encode($manifest, JSON_THROW_ON_ERROR));
    $plan = (new Planner())->create($project, ['pint'], 'stable');
    try {
        $resolver->resolve($project, $plan);
        throw new LogicException('Real Composer must reject an unsatisfiable fixture.');
    } catch (RuntimeException $error) {
        if ($error->getMessage() !== 'Composer resolution failed. No target changes were applied.') {
            throw $error;
        }
    }
    echo "PASS: real Composer offline synthetic-metadata solve and unsatisfiable dependency rejection; no upstream integration claim.\n";
} finally {
    unlink($wrapper);
    unlink($project.'/composer.json');
    rmdir($project);
    rmdir($root);
}
