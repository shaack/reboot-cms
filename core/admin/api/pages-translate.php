<?php
/** JSON API: translate the values of fields from one language of the site into another, see Translator */

use Shaack\Reboot\AddOns\Admin\Translator;
use Shaack\Reboot\CsrfProtection;

CsrfProtection::validate($request);
while (ob_get_level()) {
    ob_end_clean();
}
header('Content-Type: application/json; charset=utf-8');
$translator = Translator::fromConfig($localConfig);
$languages = $defaultSite->getLanguages();
$source = (string)($request->getParam("source", "post") ?? "");
$target = (string)($request->getParam("target", "post") ?? "");
$texts = $request->getParam("texts", "post");
$format = $request->getParam("format", "post") === "markdown" ? "markdown" : "text";
if (!$translator || !in_array($source, $languages, true) || !in_array($target, $languages, true)
    || $source === $target || !is_array($texts)) {
    http_response_code(400);
    echo json_encode(['error' => $translator ? "Nothing to translate." : "No key for DeepL in local/config.local.yml."]);
    exit;
}
try {
    echo json_encode(['texts' => $translator->translate(array_map("strval", array_values($texts)), $source, $target, $format)], JSON_UNESCAPED_UNICODE);
} catch (\RuntimeException $e) {
    http_response_code(502);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
exit;
