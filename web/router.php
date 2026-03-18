<?php
/**
 * Router for PHP's built-in web server.
 * Usage: php -S localhost:8080 -t web web/router.php
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// Serve existing files directly (not directories)
if ($uri !== '/' && is_file(__DIR__ . $uri)) {
    return false;
}

// Admin routes → admin/index.php
if (preg_match('#^/admin(/|$)#', $uri)) {
    $_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/admin/index.php';
    $_SERVER['PHP_SELF'] = '/admin/index.php';
    require __DIR__ . '/admin/index.php';
    return;
}

// Site routes → index.php
$_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/index.php';
$_SERVER['PHP_SELF'] = '/index.php';
require __DIR__ . '/index.php';
