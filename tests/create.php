<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

use LaravelSetup\Creator;

$temporary = realpath(getenv('TMPDIR') ?: '/tmp');
$root = $temporary.'/create behavior '.bin2hex(random_bytes(6));
mkdir($root, 0700);
// Set before the first sys_get_temp_dir() call; independent test processes stay isolated.
putenv('TMPDIR='.$root);
$_ENV['TMPDIR'] = $root;
if (sys_get_temp_dir() !== $root) {
    throw new RuntimeException('Create tests require a fresh isolated temporary directory.');
}
$fake = $root.'/composer.php';
$source = <<<'FAKE'
<?php
if ($argv[1] === '--version') { echo "Composer version 2.8.12\n"; exit; }
if ($argv[1] !== 'create-project' || !in_array('--no-scripts', $argv, true) || !in_array('--no-plugins', $argv, true) || !in_array('laravel/laravel', $argv, true) || getenv('CREATE_SECRET') !== false || getenv('CREATE_ENV_SECRET') !== false || getenv('COMPOSER_AUTH') !== '{}' || getenv('HOME') !== getcwd().'/home') { exit(21); }
$app = getcwd().'/app';
mkdir($app, 0700);
mkdir($app.'/vendor'); mkdir($app.'/bootstrap'); mkdir($app.'/public');
file_put_contents($app.'/composer.json', json_encode(['name'=>'laravel/laravel','require'=>['php'=>'^8.3','laravel/framework'=>'^12.0']]));
file_put_contents($app.'/composer.lock', json_encode(['packages'=>[['name'=>'laravel/framework','version'=>'v12.0.0']],'packages-dev'=>[]]));
file_put_contents($app.'/.env.example', 'APP_ENV=local');
file_put_contents($app.'/vendor/autoload.php', <<<'AUTO'
<?php
namespace Illuminate\Foundation;
class Application {
    public function make($class) { return new class {
        public function handle($request) { return new class { public function getStatusCode() { return 200; } }; }
        public function terminate($request, $response) {}
    }; }
}
namespace Illuminate\Http;
class Request { public static function create($uri) { return $uri; } }
AUTO);

