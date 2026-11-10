<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

namespace Shaack\Reboot\AddOns\Admin;

use Shaack\Reboot\Request;
use Shaack\Reboot\Site;
use Shaack\Utils\FileSystemUtils;

class PageActionHandler
{
    private string $pagesDir;
    private string $historyDir;
    private int $historyMaxVersions;
    private ?Site $site;

    /**
     * @param ?Site $site its languages: renaming or moving a page takes its versions
     *     in the other languages along, `about.md` and `about.de.md`
     */
    public function __construct(string $pagesDir, string $historyDir, int $historyMaxVersions, ?Site $site = null)
    {
        $this->pagesDir = $pagesDir;
        $this->historyDir = $historyDir;
        $this->historyMaxVersions = $historyMaxVersions;
        $this->site = $site;
    }

    /**
     * Handle a page/folder action. Returns ['success' => string, 'editPageName' => ?string].
     * Throws on validation errors.
     */
    public function handle(string $action, string $targetName, Request $request): array
    {
        return match ($action) {
            'add_page' => $this->addPage($targetName, true),
            'add_markdown_page' => $this->addPage($targetName),
            'delete_page' => $this->deletePage($targetName),
            'rename_page' => $this->renamePage($targetName, $request),
            'move_page' => $this->movePage($targetName, $request),
            'add_folder' => $this->addFolder($targetName),
            'delete_folder' => $this->deleteFolder($targetName),
            'rename_folder' => $this->renameFolder($targetName, $request),
            'restore_page' => $this->restorePage($targetName, $request),
            'add_translation' => $this->addTranslation($targetName, $request),
            default => throw new \InvalidArgumentException("Unknown action: " . $action),
        };
    }

    /**
     * @param bool $structured a structured page, a YAML file with a title and blocks
     */
    private function addPage(string $targetName, bool $structured = false): array
    {
        $newName = trim($targetName);
        if (!preg_match('/^[\w\-\/]+$/', $newName)) {
            throw new \InvalidArgumentException("Invalid page name. Use letters, numbers, hyphens, underscores.");
        }
        $extension = $structured ? ".yml" : ".md";
        $newPath = $this->pagesDir . "/" . $newName . $extension;
        $newDir = dirname($newPath);
        if (!is_dir($newDir)) {
            mkdir($newDir, 0755, true);
        }
        // one address, one page: about.md and about.yml would both be /about
        foreach ([".md", ".yml", ".php"] as $other) {
            if (file_exists($this->pagesDir . "/" . $newName . $other)) {
                throw new \InvalidArgumentException("Page already exists.");
            }
        }
        file_put_contents($newPath, $structured ? "title: " . basename($newName) . "\nblocks: []\n" : "");
        return ['success' => "Page created", 'editPageName' => "/" . $newName . $extension];
    }

    private function deletePage(string $targetName): array
    {
        $delPath = $this->resolveAndValidateFile($targetName);
        unlink($delPath);
        return ['success' => "Page deleted", 'editPageName' => null];
    }

    private function renamePage(string $targetName, Request $request): array
    {
        $newName = trim($request->getParam("new_name") ?? "");
        if (!preg_match('/^[\w\-]+$/', $newName)) {
            throw new \InvalidArgumentException("Invalid name. Use letters, numbers, hyphens, underscores.");
        }
        $oldPath = $this->resolveAndValidateFile($targetName);
        return $this->moveVersions($oldPath, dirname($oldPath), $newName, "Page renamed", "A page with that name already exists.");
    }

    private function movePage(string $targetName, Request $request): array
    {
        $destination = trim($request->getParam("destination") ?? "");
        $destination = str_replace("..", "", $destination);
        $oldPath = $this->resolveAndValidateFile($targetName);
        $destDir = $this->pagesDir . ($destination ? "/" . $destination : "");
        if (!is_dir($destDir)) {
            throw new \InvalidArgumentException("Destination folder does not exist.");
        }
        return $this->moveVersions($oldPath, $destDir, $this->baseName(basename($oldPath)), "Page moved",
            "A page with that name already exists in the destination.");
    }

