<?php

declare(strict_types=1);

$entry = $argv[1] ?? dirname(__DIR__).'/bin/laravel-setup';
$root = sys_get_temp_dir().'/laravel plan test '.bin2hex(random_bytes(6));
mkdir($root, 0700);
$project = $root.'/app';
mkdir($project, 0700);
$manifest = json_encode(['require' => ['php' => '^8.3', 'laravel/framework' => '^13.0'], 'scripts' => ['test' => 'touch must-not-execute'], 'extra' => ['secret' => 'never-print-this']], JSON_THROW_ON_ERROR);
file_put_contents($project.'/composer.json', $manifest);
$planFile = $root.'/plan.json';
$run = static function (array $arguments) use ($entry): array {
    $process = proc_open([PHP_BINARY, '-c', php_ini_loaded_file() ?: '', $entry, ...$arguments, '--no-interaction'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('Could not launch planner.');
    }
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    return [proc_close($process), $stdout, $stderr];
};
$mustFail = static function (array $arguments) use ($run): void {
    [$exit, $stdout, $stderr] = $run($arguments);
    if ($exit !== 1 || $stdout !== '' || !str_contains($stderr, 'Planning failed:') || str_contains($stderr, 'never-print-this')) {
        throw new RuntimeException('Expected clean planning failure.');
    }
};
try {
    $arguments = ['plan', $project, '--recipe=pint', '--recipe=phpstan', '--recipe=pint'];
    [$exit, $first, $stderr] = $run($arguments);
    if ($exit !== 0 || $stderr !== '') {
        throw new RuntimeException('Plan command must succeed: '.$stderr);
    }
    [$exit, $second] = $run(['plan', $project, '--recipe=phpstan', '--recipe=pint']);
    $plan = json_decode($first, true, flags: JSON_THROW_ON_ERROR);
    if ($exit !== 0 || $first !== $second || $plan['recipes'] !== ['phpstan', 'pint'] || $plan['schema_version'] !== 1 || !str_starts_with($plan['catalog_revision'], 'sha256:') || $plan['compatibility']['dependencies'] !== 'unresolved') {
        throw new RuntimeException('Plans must be deterministic, canonical and explicit about unresolved dependencies.');
    }
    if ($plan['inputs']['composer.json'] !== hash('sha256', $manifest) || $plan['inputs']['composer.lock'] !== null || str_contains($first, 'never-print-this') || file_get_contents($project.'/composer.json') !== $manifest || count(scandir($project)) !== 3) {
        throw new RuntimeException('Plan must hash inputs without mutation or private data disclosure.');
    }
    file_put_contents($planFile, $first);
    [$exit, $stdout] = $run(['check-plan', $project, $planFile]);
    if ($exit !== 0 || json_decode($stdout, true) !== ['valid' => true]) {
        throw new RuntimeException('Unchanged canonical plan must validate.');
    }
    $tampered = $plan;
    $tampered['packages'][0]['constraint'] = '*';
    file_put_contents($planFile, json_encode($tampered, JSON_THROW_ON_ERROR));
    $mustFail(['check-plan', $project, $planFile]);
    file_put_contents($planFile, $first);
    file_put_contents($project.'/composer.lock', '{}');
    $mustFail(['check-plan', $project, $planFile]);
    [$exit, $locked] = $run($arguments);
    file_put_contents($planFile, $locked);
    file_put_contents($project.'/composer.lock', '{"changed":true}');
    $mustFail(['check-plan', $project, $planFile]);
    unlink($project.'/composer.lock');
    $mustFail(['check-plan', $project, $planFile]);
    file_put_contents($planFile, $first);
    file_put_contents($project.'/composer.json', $manifest."\n");
    $mustFail(['check-plan', $project, $planFile]);
    file_put_contents($project.'/composer.json', $manifest);
    $mustFail(['plan', $project, '--recipe=unknown']);
    $mustFail(['plan', $project, '--recipe=pint', '--channel=dev']);
    $mustFail(['plan', $project]);
    foreach ([['^7.4', '^13.0'], ['^8.3', '^9.0'], ['secret-never-print-this', '^13.0'], ['^8.3', 'secret-never-print-this']] as [$php, $laravel]) {
        file_put_contents($project.'/composer.json', json_encode(['require' => ['php' => $php, 'laravel/framework' => $laravel]], JSON_THROW_ON_ERROR));
        $mustFail(['plan', $project, '--recipe=pint']);
    }
    file_put_contents($project.'/composer.json', json_encode(['require' => ['php' => '^8.3', 'laravel/framework' => '^13.0'], 'require-dev' => ['laravel/pint' => '^0.1']], JSON_THROW_ON_ERROR));
    $mustFail(['plan', $project, '--recipe=pint']);
    file_put_contents($project.'/composer.json', json_encode(['require' => ['php' => '^8.3', 'laravel/framework' => '^13.0', 'laravel/pint' => '^0.1']], JSON_THROW_ON_ERROR));
    $mustFail(['plan', $project, '--recipe=pint']);
    file_put_contents($project.'/composer.json', json_encode(['require' => ['php' => '^8.3', 'laravel/framework' => '^13.0', 'laravel/pint' => '~1.24.0']], JSON_THROW_ON_ERROR));
    [$exit, $runtimePackage] = $run(['plan', $project, '--recipe=pint']);
    $package = json_decode($runtimePackage, true)['packages'][0];
    if ($exit !== 0 || $package['dependency_type'] !== 'require' || $package['constraint'] !== '~1.24.0') {
        throw new RuntimeException('An existing dependency must retain its category and constraint.');
    }
    file_put_contents($project.'/composer.json', json_encode(['require' => ['php' => '^8.3', 'laravel/framework' => '^13.0', 'laravel/pint' => '^1.24'], 'require-dev' => ['laravel/pint' => '^1.24']], JSON_THROW_ON_ERROR));
    $mustFail(['plan', $project, '--recipe=pint']);
    file_put_contents($project.'/composer.json', $manifest);
    file_put_contents($root.'/external.lock', '{}');
    symlink($root.'/external.lock', $project.'/composer.lock');
    $mustFail(['plan', $project, '--recipe=pint']);
    unlink($project.'/composer.lock');
    unlink($project.'/composer.json');
    file_put_contents($root.'/external.json', $manifest);
    symlink($root.'/external.json', $project.'/composer.json');
    $mustFail(['plan', $project, '--recipe=pint']);
    if (file_exists($project.'/must-not-execute')) {
        throw new RuntimeException('Project scripts must not execute.');
    }
    echo "PASS: deterministic planning, canonical selections, constraint checks, tamper/drift detection and symlink rejection.\n";
} finally {
    foreach (glob($project.'/*') as $file) {
        unlink($file);
    }
    foreach (glob($root.'/*') as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
    rmdir($project);
    rmdir($root);
}
