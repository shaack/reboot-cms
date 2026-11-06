<?php
/** JSON API: check the Markdown in the editor while it is typed, without saving */

use Shaack\Reboot\AddOns\Admin\PageValidator;
use Shaack\Reboot\CsrfProtection;

CsrfProtection::validate($request);
while (ob_get_level()) {
    ob_end_clean();
}
header('Content-Type: application/json; charset=utf-8');
$validateSite = new \Shaack\Reboot\Site($reboot, "/site", "");
$content = str_replace("\r\n", "\n", (string)($request->getParam("content") ?? ""));
echo json_encode(['problems' => PageValidator::validate($validateSite, $content)], JSON_UNESCAPED_UNICODE);
exit;
