<?php

declare(strict_types=1);

$entry = $argv[1] ?? dirname(__DIR__).'/bin/laravel-setup';
$directory = sys_get_temp_dir().'/laravel inspect test '.bin2hex(random_bytes(6));
mkdir($directory, 0700);
$files = [];
$folders = [];
$run = static function (string $path) use ($entry): array {
    $process = proc_open([PHP_BINARY, '-c', php_ini_loaded_file() ?: '', $entry, 'inspect', $path, '--no-interaction'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('Could not launch inspection.');
    }
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    return [proc_close($process), $stdout, $stderr];
};
$fixture = static function (string $name, string $manifest) use ($directory, &$files, &$folders): string {
    $path = $directory.'/'.$name;
    mkdir($path, 0700);
    $folders[] = $path;
    file_put_contents($path.'/composer.json', $manifest);
    $files[] = $path.'/composer.json';

    return $path;
};
try {
    $manifest = json_encode([
        'require' => ['php' => '^8.3', 'laravel/framework' => '^13.0'],
        'scripts' => ['post-install-cmd' => 'touch inspection-must-not-run'],
        'extra' => ['secret' => 'do-not-disclose-this'],
    ], JSON_THROW_ON_ERROR);
    $project = $fixture('valid app', $manifest);
    file_put_contents($project.'/.env', 'APP_KEY=do-not-disclose-this');
    $files[] = $project.'/.env';
    $before = hash_file('sha256', $project.'/composer.json');
    [$exit, $stdout, $stderr] = $run($project.'/../valid app');
    $expected = ['root' => realpath($project), 'php_constraint' => '^8.3', 'laravel_constraint' => '^13.0'];
    if ($exit !== 0 || $stderr !== '' || json_decode($stdout, true) !== $expected) {
        throw new RuntimeException('Inspection must return only canonical root and declared constraints: '.$stderr.$stdout);
    }
    if (str_contains($stdout, 'do-not-disclose-this') || hash_file('sha256', $project.'/composer.json') !== $before || count(scandir($project)) !== 4) {
        throw new RuntimeException('Inspection disclosed private data or mutated the project.');
    }
    $markup = $fixture('markup', '{"require":{"php":"<info>^8.3</info>","laravel/framework":"^13.0"}}');
    [$exit, $stdout, $stderr] = $run($markup);
    if ($exit !== 0 || $stderr !== '' || (json_decode($stdout, true)['php_constraint'] ?? null) !== '<info>^8.3</info>') {
        throw new RuntimeException('Declared constraint text must remain literal JSON.');
    }
    $empty = $directory.'/empty';
    mkdir($empty, 0700);
    $folders[] = $empty;
    $invalid = [
        $directory.'/missing',
        $empty,
        $fixture('malformed', '{secret-invalid'),
        $fixture('not-laravel', '{"require":{"php":"^8.3"}}'),
        $fixture('wrong-shape', '{"require":{"php":[],"laravel/framework":"^13.0"}}'),
        $fixture('wrong-laravel', '{"require":{"php":"^8.3","laravel/framework":13}}'),
        $fixture('missing-php', '{"require":{"laravel/framework":"^13.0"}}'),
        $fixture('dev-only', '{"require":{"php":"^8.3"},"require-dev":{"laravel/framework":"^13.0"}}'),
        $fixture('scalar', '"private-scalar"'),
        $fixture('oversized', str_repeat(' ', 1048577)),
    ];
    foreach ($invalid as $path) {
        [$exit, $stdout, $stderr] = $run($path);
        if ($exit !== 1 || $stdout !== '' || !str_contains($stderr, 'Inspection failed:') || str_contains($stderr, 'secret-invalid')) {
            throw new RuntimeException('Invalid project must fail cleanly without manifest disclosure.');
        }
    }
    $outside = $fixture('outside', $manifest);
    $linked = $directory.'/linked';
    mkdir($linked, 0700);
    $folders[] = $linked;
    symlink($outside.'/composer.json', $linked.'/composer.json');
    $files[] = $linked.'/composer.json';
    [$exit, $stdout, $stderr] = $run($linked);
    if ($exit !== 1 || $stdout !== '' || !str_contains($stderr, 'outside the project')) {
        throw new RuntimeException('Escaping manifest symlink must be rejected.');
    }
    echo "PASS: canonical read-only inspection, literal JSON, private data exclusion and eleven invalid-project cases.\n";
} finally {
    foreach (array_reverse($files) as $file) {
        unlink($file);
    }
    foreach (array_reverse($folders) as $folder) {
        rmdir($folder);
    }
    rmdir($directory);
}
