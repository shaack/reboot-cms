<?php

require_once __DIR__ . "/../vendor/autoload.php";
require_once __DIR__ . "/TestRunner.php";

use Shaack\Tests\TestRunner;

$runner = new TestRunner();
exit($runner->run(__DIR__));
