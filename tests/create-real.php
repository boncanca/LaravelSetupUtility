<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

use LaravelSetup\Creator;
use Symfony\Component\Process\Process;

if (getenv('RUN_CREATE_INTEGRATION') !== '1') {
    echo "SKIP: set RUN_CREATE_INTEGRATION=1 to download official Laravel and its upstream dependencies.\n";
    exit(0);
}
$entry = $argv[1] ?? dirname(__DIR__).'/bin/laravel-setup';
$temporary = realpath(getenv('TMPDIR') ?: '/tmp');
$root = $temporary.'/real create '.bin2hex(random_bytes(6));
mkdir($root, 0700);
// Set before the first sys_get_temp_dir() call; independent test processes stay isolated.
putenv('TMPDIR='.$root);
$_ENV['TMPDIR'] = $root;
if (sys_get_temp_dir() !== $root) {
    throw new RuntimeException('Create tests require a fresh isolated temporary directory.');
}
$target = $root.'/Laravel application';
$before = glob(sys_get_temp_dir().'/laravel-setup-create-*');
$remove = function (string $path) use (&$remove): void {
    if (is_dir($path) && ! is_link($path)) {
        foreach (new FilesystemIterator($path) as $file) {
            $remove($file->getPathname());
        }
        rmdir($path);
    } elseif (file_exists($path) || is_link($path)) {
        unlink($path);
    }
};
$run = function (array $command, string $directory, int $expected = 0): string {
    $process = new Process($command, $directory, timeout: 900);
    $process->run();
    $output = $process->getOutput().$process->getErrorOutput();
    if ($process->getExitCode() !== $expected) {
        throw new RuntimeException('Real integration command failed: '.$output);
    }

    return $output;
};
try {
    $output = $run([PHP_BINARY, $entry, 'create', $target], $root);
    $result = json_decode($output, true, flags: JSON_THROW_ON_ERROR);
    if ($result['laravel_skeleton'] !== Creator::SKELETON || $result['engineering_preset'] !== false || $result['migrations_run'] !== false) {
        throw new RuntimeException('Creation readiness result is incorrect.');
    }
    $manifest = file_get_contents($target.'/composer.json');
    $lock = file_get_contents($target.'/composer.lock');
    $environment = file_get_contents($target.'/.env');
    if (! str_contains($environment, 'SESSION_DRIVER=file') || ! str_contains($environment, 'CACHE_STORE=file') || ! str_contains($environment, 'QUEUE_CONNECTION=sync') || ! preg_match('/^APP_KEY=base64:.+$/m', $environment) || (fileperms($target.'/.env') & 0777) !== 0600) {
        throw new RuntimeException('Local boot configuration or private application key is missing.');
    }
    $run([PHP_BINARY, '-r', Creator::smokeCode()], $target);
    $tests = $run([PHP_BINARY, 'artisan', 'test', '--no-ansi', '--do-not-cache-result'], $target);
    if (! str_contains($tests, '2 passed')) {
        throw new RuntimeException('Expected both official Laravel example tests to pass.');
    }
    $failure = $run([PHP_BINARY, $entry, 'create', $target], $root, 1);
    if (! str_contains($failure, 'Target already exists') || file_get_contents($target.'/composer.json') !== $manifest || file_get_contents($target.'/composer.lock') !== $lock) {
        throw new RuntimeException('Rerun did not preserve the generated application.');
    }
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($target, FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file->isLink()) {
            throw new RuntimeException('Unexpected generated symlink.');
        }
        if ($file->isFile() && $file->getSize() < 16777216 && str_contains(file_get_contents($file->getPathname()), '/laravel-setup-create-')) {
            throw new RuntimeException('Generated application retained a private staging path.');
        }
    }
    if (str_contains($manifest, 'boncanca/laravel-setup-utility') || str_contains($lock, 'boncanca/laravel-setup-utility') || file_exists($target.'/.phpunit.result.cache') || glob(sys_get_temp_dir().'/laravel-setup-create-*') !== $before) {
        throw new RuntimeException('Utility or verification residue remained after installation.');
    }
    echo 'PASS: official Laravel '.Creator::SKELETON.' created; moved homepage and /up boot; 2 upstream tests pass; rerun preserves files; no utility/staging residue.'."\n";
} finally {
    $remove($root);
}
