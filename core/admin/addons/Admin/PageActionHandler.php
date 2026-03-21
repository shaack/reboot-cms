<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

namespace Shaack\Reboot\Admin;

use Shaack\Reboot\Request;

class PageActionHandler
{
    private string $pagesDir;
    private string $historyDir;
    private int $historyMaxVersions;

    public function __construct(string $pagesDir, string $historyDir, int $historyMaxVersions)
    {
        $this->pagesDir = $pagesDir;
        $this->historyDir = $historyDir;
        $this->historyMaxVersions = $historyMaxVersions;
    }

    /**
     * Handle a page/folder action. Returns ['success' => string, 'editPageName' => ?string].
     * Throws on validation errors.
     */
    public function handle(string $action, string $targetName, Request $request): array
    {
        return match ($action) {
            'add_page' => $this->addPage($targetName),
            'delete_page' => $this->deletePage($targetName),
            'rename_page' => $this->renamePage($targetName, $request),
            'move_page' => $this->movePage($targetName, $request),
            'add_folder' => $this->addFolder($targetName),
            'delete_folder' => $this->deleteFolder($targetName),
            'rename_folder' => $this->renameFolder($targetName, $request),
            'restore_page' => $this->restorePage($targetName, $request),
            default => throw new \InvalidArgumentException("Unknown action: " . $action),
        };
    }

    private function addPage(string $targetName): array
    {
        $newName = trim($targetName);
        if (!preg_match('/^[\w\-\/]+$/', $newName)) {
            throw new \InvalidArgumentException("Invalid page name. Use letters, numbers, hyphens, underscores.");
        }
        $newPath = $this->pagesDir . "/" . $newName . ".md";
        $newDir = dirname($newPath);
        if (!is_dir($newDir)) {
            mkdir($newDir, 0755, true);
        }
        if (file_exists($newPath)) {
            throw new \InvalidArgumentException("Page already exists.");
        }
        file_put_contents($newPath, "");
        return ['success' => "Page created", 'editPageName' => "/" . $newName . ".md"];
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
        $newPath = dirname($oldPath) . "/" . $newName . ".md";
        if (file_exists($newPath)) {
            throw new \InvalidArgumentException("A page with that name already exists.");
        }
        rename($oldPath, $newPath);
        $relNew = str_replace($this->pagesDir, "", $newPath);
        PageHistoryHelper::movePageHistory($targetName, $relNew, $this->historyDir);
        return ['success' => "Page renamed", 'editPageName' => $relNew];
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
        $newPath = $destDir . "/" . basename($oldPath);
        if (file_exists($newPath)) {
            throw new \InvalidArgumentException("A page with that name already exists in the destination.");
        }
        rename($oldPath, $newPath);
        $newRelPath = str_replace($this->pagesDir, "", $newPath);
        PageHistoryHelper::movePageHistory($targetName, $newRelPath, $this->historyDir);
        return ['success' => "Page moved", 'editPageName' => $newRelPath];
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
        file_put_contents($newDir . "/index.md", "");
        return ['success' => "Folder created with index.md", 'editPageName' => "/" . $folderName . "/index.md"];
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

    private function restorePage(string $targetName, Request $request): array
    {
        $version = basename($request->getParam("version") ?? "");
        $pagePath = $this->resolveAndValidateFile($targetName);
        $relPath = preg_replace('/\.md$/', '', $targetName);
        $snapshotFile = $this->historyDir . $relPath . "/" . $version;
        $resolvedSnapshot = realpath($snapshotFile);
        if (!$resolvedSnapshot || strncmp($resolvedSnapshot, realpath($this->historyDir), strlen(realpath($this->historyDir))) !== 0 || !is_file($resolvedSnapshot)) {
            throw new \InvalidArgumentException("Invalid version.");
        }
        PageHistoryHelper::savePageSnapshot($pagePath, $this->pagesDir, $this->historyDir, $this->historyMaxVersions);
        copy($resolvedSnapshot, $pagePath);
        return ['success' => "Page restored to " . basename($version, '.md'), 'editPageName' => $targetName];
    }

    // --- Path validation helpers ---

    private function resolveAndValidateFile(string $targetName): string
    {
        $path = realpath($this->pagesDir . $targetName);
        if (!$path || strncmp($path, $this->pagesDir, strlen($this->pagesDir)) !== 0 || !is_file($path)) {
            throw new \InvalidArgumentException("Invalid page.");
        }
        return $path;
    }

    private function resolveAndValidateDir(string $targetName): string
    {
        $path = realpath($this->pagesDir . "/" . $targetName);
        if (!$path || strncmp($path, $this->pagesDir, strlen($this->pagesDir)) !== 0 || !is_dir($path)) {
            throw new \InvalidArgumentException("Invalid folder.");
        }
        return $path;
    }
}
