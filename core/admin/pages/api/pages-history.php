<?php
/** JSON API: page history */

use Shaack\Reboot\Admin\AdminHelper;
use Shaack\Reboot\Admin\PageHistoryHelper;

$historyPage = $request->getParam("page") ?? "";
$historyPage = str_replace("..", "", $historyPage);
$versions = PageHistoryHelper::getPageHistory($historyPage, $pagesDir, $historyDir);
$result = array_map(function ($v) {
    return [
        'filename' => $v['filename'],
        'timestamp' => $v['timestamp'],
        'size' => $v['size'],
        'content' => file_get_contents($v['file'])
    ];
}, $versions);
AdminHelper::jsonResponse($result);
