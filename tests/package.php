<?php

declare(strict_types=1);

$path = dirname(__DIR__).'/build/laravel-setup.phar';
$archive = new Phar($path);
$hasApplication = false;
$lock = json_decode(file_get_contents(dirname(__DIR__).'/composer.lock'), true, flags: JSON_THROW_ON_ERROR);
$production = array_column($lock['packages'], 'name');
foreach (new RecursiveIteratorIterator($archive) as $file) {
    $name = str_replace('phar://'.$path.'/', '', $file->getPathname());
    if ($name === 'src/Application.php') {
        $hasApplication = true;
    }
    if (preg_match('~^vendor/([^/]+/[^/]+)/~', $name, $match) && !str_starts_with($name, 'vendor/composer/') && !in_array($match[1], $production, true)) {
        throw new RuntimeException('Non-production package in archive: '.$match[1]);
    }
    if (preg_match('~^(tests/|build/|infrastructure/|phpstan-create\.neon$|\.git/|\.env(?:$|\.))~', $name)) {
        throw new RuntimeException('Unexpected archive member: '.$name);
    }
}
if (!$hasApplication) {
    throw new RuntimeException('Application missing from archive.');
}
echo "PASS: archive contains application and excludes test/dev files.\n";
