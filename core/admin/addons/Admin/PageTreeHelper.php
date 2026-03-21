<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

namespace Shaack\Reboot\Admin;

class PageTreeHelper
{
    /**
     * Build a tree structure from a flat file list.
     */
    public static function buildPageTree(array $pages, string $pagesDir): array
    {
        $tree = [];
        // Add .md files
        foreach ($pages as $page) {
            $pagePathInfo = pathinfo($page["name"]);
            if (!array_key_exists("extension", $pagePathInfo) || $pagePathInfo["extension"] !== "md") {
                continue;
            }
            $relPath = str_replace($pagesDir, "", $page["name"]);
            $parts = explode("/", trim($relPath, "/"));
            $node = &$tree;
            for ($i = 0; $i < count($parts) - 1; $i++) {
                if (!isset($node[$parts[$i]])) {
                    $node[$parts[$i]] = [];
                }
                $node = &$node[$parts[$i]];
            }
            $node[] = $relPath;
            unset($node);
        }
        // Add empty directories
        self::addEmptyDirs($pagesDir, $pagesDir, $tree);
        return $tree;
    }

    private static function addEmptyDirs(string $dir, string $pagesDir, array &$tree): void
    {
        $entries = scandir($dir);
        foreach ($entries as $entry) {
            if ($entry[0] === '.' || !is_dir($dir . "/" . $entry)) continue;
            $relPath = substr($dir . "/" . $entry, strlen($pagesDir) + 1);
            $parts = explode("/", $relPath);
            $node = &$tree;
            foreach ($parts as $part) {
                if (!isset($node[$part])) {
                    $node[$part] = [];
                }
                $node = &$node[$part];
            }
            unset($node);
            self::addEmptyDirs($dir . "/" . $entry, $pagesDir, $tree);
        }
    }

    /**
     * Render the page tree as nested HTML lists.
     */
    public static function renderTree(array $tree, ?string $editPageName = null, bool &$editable = false, string $prefix = ""): string
    {
        $html = "<ul class='page-tree list-unstyled'>";
        // Separate folders and files
        $folders = [];
        $files = [];
        foreach ($tree as $key => $value) {
            if (is_array($value)) {
                $folders[$key] = $value;
            } else {
                $files[] = $value;
            }
        }
        // Sort files: index first, then alphabetically
        usort($files, function ($a, $b) {
            $aIsIndex = basename($a) === 'index.md';
            $bIsIndex = basename($b) === 'index.md';
            if ($aIsIndex !== $bIsIndex) return $aIsIndex ? -1 : 1;
            return strcasecmp($a, $b);
        });
        // Render files first, then folders
        foreach ($files as $filePath) {
            $fileName = preg_replace('/\.md$/', '', basename($filePath));
            $active = $editPageName && $filePath === $editPageName;
            if ($active) {
                $editable = true;
            }
            $html .= "<li>";
            $html .= "<a class='page-tree-file" . ($active ? " active" : "") . "' href='pages?page=" . urlencode($filePath) . "'>" . htmlspecialchars($fileName) . "</a>";
            $html .= "</li>";
        }
        foreach ($folders as $folderName => $children) {
            $folderId = "folder-" . md5($prefix . $folderName);
            // Check if any child in this folder is active
            $folderContainsActive = false;
            if ($editPageName) {
                $folderPrefix = $prefix . $folderName . "/";
                $folderContainsActive = str_starts_with($editPageName, "/" . $folderPrefix) || str_starts_with($editPageName, $folderPrefix);
            }
            $expanded = $folderContainsActive;
            $folderPath = $prefix . $folderName;
            $html .= "<li class='page-tree-folder-item'>";
            $html .= "<div class='d-flex align-items-center'>";
            $html .= "<a class='page-tree-folder flex-grow-1' data-bs-toggle='collapse' href='#" . $folderId . "' role='button' aria-expanded='" . ($expanded ? "true" : "false") . "'>";
            $html .= "<span class='folder-icon'>" . ($expanded ? "&#9660;" : "&#9654;") . "</span> " . htmlspecialchars($folderName);
            $html .= "</a>";
            $html .= "<span class='folder-actions'>";
            $html .= "<a href='#' class='folder-action' title='Rename folder' onclick='renameFolder(\"" . htmlspecialchars($folderPath, ENT_QUOTES) . "\", \"" . htmlspecialchars($folderName, ENT_QUOTES) . "\"); return false;'>&#9998;</a>";
            $html .= "<a href='#' class='folder-action' title='Delete empty folder' onclick='deleteFolder(\"" . htmlspecialchars($folderPath, ENT_QUOTES) . "\", \"" . htmlspecialchars($folderName, ENT_QUOTES) . "\"); return false;'>&#10005;</a>";
            $html .= "</span>";
            $html .= "</div>";
            $html .= "<div class='collapse" . ($expanded ? " show" : "") . "' id='" . $folderId . "'>";
            $html .= self::renderTree($children, $editPageName, $editable, $prefix . $folderName . "/");
            $html .= "</div>";
            $html .= "</li>";
        }
        $html .= "</ul>";
        return $html;
    }

    /**
     * Collect folder paths for the move dropdown.
     */
    public static function collectFolders(string $dir, string $base = ""): array
    {
        $folders = [$base ?: "/"];
        $entries = scandir($dir);
        foreach ($entries as $entry) {
            if ($entry[0] === '.' || !is_dir($dir . "/" . $entry)) continue;
            $path = $base . "/" . $entry;
            $folders[] = $path;
            $folders = array_merge($folders, self::collectFolders($dir . "/" . $entry, $path));
        }
        return $folders;
    }
}
