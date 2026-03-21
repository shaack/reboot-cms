<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

namespace Shaack\Reboot\Admin;

class PageHistoryHelper
{
    /**
     * Save a snapshot of a page to the history directory.
     */
    public static function savePageSnapshot(string $pageFsPath, string $pagesDir, string $historyDir, int $maxVersions): void
    {
        if (!is_file($pageFsPath) || filesize($pageFsPath) === 0) return;
        $relPath = str_replace($pagesDir, "", $pageFsPath);
        $relPath = preg_replace('/\.md$/', '', $relPath);
        $snapshotDir = $historyDir . $relPath;
        // Skip if content is identical to the latest snapshot
        if (is_dir($snapshotDir)) {
            $existing = glob($snapshotDir . "/*.md");
            if (!empty($existing)) {
                sort($existing);
                $latest = end($existing);
                if (file_get_contents($latest) === file_get_contents($pageFsPath)) {
                    return;
                }
            }
        } else {
            mkdir($snapshotDir, 0755, true);
        }
        $timestamp = date('Y-m-d_H-i-s');
        $snapshotPath = $snapshotDir . "/" . $timestamp . ".md";
        copy($pageFsPath, $snapshotPath);
        self::pruneHistory($snapshotDir, $maxVersions);
    }

    /**
     * Keep only the latest $max snapshots in a history directory.
     */
    private static function pruneHistory(string $snapshotDir, int $max): void
    {
        $files = glob($snapshotDir . "/*.md");
        if (count($files) <= $max) return;
        sort($files);
        $toDelete = array_slice($files, 0, count($files) - $max);
        foreach ($toDelete as $file) {
            unlink($file);
        }
    }

    /**
     * Get the list of snapshots for a page, newest first.
     */
    public static function getPageHistory(string $editPageName, string $pagesDir, string $historyDir): array
    {
        $relPath = preg_replace('/\.md$/', '', $editPageName);
        $snapshotDir = $historyDir . $relPath;
        if (!is_dir($snapshotDir)) return [];
        $files = glob($snapshotDir . "/*.md");
        rsort($files);
        $versions = [];
        foreach ($files as $file) {
            $name = basename($file, '.md');
            // Fix: first 10 chars are date with colons, fix back to hyphens
            $timestamp = substr($name, 0, 10) . ' ' . str_replace('-', ':', substr($name, 11));
            $versions[] = [
                'file' => $file,
                'filename' => basename($file),
                'timestamp' => $timestamp,
                'size' => filesize($file)
            ];
        }
        return $versions;
    }

    /**
     * Move history directory when a page is renamed or moved.
     */
    public static function movePageHistory(string $oldPageName, string $newPageName, string $historyDir): void
    {
        $oldDir = $historyDir . preg_replace('/\.md$/', '', $oldPageName);
        $newDir = $historyDir . preg_replace('/\.md$/', '', $newPageName);
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