file_put_contents($app.'/public/index.php', '<?php');
file_put_contents($app.'/bootstrap/app.php', '<?php return new \\Illuminate\\Foundation\\Application;');
file_put_contents($app.'/artisan', '<?php if (!in_array($argv[1], ["key:generate", "package:discover", "test"], true)) exit(22);');
// A synthetic framework double verifies orchestration only, never upstream compatibility.
FAKE;
file_put_contents($fake, $source);
$remove = function (string $path) use (&$remove): void {
    if (is_dir($path) && ! is_link($path)) {
        foreach (new FilesystemIterator($path) as $file) {
            $remove($file->getPathname());
        } rmdir($path);
    } else {
        unlink($path);
    }
};
$expectFailure = function (callable $operation, string $expected): void {
    try {
        $operation();
    } catch (RuntimeException $error) {
        if (! str_contains(strtolower($error->getMessage()), strtolower($expected))) {
            throw new LogicException('Wrong failure phase: '.$error->getMessage());
        }

        return;
    }
    throw new LogicException('Expected safe refusal.');
};
try {
    putenv('CREATE_SECRET=must-not-leak');
    $_ENV['CREATE_ENV_SECRET'] = 'must-not-leak';
    $creator = new Creator([PHP_BINARY, $fake], 3);
    // Path guards must run before Composer, including empty existing targets.
    foreach (['existing', 'nonempty'] as $name) {
        mkdir($root.'/'.$name);
    }
    file_put_contents($root.'/nonempty/sentinel', 'keep');
    symlink($root.'/existing', $root.'/linked');
    foreach ([$root.'/existing', $root.'/nonempty', $root.'/linked/new', $root.'/../escape', $root.'/bad;name', $root.'/missing/new'] as $path) {
        $expectFailure(fn () => $creator->create($path), 'target');
    }
    if (file_get_contents($root.'/nonempty/sentinel') !== 'keep') {
        throw new LogicException('User files changed.');
    }
    $before = glob(sys_get_temp_dir().'/laravel-setup-create-*');
    // A Composer failure must be redacted and remove its private stage.
    file_put_contents($fake, '<?php if ($argv[1] === "--version") { echo "Composer version 2.8.12"; exit; } fwrite(STDERR,"must-not-leak"); exit(1);');
    $expectFailure(fn () => $creator->create($root.'/failed'), 'Composer installation');
    if (file_exists($root.'/failed') || glob(sys_get_temp_dir().'/laravel-setup-create-*') !== $before) {
        throw new LogicException('Failed create left residue.');
    }
    file_put_contents($fake, '<?php if ($argv[1] === "--version") { echo "Composer version 2.8.12"; exit; } sleep(10);');
    $expectFailure(fn () => (new Creator([PHP_BINARY, $fake], 1))->create($root.'/timeout'), 'Composer installation');
    if (file_exists($root.'/timeout') || glob(sys_get_temp_dir().'/laravel-setup-create-*') !== $before) {
        throw new LogicException('Timeout left residue.');
    }
    file_put_contents($fake, $source);
    // Synthetic output must never be accepted as a real Laravel application.
    $invalid = str_replace("'name'=>'laravel/laravel'", "'name'=>'invalid/skeleton'", $source);
    file_put_contents($fake, $invalid);
    $expectFailure(fn () => $creator->create($root.'/synthetic'), 'Generated Laravel manifest');
    if (file_exists($root.'/synthetic') || glob(sys_get_temp_dir().'/laravel-setup-create-*') !== $before) {
        throw new LogicException('Invalid app left residue.');
    }
    file_put_contents($fake, $source);
    $result = $creator->create($root.'/success');
    if ($result['engineering_preset'] !== false || $result['migrations_run'] !== false || ! is_file($root.'/success/composer.lock') || glob(sys_get_temp_dir().'/laravel-setup-create-*') !== $before) {
        throw new LogicException('Synthetic success contract failed.');
    }
    $expectFailure(fn () => $creator->create($root.'/success'), 'Target already exists');
    if (! is_file($root.'/success/composer.lock')) {
        throw new LogicException('Rerun touched existing app.');
    }
    $race = $root.'/race';
    $raceSource = $source."\nfile_put_contents(\$app.'/artisan', ".var_export('<?php if ($argv[1] === "test") { mkdir('.var_export($race, true).'); file_put_contents('.var_export($race.'/sentinel', true).', "keep"); }', true).');';
    file_put_contents($fake, $raceSource);
    $expectFailure(fn () => $creator->create($race), 'Target already exists');
    if (file_get_contents($race.'/sentinel') !== 'keep' || glob(sys_get_temp_dir().'/laravel-setup-create-*') !== $before) {
        throw new LogicException('Destination race damaged user files.');
    }
    // A child extraction process must not outlive timeout or an early-exiting Composer.
    foreach (['timeout-child', 'early-exit-child'] as $scenario) {
        $late = $root.'/'.$scenario.'-late-write';
        $spawn = '<?php if ($argv[1] === "--version") { echo "Composer version 2.8.12"; exit; } '
            .'if (pcntl_fork() === 0) { usleep(2000000); file_put_contents('.var_export($late, true).', "orphan"); exit; } '
            .($scenario === 'early-exit-child' ? 'exit(1);' : 'sleep(10);');
        file_put_contents($fake, $spawn);
        $expectFailure(fn () => (new Creator([PHP_BINARY, $fake], 1))->create($root.'/'.$scenario), 'Composer installation');
        usleep(2200000);
        if (file_exists($late) || file_exists($root.'/'.$scenario) || glob(sys_get_temp_dir().'/laravel-setup-create-*') !== $before) {
            throw new LogicException('A Composer descendant survived '.$scenario.' or staging leaked.');
        }
    }
    file_put_contents($fake, str_replace(var_export($root.'/early-exit-child-late-write', true), var_export($root.'/cancel-late-write', true), str_replace('exit(1);', 'sleep(10);', $spawn)));
    if (extension_loaded('pcntl')) {
        $cancel = new Creator([PHP_BINARY, $fake], 5);
        pcntl_async_signals(true);
        pcntl_signal(SIGALRM, fn () => $cancel->cancel());
        pcntl_alarm(1);
        $started = microtime(true);
        $expectFailure(fn () => $cancel->create($root.'/cancelled'), 'Composer installation');
        pcntl_alarm(0);
        pcntl_signal(SIGALRM, SIG_DFL);
        usleep(2200000);
        if (file_exists($root.'/cancel-late-write') || microtime(true) - $started > 5 || file_exists($root.'/cancelled') || glob(sys_get_temp_dir().'/laravel-setup-create-*') !== $before) {
            throw new LogicException('Cancellation did not promptly clean staging.');
        }
    } else {
        echo "SKIP: asynchronous cancellation requires pcntl.\n";
    }
    echo "PASS: create path refusals, secret isolation, Composer failure, timeout, descendants, cancellation, success, rerun, destination race and cleanup (synthetic plumbing only).\n";
} finally {
    putenv('CREATE_SECRET');
    unset($_ENV['CREATE_ENV_SECRET']);
    $remove($root);
}
