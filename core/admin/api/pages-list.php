<?php
/** JSON API: list pages (used by InsertPageLink editor tool) */

use Shaack\Reboot\AddOns\Admin\AdminHelper;

$pageList = [];
foreach ($pages as $page) {
    $pagePathInfo = pathinfo($page["name"]);
    if (!in_array($pagePathInfo["extension"] ?? "", ["md", "yml"], true)) {
        continue;
    }
    $relPath = str_replace($pagesDir, "", $page["name"]);
    $webPath = preg_replace('/\.(md|yml)$/', '', $relPath);
    $webPath = preg_replace('/\/index$/', '/', $webPath);
    if ($webPath === '/index') $webPath = '/';
    $pageList[] = [
        'filePath' => $relPath,
        'webPath' => $webPath,
        'name' => preg_replace('/\.(md|yml)$/', '', basename($relPath))
    ];
}
AdminHelper::jsonResponse($pageList);
