<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

namespace Shaack\Reboot\AddOns\Admin;

/**
 * The history of pages: snapshots in `local/history/pages/<path>/`, one folder
 * per address, `about.md` and `about.yml` share `about/`. A snapshot keeps the
 * extension of its page, `2026-11-07_18-53-15.md` or `.yml`, so the history of a
 * converted page still holds its Markdown versions.
 */
class PageHistoryHelper
{
    const EXTENSIONS = ["md", "yml"];

    /**
     * Save a snapshot of a page to the history directory.
     */
    public static function savePageSnapshot(string $pageFsPath, string $pagesDir, string $historyDir, int $maxVersions): void
    {
        if (!is_file($pageFsPath) || filesize($pageFsPath) === 0) return;
        $snapshotDir = $historyDir . self::address(str_replace($pagesDir, "", $pageFsPath));
        // Skip if content is identical to the latest snapshot
        if (is_dir($snapshotDir)) {
            $existing = self::snapshots($snapshotDir);
            if (!empty($existing)) {
                $latest = end($existing);
                if (file_get_contents($latest) === file_get_contents($pageFsPath)) {
                    return;
                }
            }
        } else {
            mkdir($snapshotDir, 0755, true);
        }
        $extension = pathinfo($pageFsPath, PATHINFO_EXTENSION) === "yml" ? "yml" : "md";
        $snapshotPath = $snapshotDir . "/" . date('Y-m-d_H-i-s') . "." . $extension;
        copy($pageFsPath, $snapshotPath);
        self::pruneHistory($snapshotDir, $maxVersions);
    }

    /**
     * The path of a page without its extension, the folder of its history.
     */
    public static function address(string $pageName): string
    {
        return preg_replace('/\.(md|yml)$/', '', $pageName);
    }

    /**
     * The snapshots in a history directory, oldest first.
     * @return string[]
     */
    private static function snapshots(string $snapshotDir): array
    {
        $files = [];
        foreach (self::EXTENSIONS as $extension) {
            $files = array_merge($files, glob($snapshotDir . "/*." . $extension) ?: []);
        }
        usort($files, fn($a, $b) => strcmp(basename($a), basename($b)));
        return $files;
    }

    /**
     * Keep only the latest $max snapshots in a history directory.
     */
    private static function pruneHistory(string $snapshotDir, int $max): void
    {
        $files = self::snapshots($snapshotDir);
        if (count($files) <= $max) return;
        foreach (array_slice($files, 0, count($files) - $max) as $file) {
            unlink($file);
        }
    }

    /**
     * Get the list of snapshots for a page, newest first. `type` is the kind of
     * page a snapshot was taken of, `md` or `yml`.
     */
    public static function getPageHistory(string $editPageName, string $pagesDir, string $historyDir): array
    {
        $snapshotDir = $historyDir . self::address($editPageName);
        if (!is_dir($snapshotDir)) return [];
        $versions = [];
        foreach (array_reverse(self::snapshots($snapshotDir)) as $file) {
            $name = pathinfo($file, PATHINFO_FILENAME);
            // the date keeps its hyphens, the time gets its colons back
            $timestamp = substr($name, 0, 10) . ' ' . str_replace('-', ':', substr($name, 11));
            $versions[] = [
                'file' => $file,
                'filename' => basename($file),
                'timestamp' => $timestamp,
                'size' => filesize($file),
                'type' => pathinfo($file, PATHINFO_EXTENSION),
            ];
        }
        return $versions;
    }

    /**
     * Move history directory when a page is renamed or moved.
     */
    public static function movePageHistory(string $oldPageName, string $newPageName, string $historyDir): void
    {
        $oldDir = $historyDir . self::address($oldPageName);
        $newDir = $historyDir . self::address($newPageName);
        if (is_dir($oldDir)) {
            $newParent = dirname($newDir);
            if (!is_dir($newParent)) {
                mkdir($newParent, 0755, true);
            }
            rename($oldDir, $newDir);
        }
    }

    /**
     * Move all history under a folder when the folder is renamed.
     */
    public static function moveFolderHistory(string $oldFolderPath, string $newFolderPath, string $historyDir): void
    {
        $oldDir = $historyDir . "/" . $oldFolderPath;
        $newDir = $historyDir . "/" . $newFolderPath;
        if (is_dir($oldDir)) {
            $newParent = dirname($newDir);
            if (!is_dir($newParent)) {
                mkdir($newParent, 0755, true);
            }
            rename($oldDir, $newDir);
        }
    }
}
