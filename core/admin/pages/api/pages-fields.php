<?php
/** JSON API: get block field definitions with current values for a page */

use Shaack\Reboot\Page;

$pageName = $request->getParam("page") ?? "";
$pageName = str_replace("..", "", $pageName);
$pagePath = $pagesDir . $pageName;
$resolvedPath = realpath(dirname($pagePath));

while (ob_get_level()) {
    ob_end_clean();
}
header('Content-Type: application/json; charset=utf-8');

if (!$resolvedPath || strncmp($resolvedPath, $pagesDir, strlen($pagesDir)) !== 0 || !is_file($pagePath)) {
    http_response_code(404);
    echo json_encode(['error' => 'Page not found']);
    exit;
}

$pageObj = new Page($reboot, $defaultSite);
$blocks = $pageObj->getBlockFields($pagePath);

echo json_encode(['blocks' => $blocks], JSON_UNESCAPED_UNICODE);
exit;
