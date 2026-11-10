<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

namespace Shaack\Reboot\AddOns\Admin;

use Shaack\Reboot\Site;

class PageTreeHelper
{
    /**
     * Build a tree structure from a flat file list.
     */
    public static function buildPageTree(array $pages, string $pagesDir): array
    {
        $tree = [];
        // Add Markdown pages and structured pages (.yml)
        foreach ($pages as $page) {
            $pagePathInfo = pathinfo($page["name"]);
            if (!in_array($pagePathInfo["extension"] ?? "", ["md", "yml"], true)) {
                continue;
            }
            // Hidden files such as the AppleDouble ._index.md a macOS tar leaves behind are not pages
            if (str_starts_with($pagePathInfo["basename"], ".")) {
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
     * @param ?Site $site a multilingual site: the versions of a Markdown page in its languages,
     *     `about.md` and `about.de.md`, are one entry with a link per language
     */
    public static function renderTree(array $tree, ?string $editPageName = null, bool &$editable = false, string $prefix = "", ?Site $site = null): string
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
            $aIsIndex = in_array(basename($a), ['index.md', 'index.yml'], true);
            $bIsIndex = in_array(basename($b), ['index.md', 'index.yml'], true);
            if ($aIsIndex !== $bIsIndex) return $aIsIndex ? -1 : 1;
            return strcasecmp($a, $b);
        });
        // the versions of a page in its languages, by the name without language
        $groups = [];
        foreach ($files as $filePath) {
            $groups[$site ? LanguageSwitch::baseName($site, $filePath) : $filePath][] = $filePath;
        }
        // Render files first, then folders
        foreach ($groups as $versions) {
            $filePath = self::mainVersion($versions, $site);
            $fileName = basename($site ? LanguageSwitch::baseName($site, $filePath) : preg_replace('/\.(md|yml)$/', '', $filePath));
            $structured = str_ends_with($filePath, ".yml");
            $active = $editPageName && $filePath === $editPageName;
            if ($editPageName && in_array($editPageName, $versions, true)) {
                $editable = true;
            }
            // an index page is the page of its folder, the one of the root is the homepage
            $isIndex = $fileName === "index";
            $title = $isIndex ? ($prefix === "" ? "Homepage /" : "Folder page /" . rtrim($prefix, "/")) : ltrim($filePath, "/");
            $html .= "<li>";
            $title .= $structured ? "" : " (Markdown page)";
            $html .= "<a class='page-tree-file" . ($active ? " active" : "") . ($isIndex ? " page-tree-index" : "") . ($structured ? " page-tree-structured" : "") . "' href='pages?page=" . urlencode($filePath) . "'"
                . " title='" . htmlspecialchars($title, ENT_QUOTES) . "' data-path='" . htmlspecialchars(ltrim($filePath, "/"), ENT_QUOTES) . "'>"
                . AdminHelper::icon($isIndex ? "house" : ($structured ? "file" : "markdown")) . "<span>" . htmlspecialchars($fileName) . "</span></a>";
            $html .= self::languageLinks($versions, $editPageName, $site);
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
            $html .= "<a class='page-tree-folder flex-grow-1' data-bs-toggle='collapse' href='#" . $folderId . "' role='button' aria-expanded='" . ($expanded ? "true" : "false") . "'"
                . " data-path='" . htmlspecialchars($folderPath, ENT_QUOTES) . "'>";
            $html .= AdminHelper::icon("folder") . "<span>" . htmlspecialchars($folderName) . "</span>" . AdminHelper::icon("chevron", "folder-icon");
            $html .= "</a>";
            $html .= "</div>";
            $html .= "<div class='collapse" . ($expanded ? " show" : "") . "' id='" . $folderId . "'>";
            $html .= self::renderTree($children, $editPageName, $editable, $prefix . $folderName . "/", $site);
            $html .= "</div>";
            $html .= "</li>";
        }
        $html .= "</ul>";
        return $html;
    }

    /**
     * The file the entry of a page opens: the page of all languages or the one
     * of the default language, else the first version.
     */
    private static function mainVersion(array $versions, ?Site $site): string
    {
        if ($site) {
            foreach ($versions as $file) {
                if ($site->pageFileAddress($file)[1] === $site->getDefaultLanguage()) {
                    return $file;
                }
            }
        }
        return $versions[0];
    }

    /**
     * A link per language of a Markdown page, `EN DE`, if it has a file of its own
     * for one: a structured page alone holds all languages in one file.
     */
    private static function languageLinks(array $versions, ?string $editPageName, ?Site $site): string
    {
        if (!$site) {
            return "";
        }
        $byLanguage = [];
        foreach ($versions as $file) {
            if (str_ends_with($file, ".md")) {
                $byLanguage[$site->pageFileAddress($file)[1]] = $file;
            }
        }
        if (!$byLanguage || (count($versions) === 1 && isset($byLanguage[$site->getDefaultLanguage()]))) {
            return "";
        }
        $html = "<span class='page-tree-languages'>";
        foreach ($site->getLanguages() as $language) {
            if (!isset($byLanguage[$language])) {
                continue;
            }
            $file = $byLanguage[$language];
            $html .= "<a class='page-tree-language" . ($file === $editPageName ? " active" : "") . "' data-lang-link='" . htmlspecialchars($language, ENT_QUOTES) . "'"
                . " href='pages?page=" . urlencode($file) . "'"
                . " title='" . htmlspecialchars(ltrim($file, "/"), ENT_QUOTES) . "'>" . htmlspecialchars(strtoupper($language)) . "</a>";
        }
        return $html . "</span>";
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
            // the recursive call adds $path itself via its $base — don't add it again
            $folders = array_merge($folders, self::collectFolders($dir . "/" . $entry, $path));
        }
        return $folders;
    }
}
