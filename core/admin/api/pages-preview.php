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
$previewContent = (string)($request->getParam("content") ?? "");
$previewPath = realpath($pagesDir . $previewPageName);
// Only Markdown pages, the editor edits nothing else
if ($previewPath && str_starts_with($previewPath, $pagesDir . DIRECTORY_SEPARATOR)
    && str_ends_with($previewPath, ".md") && is_file($previewPath)) {
    header('Content-Type: text/html; charset=utf-8');
    header('X-Frame-Options: SAMEORIGIN');
    header('Content-Security-Policy: default-src * \'unsafe-inline\' \'unsafe-eval\'; img-src * data:;');
    $previewSite = new \Shaack\Reboot\Site($reboot, "/site", "");
    $previewRequest = new \Shaack\Reboot\Request($previewSite, $reboot->getBaseWebPath(), preg_replace('/\/index$/', '/', preg_replace('/\.md$/', '', $previewPageName)), []);
    $previewPageObj = new Page($reboot, $previewSite);
    // The draft is rendered from memory, the published file stays untouched
    $previewPageObj->setPreviewContent($previewPath, $previewContent);
    $previewHtml = \Shaack\Reboot\renderPage($previewSite, $previewPageObj, $previewRequest);
    $previewHtml = str_replace('</head>', '<style>*,*::before,*::after{transition:none!important;animation:none!important;scroll-behavior:auto!important;}</style></head>', $previewHtml);
    echo $previewHtml;
} else {
    http_response_code(404);
    echo 'Page not found';
}
exit;
