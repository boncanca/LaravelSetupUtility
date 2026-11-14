<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

use LaravelSetup\EngineeringPreset;

$root = sys_get_temp_dir().'/lsu engineering '.bin2hex(random_bytes(6));
mkdir($root, 0700); mkdir($root.'/tests'); mkdir($root.'/config');
try {
    file_put_contents($root.'/composer.json', '{"require":{"php":"^8.3","laravel/framework":"^13.17"},"scripts":{"test":"original-test"}}');
    file_put_contents($root.'/AGENTS.md', "Handwritten instructions\n");
    mkdir($root.'/outside');
    symlink($root.'/outside', $root.'/tests/Feature');
    $original = hash_file('sha256', $root.'/composer.json');
    try { (new EngineeringPreset())->prepare($root); throw new LogicException('Symlink output parent accepted.'); }
    catch (RuntimeException) { }
    if (count(scandir($root.'/outside')) !== 2 || hash_file('sha256', $root.'/composer.json') !== $original) { throw new RuntimeException('Symlink rejection must precede all writes.'); }
    unlink($root.'/tests/Feature');
    (new EngineeringPreset())->prepare($root);
    $manifest = json_decode(file_get_contents($root.'/composer.json'), true, flags: JSON_THROW_ON_ERROR);
    if ($manifest['scripts']['test'] !== 'original-test' || !isset($manifest['require']['nunomaduro/essentials']) || !isset($manifest['require-dev']['larastan/larastan']) || !isset($manifest['scripts']['check'])) { throw new RuntimeException('Preset must retain tests and select runtime/dev packages explicitly.'); }
    if (!str_starts_with(file_get_contents($root.'/AGENTS.md'), "Handwritten instructions\n") || !str_contains(file_get_contents($root.'/ENGINEERING.md'), 'Simplicity scales. Complexity fails.')) { throw new RuntimeException('Constitution must preserve instructions.'); }
    if (str_contains(file_get_contents($root.'/rector.php'), 'final_class') || !str_contains(file_get_contents($root.'/config/essentials.php'), 'Unguard::class => false')) { throw new RuntimeException('Unsafe defaults.'); }
    $before = hash_file('sha256', $root.'/composer.json');
    try { (new EngineeringPreset())->prepare($root); throw new LogicException('Existing configuration overwritten.'); }
    catch (RuntimeException) { }
    if (hash_file('sha256', $root.'/composer.json') !== $before) { throw new RuntimeException('Conflict mutated manifest.'); }
    echo "PASS: explicit engineering dependencies, preserved scripts/instructions, constitution, safe defaults and conflict refusal.\n";
} finally {
    $remove = static function (string $path) use (&$remove): void {
        if (!is_dir($path) || is_link($path)) { unlink($path); return; }
        foreach (new FilesystemIterator($path) as $file) { $remove($file->getPathname()); }
        rmdir($path);
    };
    $remove($root);
}