    /**
     * Moves a page and its versions in the other languages to `$destDir/$newName`,
     * `about.md` and `about.de.md` become `contact.md` and `contact.de.md`. Nothing
     * moves if one of the new names is taken.
     * @return array the result for the open page, the moved `$oldPath`
     */
    private function moveVersions(string $oldPath, string $destDir, string $newName, string $success, string $taken): array
    {
        $oldBase = $this->baseName(basename($oldPath));
        $moves = [];
        foreach ($this->versions($oldPath) as $file) {
            $newFile = $destDir . "/" . $newName . substr(basename($file), strlen($oldBase));
            if ($newFile !== $file && file_exists($newFile)) {
                throw new \InvalidArgumentException($taken);
            }
            $moves[$file] = $newFile;
        }
        foreach ($moves as $file => $newFile) {
            if ($newFile === $file) {
                continue;
            }
            rename($file, $newFile);
            PageHistoryHelper::movePageHistory(str_replace($this->pagesDir, "", $file), str_replace($this->pagesDir, "", $newFile), $this->historyDir);
        }
        return ['success' => $success, 'editPageName' => str_replace($this->pagesDir, "", $moves[$oldPath])];
    }

    /**
     * Creates the version of a Markdown page in another language, `about.de.md`
     * next to `about.md`, as a copy of the page to translate.
     */
    private function addTranslation(string $targetName, Request $request): array
    {
        $language = (string)($request->getParam("language") ?? "");
        if (!$this->site || !$this->site->isMultilingual() || !in_array($language, $this->site->getLanguages(), true)) {
            throw new \InvalidArgumentException("Unknown language.");
        }
        $sourcePath = $this->resolveAndValidateFile($targetName);
        if (!str_ends_with($sourcePath, ".md")) {
            throw new \InvalidArgumentException("Only a Markdown page has a file per language, a structured page holds all of them.");
        }
        $base = dirname($sourcePath) . "/" . $this->baseName(basename($sourcePath));
        $newPath = $language === $this->site->getDefaultLanguage() ? $base . ".md" : $base . "." . $language . ".md";
        if (file_exists($newPath) || file_exists($base . "." . $language . ".md")) {
            throw new \InvalidArgumentException("The page has a " . $language . " version already.");
        }
        copy($sourcePath, $newPath);
        return ['success' => "The " . $language . " version is a copy of the page, ready to translate", 'editPageName' => str_replace($this->pagesDir, "", $newPath)];
    }

    /**
     * A file name without language and extension, `about.de.md` is `about`.
     */
    private function baseName(string $fileName): string
    {
        $name = preg_replace('/\.(md|yml)$/', '', $fileName);
        $languages = $this->site ? $this->site->getLanguages() : [];
        if (preg_match('/^(.*)\.([^.]+)$/', $name, $matches) && in_array($matches[2], $languages, true)) {
            return $matches[1];
        }
        return $name;
    }

    /**
     * The page file and its versions in the other languages next to it:
     * `about.md` or `about.yml` and `about.de.md`, `about.en.md` ...
     * @return string[] existing files, `$path` among them
     */
    private function versions(string $path): array
    {
        $files = [$path];
        if (!$this->site || !$this->site->isMultilingual()) {
            return $files;
        }
        $base = dirname($path) . "/" . $this->baseName(basename($path));
        $candidates = [$base . ".md", $base . ".yml"];
        foreach ($this->site->getLanguages() as $language) {
            $candidates[] = $base . "." . $language . ".md";
        }
        foreach ($candidates as $file) {
            if ($file !== $path && is_file($file)) {
                $files[] = $file;
            }
        }
        return $files;
    }

    private function addFolder(string $targetName): array
    {
        $folderName = trim($targetName);
        if (!preg_match('/^[\w\-\/]+$/', $folderName)) {
            throw new \InvalidArgumentException("Invalid folder name.");
        }
        $newDir = $this->pagesDir . "/" . $folderName;
        if (is_dir($newDir)) {
            throw new \InvalidArgumentException("Folder already exists.");
        }
        mkdir($newDir, 0755, true);
        file_put_contents($newDir . "/index.yml", "title: " . basename($folderName) . "\nblocks: []\n");
        return ['success' => "Folder created with index.yml", 'editPageName' => "/" . $folderName . "/index.yml"];
    }

