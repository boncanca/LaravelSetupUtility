<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

use LaravelSetup\ManifestTransaction;
use LaravelSetup\Planner;
use LaravelSetup\Resolver;

$root = sys_get_temp_dir().'/laravel transaction test '.bin2hex(random_bytes(6));
mkdir($root, 0700);
$project = $root.'/app';
mkdir($project, 0700);
$original = json_encode(['require' => ['php' => '^8.3', 'laravel/framework' => '^13.0']], JSON_THROW_ON_ERROR);
file_put_contents($project.'/composer.json', $original);
file_put_contents($project.'/untracked.txt', 'keep');
$fake = $root.'/composer.php';
file_put_contents($fake, '<?php file_put_contents("composer.lock", json_encode(["packages" => [["name" => "laravel/framework", "version" => "v13.0.0"]], "packages-dev" => [["name" => "laravel/pint", "version" => "v1.24.0"]]]));');
$plan = (new Planner())->create($project, ['pint'], 'stable');
$bundle = $root.'/bundle';
try {
    $resolver = new Resolver([PHP_BINARY, '-c', php_ini_loaded_file() ?: '', $fake], 5);
    $summary = $resolver->resolve($project, $plan, $bundle);
    if ($summary['lock_retained'] !== true || !is_file($bundle.'/bundle.json') || (fileperms($bundle) & 0777) !== 0700) {
        throw new RuntimeException('Private replayable bundle is required.');
    }
    $transaction = new ManifestTransaction();
    $result = $transaction->apply($project, $bundle);
    if ($result['status'] !== 'manifests_applied' || $result['dependencies_installed'] !== false || !is_file($project.'/composer.lock') || file_get_contents($project.'/untracked.txt') !== 'keep') {
        throw new RuntimeException('Only manifests may be applied.');
    }
    if ($transaction->apply($project, $bundle)['status'] !== 'already_applied') {
        throw new RuntimeException('Apply must be idempotent.');
    }
    file_put_contents($project.'/composer.json', "\n", FILE_APPEND);
    try {
        $transaction->recover($project, $bundle);
        throw new LogicException('Recovery must refuse user edits.');
    } catch (RuntimeException) {
    }
    file_put_contents($project.'/composer.json', file_get_contents($bundle.'/composer.json'));
    $transaction->recover($project, $bundle);
    if (file_get_contents($project.'/composer.json') !== $original || file_exists($project.'/composer.lock')) {
        throw new RuntimeException('Recovery must restore exact original bytes and absent lock.');
    }
    try {
        (new ManifestTransaction(static function (string $name): void {
            if ($name === 'composer.json') {
                throw new RuntimeException('Injected second-write failure.');
            }
        }))->apply($project, $bundle);
        throw new LogicException('Injected failure must fail.');
    } catch (RuntimeException) {
    }
    if (file_get_contents($project.'/composer.json') !== $original || file_exists($project.'/composer.lock')) {
        throw new RuntimeException('Failed apply must roll back the first write.');
    }
    $held = fopen(sys_get_temp_dir().'/laravel-setup-target-locks/'.hash('sha256', realpath($project)).'.lock', 'c+b');
    flock($held, LOCK_EX);
    try {
        $transaction->apply($project, $bundle);
        throw new LogicException('Concurrent writer lock must be exclusive.');
    } catch (RuntimeException) {
    } finally {
        flock($held, LOCK_UN);
        fclose($held);
    }
    $transaction->apply($project, $bundle);
    unlink($project.'/composer.lock');
    $journal = json_decode(file_get_contents($bundle.'/journal.json'), true, flags: JSON_THROW_ON_ERROR);
    $journal['status'] = 'prepared';
    file_put_contents($bundle.'/journal.json', json_encode($journal, JSON_THROW_ON_ERROR));
    $transaction->recover($project, $bundle);
    if (file_get_contents($project.'/composer.json') !== $original || file_exists($project.'/composer.lock')) {
        throw new RuntimeException('Recovery must handle interrupted first-file-only state.');
    }
    file_put_contents($project.'/composer.lock', "{\"before\":true}\n");
    $oldLock = file_get_contents($project.'/composer.lock');
    $lockedPlan = (new Planner())->create($project, ['pint'], 'stable');
    $secondBundle = $root.'/locked-bundle';
    $resolver->resolve($project, $lockedPlan, $secondBundle);
    $transaction->apply($project, $secondBundle);
    $transaction->recover($project, $secondBundle);
    if (file_get_contents($project.'/composer.lock') !== $oldLock || file_get_contents($project.'/composer.json') !== $original) {
        throw new RuntimeException('Existing lock must be restored byte for byte.');
    }
    unlink($project.'/composer.lock');
    try {
        $transaction->apply($project, $secondBundle);
        throw new LogicException('Bundle must reject changed original lock presence.');
    } catch (RuntimeException) {
    }
    file_put_contents($project.'/actual-composer.json', $original);
    unlink($project.'/composer.json');
    symlink($project.'/actual-composer.json', $project.'/composer.json');
    try {
        $transaction->apply($project, $bundle);
        throw new LogicException('Even inward manifest symlinks must be rejected for writes.');
    } catch (RuntimeException) {
    }
    unlink($project.'/composer.json');
    file_put_contents($project.'/composer.json', $original);
    unlink($project.'/actual-composer.json');
    $resolvedLock = file_get_contents($bundle.'/composer.lock');
    file_put_contents($bundle.'/composer.lock', "\n", FILE_APPEND);
    try {
        $transaction->apply($project, $bundle);
        throw new LogicException('Tampered lock artifact must fail.');
    } catch (RuntimeException) {
    }
    file_put_contents($bundle.'/composer.lock', $resolvedLock);
    $originalMeta = file_get_contents($bundle.'/bundle.json');
    $badLock = json_decode($resolvedLock, true, flags: JSON_THROW_ON_ERROR);
    $badLock['packages'] = [];
    $badLock = json_encode($badLock, JSON_THROW_ON_ERROR);
    file_put_contents($bundle.'/composer.lock', $badLock);
    $meta = json_decode($originalMeta, true, flags: JSON_THROW_ON_ERROR);
    $meta['hashes']['composer.lock'] = hash('sha256', $badLock);
    file_put_contents($bundle.'/bundle.json', json_encode($meta, JSON_THROW_ON_ERROR));
    try {
        $transaction->apply($project, $bundle);
        throw new LogicException('A lock missing Laravel must fail even with recomputed integrity hashes.');
    } catch (RuntimeException) {
    }
    file_put_contents($bundle.'/composer.lock', $resolvedLock);
    file_put_contents($bundle.'/bundle.json', $originalMeta);
    file_put_contents($bundle.'/composer.json', "\n", FILE_APPEND);
    try {
        $transaction->apply($project, $bundle);
        throw new LogicException('Tampered bundle must fail.');
    } catch (RuntimeException) {
    }
    foreach ([$project.'/bundle', $bundle] as $destination) {
        try {
            $resolver->resolve($project, $plan, $destination);
            throw new LogicException('Target or existing bundle destination must fail.');
        } catch (RuntimeException) {
        }
    }
    symlink($root, $root.'/alias');
    try {
        $resolver->resolve($project, $plan, $root.'/alias/new-bundle');
        throw new LogicException('Symlink destination must fail.');
    } catch (RuntimeException) {
    }
    echo "PASS: private artifact, file-only apply, idempotence, recovery, tamper/user-edit refusal and injected rollback.\n";
} finally {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $file) {
        $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }
    rmdir($root);
}
