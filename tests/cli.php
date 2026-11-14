<?php

declare(strict_types=1);

$entry = $argv[1] ?? dirname(__DIR__).'/bin/laravel-setup';
$directory = sys_get_temp_dir().'/laravel setup test '.bin2hex(random_bytes(6));
mkdir($directory, 0700);
$cases = [
    [['--version'], 0, 'Laravel Setup Utility 0.1.0-dev'],
    [['--help'], 0, 'Usage:'],
    [['list', '--raw'], 0, 'help'],
    [['install'], 1, 'not defined'],
    [['create', 'missing-parent/new-app'], 1, 'Creation failed:'],
    [['resolve-plan', 'missing-project', 'missing-plan.json'], 1, 'Resolution failed:'],
    [['apply-resolution', 'missing-project', 'missing-bundle'], 1, 'Manifest transaction failed:'],
    [['recover-resolution', 'missing-project', 'missing-bundle'], 1, 'Manifest transaction failed:'],
];
try {
    foreach ($cases as [$arguments, $expectedExit, $expectedOutput]) {
        $process = proc_open([PHP_BINARY, '-c', php_ini_loaded_file() ?: '', $entry, ...$arguments], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $directory);
        if (!is_resource($process)) {
            throw new RuntimeException('Could not launch CLI.');
        }
        $output = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);
        if ($exit !== $expectedExit || !str_contains($output, $expectedOutput)) {
            throw new RuntimeException(json_encode($arguments).' returned '.$exit.': '.$output);
        }
        if (count(scandir($directory)) !== 2) {
            throw new RuntimeException('CLI left files in the working directory.');
        }
    }
    echo "PASS: eight CLI contracts; foreign working directory unchanged.\n";
} finally {
    rmdir($directory);
}
