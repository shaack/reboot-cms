<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

namespace Shaack\Reboot\Admin;

class MediaHelper
{
    public static function formatFileSize(int $bytes): string
    {
        if ($bytes >= 1048576) return round($bytes / 1048576, 1) . " MB";
        if ($bytes >= 1024) return round($bytes / 1024, 1) . " KB";
        return $bytes . " B";
    }

    public static function isImageType(string $mimeType): bool
    {
        return str_starts_with($mimeType, 'image/');
    }
}
