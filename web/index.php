<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

require __DIR__ . '/../vendor/autoload.php';

// Security headers
header("X-Frame-Options: SAMEORIGIN");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Permissions-Policy: camera=(), microphone=(), geolocation=()");

$thisDir = dirname($_SERVER['SCRIPT_FILENAME']);
$reboot = new Shaack\Reboot\Reboot(dirname($thisDir), "/site");