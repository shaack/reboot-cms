<?php
/**
 * Router for PHP's built-in web server.
 * Usage: php -S localhost:8080 -t web web/router.php
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// Strip /web prefix so /web/... maps to the same as /...
$webPrefixStripped = false;
if (preg_match('#^/web(/|$)#', $uri)) {
    $uri = substr($uri, 4) ?: '/';
    $_SERVER['REQUEST_URI'] = $uri;
    $webPrefixStripped = true;
}

// Serve existing files directly (not directories)
if ($uri !== '/' && is_file(__DIR__ . $uri)) {
    if ($webPrefixStripped) {
        // Cannot use return false after rewriting URI, serve the file manually
        $filePath = __DIR__ . $uri;
        $mimeType = mime_content_type($filePath) ?: 'application/octet-stream';
        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
        return;
    }
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
