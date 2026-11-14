<?php

declare(strict_types=1);

$entry = $argv[1] ?? dirname(__DIR__).'/bin/laravel-setup';
$root = sys_get_temp_dir().'/lsu new cli '.bin2hex(random_bytes(6));
mkdir($root, 0700);
$run = static function (array $arguments) use ($entry): array {
    $process = proc_open([PHP_BINARY, '-c', php_ini_loaded_file() ?: '', $entry, 'new', ...$arguments, '--no-interaction'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) { throw new RuntimeException('Cannot launch new command.'); }
    $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    return [proc_close($process), $out, $err];
};
try {
    [$exit, $out, $err] = $run([$root.'/app with spaces']);
    if ($exit !== 0 || $err !== '' || json_decode($out, true)['destination'] !== $root.'/app with spaces' || count(scandir($root)) !== 2) {
        throw new RuntimeException('New preview must return JSON and leave parent unchanged.');
    }
    [$exit, $out, $err] = $run([$root, '--apply']);
    if ($exit !== 1 || $out !== '' || !str_contains($err, 'Destination already exists')) { throw new RuntimeException('Existing destination must fail before launching Composer.'); }
    [$exit, $out, $err] = $run([$root.'/strict app', '--engineering=strict']);
    $plan = json_decode($out, true, flags: JSON_THROW_ON_ERROR);
    if ($exit !== 0 || $err !== '' || $plan['engineering'] !== 'strict' || !in_array('nunomaduro/essentials', $plan['engineering_packages'], true) || count(scandir($root)) !== 2) { throw new RuntimeException('Strict preview must show retained runtime packages without writes.'); }
    [$exit, $out, $err] = $run([$root.'/invalid', '--engineering=unknown']);
    if ($exit !== 1 || $out !== '' || !str_contains($err, 'none or strict')) { throw new RuntimeException('Unknown preset must fail before mutation.'); }
    echo "PASS: new CLI preview and existing destination refusal.\n";
} finally {
    rmdir($root);
}
