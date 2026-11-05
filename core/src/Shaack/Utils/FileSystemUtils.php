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
                    'type' => \mime_content_type("{$dir}{$entry}"),
                    'size' => filesize("{$dir}{$entry}"),
                    'lastmod' => filemtime("{$dir}{$entry}")
                ];
            }
        }
        $d->close();
        return $directoryStructure;
    }

    /**
     * True if `$path` is `$dir` itself or lies below it. Both should come from
     * realpath(). Compared with the separator, so `/site/pages-old` is not
     * inside `/site/pages`. A `false` from realpath() is never inside.
     */
    static function isInside(string|false $path, string|false $dir): bool
    {
        if ($path === false || $dir === false || $dir === "") {
            return false;
        }
        $dir = rtrim($dir, DIRECTORY_SEPARATOR);
        return $path === $dir || str_starts_with($path, $dir . DIRECTORY_SEPARATOR);
    }

    /**
     * Writes `$content` to a temporary file next to `$path` and renames it over
     * `$path`. Readers see the old or the new content, never a half written or
     * empty file, also when the disk is full or the request dies midway. An
     * existing file keeps its permissions, a new one gets `$mode` or the umask.
     * A symlink is followed, the file it points to is replaced.
     * @throws \RuntimeException if the file could not be written
     */
    static function writeAtomic(string $path, string $content, ?int $mode = null): void
    {
        if (is_link($path) && ($target = realpath($path)) !== false) {
            $path = $target;
        }
        $dir = dirname($path);
        $tmp = @tempnam($dir, "." . basename($path) . ".");
        if ($tmp === false || realpath(dirname($tmp)) !== realpath($dir)) {
            // tempnam() falls back to the system temp dir, a rename from there is not atomic
            if ($tmp !== false) @unlink($tmp);
            throw new \RuntimeException("Could not write " . basename($path) . ", check the permissions of " . basename($dir) . "/");
        }
        $mode ??= is_file($path) ? fileperms($path) & 0777 : 0666 & ~umask();
        if (@file_put_contents($tmp, $content) !== strlen($content) || !@chmod($tmp, $mode) || !@rename($tmp, $path)) {
            @unlink($tmp);
            throw new \RuntimeException("Could not write " . basename($path) . ", the disk may be full");
        }
    }

    /**
     * Runs `$fn` while holding an exclusive lock for `$path`, so a read, change
     * and write of the file cannot interleave with another request. The lock is
     * on `$path.lock`, because writeAtomic() replaces the file itself.
     * @return mixed what `$fn` returns
     */
    static function withLock(string $path, callable $fn): mixed
    {
        $handle = @fopen($path . ".lock", "c");
        if ($handle === false) {
            throw new \RuntimeException("Could not open " . basename($path) . ".lock");
        }
        try {
            if (!flock($handle, LOCK_EX)) {
                throw new \RuntimeException("Could not lock " . basename($path));
            }
            return $fn();
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /**
     * Reads the JSON file `$path` under the lock, hands the data to
     * `$fn(array &$data)` and writes it back atomically if `$fn` returns true.
     * A missing or empty file is an empty array.
     */
    static function updateJson(string $path, callable $fn, int $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE, int $mode = 0600): void
    {
        self::withLock($path, function () use ($path, $fn, $flags, $mode) {
            $raw = is_file($path) ? @file_get_contents($path) : "";
            if ($raw === false) {
                throw new \RuntimeException("Could not read " . basename($path));
            }
            $data = trim($raw) === "" ? [] : json_decode($raw, true);
            if (!is_array($data)) {
                throw new \RuntimeException(basename($path) . " is damaged, it is no JSON object");
            }
            if ($fn($data)) {
                self::writeAtomic($path, json_encode($data, $flags) . "\n", $mode);
            }
        });
    }
}