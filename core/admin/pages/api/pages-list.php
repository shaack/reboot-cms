<?php
/** JSON API: list pages (used by InsertPageLink editor tool) */

use Shaack\Reboot\Admin\AdminHelper;

$pageList = [];
foreach ($pages as $page) {
    $pagePathInfo = pathinfo($page["name"]);
    if (!array_key_exists("extension", $pagePathInfo) || $pagePathInfo["extension"] !== "md") {
        continue;
    }
    $relPath = str_replace($pagesDir, "", $page["name"]);
    $webPath = preg_replace('/\.md$/', '', $relPath);
    $webPath = preg_replace('/\/index$/', '/', $webPath);
    if ($webPath === '/index') $webPath = '/';
    $pageList[] = [
        'filePath' => $relPath,
        'webPath' => $webPath,
        'name' => basename($relPath, '.md')
    ];
}
AdminHelper::jsonResponse($pageList);
