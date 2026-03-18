<?php
/**
 * Router for PHP's built-in web server.
 * Usage: php -S localhost:8080 -t web web/router.php
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// Serve existing files and directories directly
if ($uri !== '/' && file_exists(__DIR__ . $uri)) {
    return false;
}

// Route everything else through index.php
$_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'];
require __DIR__ . '/index.php';
