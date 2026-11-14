#!/usr/local/bin/php
<?php

declare(strict_types=1);

// Model GNU 9.2-9.4's nonzero no-clobber skip; positive moves use real GNU mv.
$arguments = array_slice($argv, 1);
if ($arguments === ['--version']) {
    echo "mv (GNU coreutils) 9.4\n";
    exit(0);
}
$source = $arguments[count($arguments) - 2] ?? '';
$target = $arguments[count($arguments) - 1] ?? '';
if (in_array('--no-clobber', $arguments, true) && is_dir($source) && (file_exists($target) || is_link($target))) {
    exit(1);
}
pcntl_exec('/bin/mv', $arguments);
exit(126);
