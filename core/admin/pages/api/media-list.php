<?php
/** JSON API: list media files (used by InsertMedia editor tool) */

use Shaack\Reboot\Admin\AdminHelper;

$items = [];
if (is_dir($resolvedPath)) {
    $d = dir($resolvedPath);
    while (false !== ($entry = $d->read())) {
        if ($entry[0] === ".") continue;
        $entryPath = $resolvedPath . "/" . $entry;
        $isDir = is_dir($entryPath);
        $type = $isDir ? 'folder' : mime_content_type($entryPath);
        $webPath = $reboot->getBaseWebPath() . "/media" . ($currentPath ? "/" . $currentPath : "") . "/" . $entry;
        $items[] = [
            'name' => $entry,
            'isDir' => $isDir,
            'type' => $type,
            'isImage' => !$isDir && str_starts_with($type, 'image/'),
            'webPath' => $webPath,
            'subPath' => ($currentPath ? $currentPath . "/" : "") . $entry
        ];
    }
    $d->close();
}
usort($items, function ($a, $b) {
    if ($a['isDir'] !== $b['isDir']) return $b['isDir'] - $a['isDir'];
    return strcasecmp($a['name'], $b['name']);
});
AdminHelper::jsonResponse(['path' => $currentPath, 'items' => $items]);