    private function deleteFolder(string $targetName): array
    {
        $delDir = $this->resolveAndValidateDir($targetName);
        if ($delDir === $this->pagesDir) {
            throw new \InvalidArgumentException("Cannot delete the pages root folder.");
        }
        if (count(scandir($delDir)) > 2) {
            throw new \InvalidArgumentException("Folder is not empty.");
        }
        rmdir($delDir);
        return ['success' => "Folder deleted", 'editPageName' => null];
    }

    private function renameFolder(string $targetName, Request $request): array
    {
        $newName = trim($request->getParam("new_name") ?? "");
        if (!preg_match('/^[\w\-]+$/', $newName)) {
            throw new \InvalidArgumentException("Invalid name.");
        }
        $oldDir = $this->resolveAndValidateDir($targetName);
        if ($oldDir === $this->pagesDir) {
            throw new \InvalidArgumentException("Cannot rename the pages root folder.");
        }
        $newDir = dirname($oldDir) . "/" . $newName;
        if (file_exists($newDir)) {
            throw new \InvalidArgumentException("A folder with that name already exists.");
        }
        rename($oldDir, $newDir);
        PageHistoryHelper::moveFolderHistory(
            $targetName,
            (dirname($targetName) === "." ? "" : dirname($targetName) . "/") . $newName,
            $this->historyDir
        );
        return ['success' => "Folder renamed", 'editPageName' => null, 'renamedFolder' => [
            'oldPrefix' => $targetName,
            'newName' => $newName,
        ]];
    }

    /**
     * Restores a version from the history. A version of the other kind of page,
     * the Markdown version of a converted page for example, turns the page back
     * into that kind: the file of the version takes the place of the current one.
     */
    private function restorePage(string $targetName, Request $request): array
    {
        $version = basename($request->getParam("version") ?? "");
        $pagePath = $this->resolveAndValidateFile($targetName);
        $snapshotFile = $this->historyDir . PageHistoryHelper::address($targetName) . "/" . $version;
        $resolvedSnapshot = realpath($snapshotFile);
        $extension = pathinfo($version, PATHINFO_EXTENSION);
        if (!$resolvedSnapshot || !FileSystemUtils::isInside($resolvedSnapshot, realpath($this->historyDir)) || !is_file($resolvedSnapshot)
            || !in_array($extension, PageHistoryHelper::EXTENSIONS, true)) {
            throw new \InvalidArgumentException("Invalid version.");
        }
        // the same kind of page is overwritten, the other kind replaces it
        $otherKind = $extension !== pathinfo($pagePath, PATHINFO_EXTENSION);
        $restoredName = PageHistoryHelper::address($targetName) . "." . $extension;
        $restoredPath = $otherKind ? dirname($pagePath) . "/" . basename($restoredName) : $pagePath;
        if ($otherKind && file_exists($restoredPath)) {
            throw new \InvalidArgumentException("There is already a page " . ltrim($restoredName, "/") . ".");
        }
        PageHistoryHelper::savePageSnapshot($pagePath, $this->pagesDir, $this->historyDir, $this->historyMaxVersions);
        copy($resolvedSnapshot, $restoredPath);
        if ($otherKind) {
            unlink($pagePath);
        }
        return ['success' => "Page restored to " . pathinfo($version, PATHINFO_FILENAME), 'editPageName' => $restoredName];
    }

    // --- Path validation helpers ---

    private function resolveAndValidateFile(string $targetName): string
    {
        $path = realpath($this->pagesDir . $targetName);
        if (!FileSystemUtils::isInside($path, $this->pagesDir) || !is_file($path)) {
            throw new \InvalidArgumentException("Invalid page.");
        }
        return $path;
    }

    private function resolveAndValidateDir(string $targetName): string
    {
        $path = realpath($this->pagesDir . "/" . $targetName);
        if (!FileSystemUtils::isInside($path, $this->pagesDir) || !is_dir($path)) {
            throw new \InvalidArgumentException("Invalid folder.");
        }
        return $path;
    }
}
