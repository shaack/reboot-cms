<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */
require __DIR__ . '/../../vendor/autoload.php';
$thisDir = dirname($_SERVER['SCRIPT_FILENAME']);
$reboot = new Shaack\Reboot\Reboot(dirname(dirname($thisDir)), "/core/admin", "/admin");