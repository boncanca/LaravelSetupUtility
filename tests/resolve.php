<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

use LaravelSetup\Planner;
use LaravelSetup\Resolver;

$root = sys_get_temp_dir().'/laravel resolver test '.bin2hex(random_bytes(6));
mkdir($root, 0700);
$project = $root.'/app';
mkdir($project, 0700);
$manifest = ['require' => ['php' => '^8.3', 'laravel/framework' => '^13.0'], 'scripts' => ['post-update-cmd' => 'touch must-not-run'], 'config' => new stdClass(), 'extra' => new stdClass(), 'autoload' => (object) ['psr-4' => new stdClass()]];
file_put_contents($project.'/composer.json', json_encode($manifest, JSON_THROW_ON_ERROR));
$fake = $root.'/composer.php';
$fakeSource = <<<'PHP'
<?php
$required = ['--no-plugins', '--no-scripts', '--no-interaction', '--no-install'];
foreach ($required as $flag) {
    if (!in_array($flag, $argv, true)) {
        exit(10);
    }
}
if (getenv('COMPOSER') !== 'composer.json' || getenv('COMPOSER_AUTH') !== '{}' || getenv('COMPOSER_VENDOR_DIR') !== getcwd().'/vendor' || getenv('COMPOSER_HOME') !== getcwd().'/home' || getenv('COMPOSER_ALLOW_SUPERUSER') !== false || getenv('COMPOSER_DISABLE_NETWORK') !== false || getenv('COMPOSER_NO_DEV') !== false || (fileperms(getcwd()) & 0777) !== 0700) {
    exit(11);
}
$rawManifest = json_decode(file_get_contents('composer.json'));
if (!is_object($rawManifest->config ?? null) || !is_object($rawManifest->extra ?? null) || !is_object($rawManifest->scripts ?? null) || !is_object($rawManifest->autoload->{'psr-4'} ?? null)) {
    exit(14);
}
$manifest = json_decode(file_get_contents('composer.json'), true);
if (($manifest['require-dev']['laravel/pint'] ?? null) !== '^1.24') {
    exit(12);
}
file_put_contents('seen-stage', getcwd());
file_put_contents('composer.lock', json_encode(['packages' => [['name' => 'laravel/framework', 'version' => 'v13.0.0']], 'packages-dev' => [['name' => 'laravel/pint', 'version' => 'v1.24.0']]]));
PHP;
file_put_contents($fake, $fakeSource);
$plan = (new Planner())->create($project, ['pint'], 'stable');
$before = file_get_contents($project.'/composer.json');
$stagesBefore = glob(sys_get_temp_dir().'/laravel-setup-resolve-*');
try {
    putenv('COMPOSER=must-not-use.json');
    putenv('COMPOSER_AUTH={"secret":"must-not-disclose"}');
    putenv('COMPOSER_ALLOW_SUPERUSER=1');
    putenv('COMPOSER_DISABLE_NETWORK=1');
    putenv('COMPOSER_NO_DEV=1');
    $resolver = new Resolver([PHP_BINARY, '-c', php_ini_loaded_file() ?: '', $fake], 5);
    $result = $resolver->resolve($project, $plan);
    if ($result['apply_ready'] !== false || $result['compatibility']['dependencies'] !== 'solved_in_staging' || $result['resolved_packages'][1] !== ['name' => 'laravel/pint', 'version' => 'v1.24.0'] || strlen($result['lock_sha256']) !== 64) {
        throw new RuntimeException('Resolution must report concrete versions and lock hash without apply readiness.');
    }
    if (file_get_contents($project.'/composer.json') !== $before || count(scandir($project)) !== 3 || glob(sys_get_temp_dir().'/laravel-setup-resolve-*') !== $stagesBefore) {
        throw new RuntimeException('Resolution must not mutate the project or leave its staging directory.');
    }
    file_put_contents($project.'/composer.lock', '{}');
    $lockedPlan = (new Planner())->create($project, ['pint'], 'stable');
    file_put_contents($fake, str_replace('file_put_contents(\'seen-stage\', getcwd());', 'if (!in_array("laravel/pint", $argv, true) || in_array("--with-all-dependencies", $argv, true) || in_array("laravel/framework", $argv, true)) { exit(13); }', $fakeSource));
    $resolver->resolve($project, $lockedPlan);
    unlink($project.'/composer.lock');
    file_put_contents($fake, '<?php sleep(3);');
    try {
        (new Resolver([PHP_BINARY, '-c', php_ini_loaded_file() ?: '', $fake], 1))->resolve($project, $plan);
        throw new LogicException('Timeout must fail.');
    } catch (RuntimeException) {
    }
    file_put_contents($fake, $fakeSource."\nfile_put_contents(".var_export($project.'/composer.json', true).", \"\\n\", FILE_APPEND);\n");
    try {
        $resolver->resolve($project, $plan);
        throw new LogicException('Post-solve drift must fail.');
    } catch (RuntimeException) {
    }
    file_put_contents($project.'/composer.json', $before);
    file_put_contents($fake, $fakeSource);
    foreach ([['repositories' => [['type' => 'path', 'url' => '../private']]], ['config' => ['vendor-dir' => '/outside']], ['config' => ['secure-http' => false]], ['unknown-hook' => 'must-not-disclose']] as $unsupported) {
        file_put_contents($project.'/composer.json', json_encode(array_merge($manifest, $unsupported), JSON_THROW_ON_ERROR));
        $unsupportedPlan = (new Planner())->create($project, ['pint'], 'stable');
        try {
            $resolver->resolve($project, $unsupportedPlan);
            throw new LogicException('Unsupported manifest must fail.');
        } catch (RuntimeException $error) {
            if (str_contains($error->getMessage(), 'must-not-disclose')) {
                throw new LogicException('Failure must redact input data.');
            }
        }
    }
    file_put_contents($project.'/composer.json', $before."\n");
    try {
        $resolver->resolve($project, $plan);
        throw new LogicException('Drifted plan must fail.');
    } catch (RuntimeException) {
    }
    file_put_contents($project.'/composer.json', $before);
    file_put_contents($fake, '<?php fwrite(STDERR, "must-not-disclose"); exit(2);');
    try {
        $resolver->resolve($project, $plan);
        throw new LogicException('Failed solver must fail.');
    } catch (RuntimeException $error) {
        if (str_contains($error->getMessage(), 'must-not-disclose')) {
            throw new LogicException('Solver output must be redacted.');
        }
    }
    if (glob(sys_get_temp_dir().'/laravel-setup-resolve-*') !== $stagesBefore) {
        throw new RuntimeException('Failed resolution must clean staging.');
    }
    echo "PASS: offline fake-solver plumbing, manifest guards, isolated environment, drift/redaction and cleanup.\n";
} finally {
    putenv('COMPOSER');
    putenv('COMPOSER_AUTH');
    putenv('COMPOSER_ALLOW_SUPERUSER');
    putenv('COMPOSER_DISABLE_NETWORK');
    putenv('COMPOSER_NO_DEV');
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
