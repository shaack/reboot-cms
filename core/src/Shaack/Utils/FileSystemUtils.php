<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

namespace Shaack\Utils;

class FileSystemUtils
{
    static function getFileList($dir, $recurse = false, $depth = false): array
    {
        $directoryStructure = [];

        if (!str_ends_with($dir, "/")) {
            $dir .= "/";
        }

        $d = @dir($dir) or die("getFileList: Failed opening directory {$dir} for reading");
        while (FALSE !== ($entry = $d->read())) {
            // skip hidden files
            if ($entry[0] == ".") continue;
            if (is_dir("{$dir}{$entry}")) {
                $directoryStructure[] = [
                    'name' => "{$dir}{$entry}/",
                    'type' => filetype("{$dir}{$entry}"),
                    'size' => 0,
                    'lastmod' => filemtime("{$dir}{$entry}")
                ];
                if ($recurse && is_readable("{$dir}{$entry}/")) {
                    if ($depth === FALSE) {
                        $directoryStructure = array_merge($directoryStructure, self::getFileList("{$dir}{$entry}/", TRUE));
                    } elseif ($depth > 0) {
                        $directoryStructure = array_merge($directoryStructure, self::getFileList("{$dir}{$entry}/", TRUE, $depth - 1));
                    }
                }
            } elseif (is_readable("{$dir}{$entry}")) {
                $directoryStructure[] = [
                    'name' => "{$dir}{$entry}",
                    'type' => mime_content_type("{$dir}{$entry}"),
                    'size' => filesize("{$dir}{$entry}"),
                    'lastmod' => filemtime("{$dir}{$entry}")
                ];
            }
        }
        $d->close();
        return $directoryStructure;
    }
}