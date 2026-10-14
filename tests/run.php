<?php

require_once __DIR__ . "/../vendor/autoload.php";
require_once __DIR__ . "/TestRunner.php";

use Shaack\Reboot\AddOnLoader;
use Shaack\Tests\TestRunner;

// Make the helper classes of the shipped AddOns available to the tests
foreach (["/../core/addons", "/../core/admin/addons"] as $pool) {
    foreach (glob(__DIR__ . $pool . "/*", GLOB_ONLYDIR) as $dir) {
        AddOnLoader::registerAutoloader(basename($dir), $dir);
    }
}

$runner = new TestRunner();
exit($runner->run(__DIR__));
