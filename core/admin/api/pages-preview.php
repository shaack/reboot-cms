<?php
/** JSON API: live preview (render page content without saving) */

use Shaack\Reboot\AddOns\Admin\FieldInput;
use Shaack\Reboot\AddOns\Admin\StructuredPageHandler;
use Shaack\Reboot\Blueprint;
use Shaack\Reboot\CsrfProtection;
use Shaack\Reboot\Page;
use Shaack\Reboot\YamlWriter;

CsrfProtection::validate($request);
while (ob_get_level()) {
    ob_end_clean();
}
$previewPageName = $request->getParam("page") ?? "";
$previewPageName = str_replace("..", "", $previewPageName);
$previewContent = (string)($request->getParam("content") ?? "");
$previewPath = realpath($pagesDir . $previewPageName);
// A structured page posts its form, the YAML is made from it as saving would make it
$previewFields = $request->getParam("fields", "post");
if ($previewPath && str_starts_with($previewPath, $pagesDir . DIRECTORY_SEPARATOR)
    && str_ends_with($previewPath, ".yml") && is_array($previewFields)) {
    $previewBlueprint = Blueprint::forPage($defaultSite);
    $previewHandler = new StructuredPageHandler($previewBlueprint, $pagesDir, $historyDir, $historyMaxVersions);
    $previewValues = $previewHandler->fromInput($previewFields);
    try {
        $previewExisting = $previewHandler->read($previewPath);
    } catch (\RuntimeException $e) {
        $previewExisting = [];
    }
    foreach ($previewExisting as $name => $value) {
        if (!array_key_exists($name, $previewBlueprint->getFields())) {
            $previewValues[$name] = $value;
        }
    }
    $previewContent = YamlWriter::dump(FieldInput::clean($previewBlueprint, $previewValues));
}
// Only Markdown pages and structured pages, the editor edits nothing else
if ($previewPath && str_starts_with($previewPath, $pagesDir . DIRECTORY_SEPARATOR)
    && (str_ends_with($previewPath, ".md") || str_ends_with($previewPath, ".yml")) && is_file($previewPath)) {
    header('Content-Type: text/html; charset=utf-8');
    header('X-Frame-Options: SAMEORIGIN');
    header('Content-Security-Policy: default-src * \'unsafe-inline\' \'unsafe-eval\'; img-src * data:;');
    $previewSite = new \Shaack\Reboot\Site($reboot, "/site", "");
    // `about.de.md` is /de/about, a structured page in the language the form shows
    [$previewAddress, $previewLanguage] = $previewSite->pageFileAddress($previewPageName);
    $requestedLanguage = (string)($request->getParam("lang", "get") ?? "");
    if (str_ends_with($previewPageName, ".yml") && in_array($requestedLanguage, $previewSite->getLanguages(), true)) {
        $previewLanguage = $requestedLanguage;
    }
    $previewRequest = new \Shaack\Reboot\Request($previewSite, $reboot->getBaseWebPath(), $previewSite->languagePath($previewAddress, $previewLanguage), []);
    $previewPageObj = new Page($reboot, $previewSite);
    // The draft is rendered from memory, the published file stays untouched
    $previewPageObj->setPreviewContent($previewPath, $previewContent);
    $previewHtml = \Shaack\Reboot\renderPage($previewSite, $previewPageObj, $previewRequest);
    // what the AddOns do with the finished page, like the colors of CodeHighlighting, as on the site
    foreach ($previewSite->getAddOns() as $addOn) {
        $previewHtml = $addOn->postRender($previewRequest, $previewHtml);
    }
    $previewHtml = str_replace('</head>', '<style>*,*::before,*::after{transition:none!important;animation:none!important;scroll-behavior:auto!important;}</style></head>', $previewHtml);
    echo $previewHtml;
} else {
    http_response_code(404);
    echo 'Page not found';
}
exit;
