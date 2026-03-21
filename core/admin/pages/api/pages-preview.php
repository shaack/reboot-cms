<?php
/** JSON API: live preview (render page content without saving) */

use Shaack\Reboot\CsrfProtection;
use Shaack\Reboot\Page;

CsrfProtection::validate($request);
while (ob_get_level()) {
    ob_end_clean();
}
$previewPageName = $request->getParam("page") ?? "";
$previewPageName = str_replace("..", "", $previewPageName);
$previewContent = $request->getParam("content") ?? "";
$previewPath = $pagesDir . $previewPageName;
$resolvedPreview = realpath(dirname($previewPath));
if ($resolvedPreview && strncmp($resolvedPreview, $pagesDir, strlen($pagesDir)) === 0 && is_file($previewPath)) {
    $originalContent = file_get_contents($previewPath);
    file_put_contents($previewPath, $previewContent, LOCK_EX);
    header('Content-Type: text/html; charset=utf-8');
    header('X-Frame-Options: SAMEORIGIN');
    header('Content-Security-Policy: default-src * \'unsafe-inline\' \'unsafe-eval\'; img-src * data:;');
    $previewSite = new \Shaack\Reboot\Site($reboot, "/site", "");
    $previewRequest = new \Shaack\Reboot\Request($previewSite, $reboot->getBaseWebPath(), preg_replace('/\/index$/', '/', preg_replace('/\.md$/', '', $previewPageName)), []);
    $previewPageObj = new Page($reboot, $previewSite);
    $previewHtml = \Shaack\Reboot\renderPage($previewSite, $previewPageObj, $previewRequest);
    $previewHtml = str_replace('</head>', '<style>*,*::before,*::after{transition:none!important;animation:none!important;scroll-behavior:auto!important;}</style></head>', $previewHtml);
    echo $previewHtml;
    file_put_contents($previewPath, $originalContent, LOCK_EX);
} else {
    http_response_code(404);
    echo 'Page not found';
}
exit;
