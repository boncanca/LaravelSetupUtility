<?php

declare(strict_types=1);

$entry = $argv[1] ?? dirname(__DIR__).'/bin/laravel-setup';
$root = sys_get_temp_dir().'/lsu doctor '.bin2hex(random_bytes(6));
mkdir($root, 0700);
$run = static function (string $path) use ($entry): array {
    $process = proc_open([PHP_BINARY, '-c', php_ini_loaded_file() ?: '', $entry, 'doctor', $path, '--no-interaction'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) { throw new RuntimeException('Cannot launch doctor.'); }
    $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    return [proc_close($process), $out, $err];
};
$write = static function (array $require) use ($root): void {
    file_put_contents($root.'/composer.json', json_encode(['require' => $require, 'scripts' => ['post-install-cmd' => 'touch MUST_NOT_RUN'], 'extra' => ['secret' => 'NEVER_PRINT']], JSON_THROW_ON_ERROR));
};
try {
    $extensionRequirements = [];
    foreach (get_loaded_extensions() as $extension) {
        $extensionRequirements['ext-'.str_replace(' ', '-', strtolower($extension))] = '*';
    }
    $write(['php' => '^8.3', 'laravel/framework' => '^13.0', ...$extensionRequirements]);
    file_put_contents($root.'/.env', 'SECRET=NEVER_PRINT');
    file_put_contents($root.'/artisan', '<?php file_put_contents(__DIR__."/MUST_NOT_RUN", "bad");');
    file_put_contents($root.'/composer.lock', json_encode(['packages' => [['name' => 'laravel/framework', 'version' => 'v13.0.0']], 'packages-dev' => []], JSON_THROW_ON_ERROR));
    $before = [];
    foreach (glob($root.'/*') as $file) { $before[basename($file)] = hash_file('sha256', $file); }
    [$exit, $out, $err] = $run($root);
    $data = json_decode($out, true, flags: JSON_THROW_ON_ERROR);
    if ($exit !== 0 || $err !== '' || $data['schema_version'] !== 1 || $data['requirements_satisfied'] !== true
        || $data['project']['locked_laravel_version'] !== 'v13.0.0' || $data['project']['installed_versions'] !== 'not_inspected'
        || $data['project']['git']['worktree_state'] !== 'not_checked' || $data['apply_ready'] !== false) {
        throw new RuntimeException('Doctor must report facts without claiming installation readiness: '.$out.$err);
    }
    foreach ($before as $file => $hash) { if (hash_file('sha256', $root.'/'.$file) !== $hash) { throw new RuntimeException('Doctor changed input.'); } }
    if (file_exists($root.'/MUST_NOT_RUN') || str_contains($out.$err, 'NEVER_PRINT')) { throw new RuntimeException('Doctor ran target code or disclosed data.'); }
    $write(['php' => '>=99.0', 'laravel/framework' => '^13.0', 'ext-lsu-nonexistent' => '*']);
    [$exit, $out, $err] = $run($root);
    $data = json_decode($out, true, flags: JSON_THROW_ON_ERROR);
    if ($exit !== 1 || $err !== '' || $data['requirements_satisfied'] !== false || $data['requirements']['ext-lsu-nonexistent']['satisfied'] !== false) { throw new RuntimeException('Missing runtime requirements must fail with structured diagnostics.'); }
    $write(['php' => '^8.3', 'laravel/framework' => '^13.0']);
    file_put_contents($root.'/composer.lock', '{NEVER_PRINT');
    [$exit, $out, $err] = $run($root);
    if ($exit !== 1 || $out !== '' || !str_contains($err, 'Doctor failed:') || str_contains($err, 'NEVER_PRINT')) { throw new RuntimeException('Malformed locks must fail privately.'); }
    unlink($root.'/composer.lock');
    [$exit, $out, $err] = $run($root);
    if ($exit !== 0 || json_decode($out, true)['project']['locked_laravel_version'] !== null) { throw new RuntimeException('Missing lock is distinct from installed version.'); }
    $write(['php' => '^8.3', 'laravel/framework' => '^13.0', 0 => 'NEVER_PRINT']);
    [$exit, $out, $err] = $run($root);
    if ($exit !== 1 || $out !== '' || !str_contains($err, 'Doctor failed:') || str_contains($err, 'NEVER_PRINT') || str_contains($err, 'Stack trace')) { throw new RuntimeException('Numeric requirement names must fail privately.'); }
    echo "PASS: doctor runtime requirements, locked-version distinction, no target execution/mutation, private errors.\n";
} finally {
    foreach (glob($root.'/*') as $file) { unlink($file); }
    unlink($root.'/.env'); rmdir($root);
}
