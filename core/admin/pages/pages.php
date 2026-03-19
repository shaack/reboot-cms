<?php
/** @var \Shaack\Reboot\Reboot $reboot */
/** @var \Shaack\Reboot\Site $site */
/** @var \Shaack\Reboot\Request $request */
/** @var Shaack\Reboot\Admin $admin */
$admin = $site->getAddOn("Admin");

use Shaack\Logger;
use Shaack\Utils\FileSystemUtils;
use Shaack\Reboot\CsrfProtection;
use Shaack\Reboot\Page;
use Shaack\Reboot\Block;

$defaultSite = $admin->getDefaultSite();
$pagesDir = realpath($defaultSite->getFsPath() . "/pages");
$localConfig = $admin->getLocalConfig();
$historyMaxVersions = $localConfig['history']['maxVersions'] ?? 50;
$historyDir = $reboot->getBaseFsPath() . "/local/history/pages";

/**
 * Save a snapshot of a page to the history directory.
 */
function savePageSnapshot(string $pageFsPath, string $pagesDir, string $historyDir, int $maxVersions): void {
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
    pruneHistory($snapshotDir, $maxVersions);
}

/**
 * Keep only the latest $max snapshots in a history directory.
 */
function pruneHistory(string $snapshotDir, int $max): void {
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
function getPageHistory(string $editPageName, string $pagesDir, string $historyDir): array {
    $relPath = preg_replace('/\.md$/', '', $editPageName);
    $snapshotDir = $historyDir . $relPath;
    if (!is_dir($snapshotDir)) return [];
    $files = glob($snapshotDir . "/*.md");
    rsort($files);
    $versions = [];
    foreach ($files as $file) {
        $name = basename($file, '.md');
        $timestamp = str_replace('_', ' ', str_replace('-', ':', $name));
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
function movePageHistory(string $oldPageName, string $newPageName, string $historyDir): void {
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
function moveFolderHistory(string $oldFolderPath, string $newFolderPath, string $historyDir): void {
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
$editPageName = $request->getParam("page");
$editable = false;
$pages = FileSystemUtils::getFileList($pagesDir, true);
usort($pages, function($a, $b) {
    return strcmp($a['name'], $b['name']);
});
// JSON API for listing pages (used by InsertPageLink editor tool)
if ($request->getParam("list")) {
    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/json');
    $pageList = [];
    foreach ($pages as $page) {
        $pagePathInfo = pathinfo($page["name"]);
        if (!array_key_exists("extension", $pagePathInfo) || $pagePathInfo["extension"] !== "md") {
            continue;
        }
        $relPath = str_replace($pagesDir, "", $page["name"]);
        // Convert to web path: strip .md, strip /index
        $webPath = preg_replace('/\.md$/', '', $relPath);
        $webPath = preg_replace('/\/index$/', '/', $webPath);
        if ($webPath === '/index') $webPath = '/';
        $pageList[] = [
            'filePath' => $relPath,
            'webPath' => $webPath,
            'name' => basename($relPath, '.md')
        ];
    }
    echo json_encode($pageList);
    exit;
}

// JSON API for page history
if ($request->getParam("history")) {
    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/json');
    $historyPage = $request->getParam("page") ?? "";
    $historyPage = str_replace("..", "", $historyPage);
    $versions = getPageHistory($historyPage, $pagesDir, $historyDir);
    $result = array_map(function($v) {
        return [
            'filename' => $v['filename'],
            'timestamp' => $v['timestamp'],
            'size' => $v['size'],
            'content' => file_get_contents($v['file'])
        ];
    }, $versions);
    echo json_encode($result);
    exit;
}

// Live preview: render page content without saving
if ($request->getParam("preview") && $_SERVER['REQUEST_METHOD'] === 'POST') {
    CsrfProtection::validate($request);
    while (ob_get_level()) {
        ob_end_clean();
    }
    $previewPageName = $request->getParam("page") ?? "";
    $previewPageName = str_replace("..", "", $previewPageName);
    $previewContent = $request->getParam("content") ?? "";
    $previewPath = $pagesDir . $previewPageName;
    $resolvedPreview = realpath(dirname($previewPath));
    if ($resolvedPreview && strncmp($resolvedPreview, $pagesDir, strlen($pagesDir)) === 0 && is_file($previewPath)) {
        // Temporarily write content, render, restore
        $originalContent = file_get_contents($previewPath);
        file_put_contents($previewPath, $previewContent, LOCK_EX);
        // Override security headers for the preview iframe
        header('Content-Type: text/html; charset=utf-8');
        header('X-Frame-Options: SAMEORIGIN');
        header('Content-Security-Policy: default-src * \'unsafe-inline\' \'unsafe-eval\'; img-src * data:;');
        $previewSite = new \Shaack\Reboot\Site($reboot, "/site", "");
        $previewRequest = new \Shaack\Reboot\Request($previewSite, $reboot->getBaseWebPath(), preg_replace('/\/index$/', '/', preg_replace('/\.md$/', '', $previewPageName)), []);
        $previewPageObj = new Page($reboot, $previewSite);
        echo \Shaack\Reboot\renderPage($previewSite, $previewPageObj, $previewRequest);
        // Restore original content
        file_put_contents($previewPath, $originalContent, LOCK_EX);
    } else {
        http_response_code(404);
        echo 'Page not found';
    }
    exit;
}

// Handle page/folder management actions
$pageAction = $request->getParam("action");
$pageActionError = null;
$pageActionSuccess = null;
if ($pageAction) {
    try {
        CsrfProtection::validate($request);
        $targetName = $request->getParam("name") ?? "";
        $targetName = str_replace("..", "", $targetName);

        if ($pageAction === "add_page") {
            $newName = trim($targetName);
            if (!preg_match('/^[\w\-\/]+$/', $newName)) {
                throw new \InvalidArgumentException("Invalid page name. Use letters, numbers, hyphens, underscores.");
            }
            $newPath = $pagesDir . "/" . $newName . ".md";
            $newDir = dirname($newPath);
            if (!is_dir($newDir)) {
                mkdir($newDir, 0755, true);
            }
            if (file_exists($newPath)) {
                throw new \InvalidArgumentException("Page already exists.");
            }
            file_put_contents($newPath, "");
            $editPageName = "/" . $newName . ".md";
            $pageActionSuccess = "Page created";
            // Refresh file list
            $pages = FileSystemUtils::getFileList($pagesDir, true);
            usort($pages, function($a, $b) { return strcmp($a['name'], $b['name']); });

        } elseif ($pageAction === "delete_page") {
            $delPath = realpath($pagesDir . $targetName);
            if (!$delPath || strncmp($delPath, $pagesDir, strlen($pagesDir)) !== 0 || !is_file($delPath)) {
                throw new \InvalidArgumentException("Invalid page.");
            }
            unlink($delPath);
            $editPageName = null;
            $pageActionSuccess = "Page deleted";
            $pages = FileSystemUtils::getFileList($pagesDir, true);
            usort($pages, function($a, $b) { return strcmp($a['name'], $b['name']); });

        } elseif ($pageAction === "rename_page") {
            $newName = trim($request->getParam("new_name") ?? "");
            if (!preg_match('/^[\w\-]+$/', $newName)) {
                throw new \InvalidArgumentException("Invalid name. Use letters, numbers, hyphens, underscores.");
            }
            $oldPath = realpath($pagesDir . $targetName);
            if (!$oldPath || strncmp($oldPath, $pagesDir, strlen($pagesDir)) !== 0 || !is_file($oldPath)) {
                throw new \InvalidArgumentException("Invalid page.");
            }
            $newPath = dirname($oldPath) . "/" . $newName . ".md";
            if (file_exists($newPath)) {
                throw new \InvalidArgumentException("A page with that name already exists.");
            }
            rename($oldPath, $newPath);
            $relNew = str_replace($pagesDir, "", $newPath);
            movePageHistory($targetName, $relNew, $historyDir);
            $editPageName = $relNew;
            $pageActionSuccess = "Page renamed";
            $pages = FileSystemUtils::getFileList($pagesDir, true);
            usort($pages, function($a, $b) { return strcmp($a['name'], $b['name']); });

        } elseif ($pageAction === "move_page") {
            $destination = trim($request->getParam("destination") ?? "");
            $destination = str_replace("..", "", $destination);
            $oldPath = realpath($pagesDir . $targetName);
            if (!$oldPath || strncmp($oldPath, $pagesDir, strlen($pagesDir)) !== 0 || !is_file($oldPath)) {
                throw new \InvalidArgumentException("Invalid page.");
            }
            $destDir = $pagesDir . ($destination ? "/" . $destination : "");
            if (!is_dir($destDir)) {
                throw new \InvalidArgumentException("Destination folder does not exist.");
            }
            $newPath = $destDir . "/" . basename($oldPath);
            if (file_exists($newPath)) {
                throw new \InvalidArgumentException("A page with that name already exists in the destination.");
            }
            rename($oldPath, $newPath);
            $newRelPath = str_replace($pagesDir, "", $newPath);
            movePageHistory($targetName, $newRelPath, $historyDir);
            $editPageName = $newRelPath;
            $pageActionSuccess = "Page moved";
            $pages = FileSystemUtils::getFileList($pagesDir, true);
            usort($pages, function($a, $b) { return strcmp($a['name'], $b['name']); });

        } elseif ($pageAction === "add_folder") {
            $folderName = trim($targetName);
            if (!preg_match('/^[\w\-\/]+$/', $folderName)) {
                throw new \InvalidArgumentException("Invalid folder name.");
            }
            $newDir = $pagesDir . "/" . $folderName;
            if (is_dir($newDir)) {
                throw new \InvalidArgumentException("Folder already exists.");
            }
            mkdir($newDir, 0755, true);
            file_put_contents($newDir . "/index.md", "");
            $editPageName = "/" . $folderName . "/index.md";
            $pageActionSuccess = "Folder created with index.md";
            $pages = FileSystemUtils::getFileList($pagesDir, true);
            usort($pages, function($a, $b) { return strcmp($a['name'], $b['name']); });

        } elseif ($pageAction === "delete_folder") {
            $delDir = realpath($pagesDir . "/" . $targetName);
            if (!$delDir || strncmp($delDir, $pagesDir, strlen($pagesDir)) !== 0 || !is_dir($delDir)) {
                throw new \InvalidArgumentException("Invalid folder.");
            }
            if ($delDir === $pagesDir) {
                throw new \InvalidArgumentException("Cannot delete the pages root folder.");
            }
            if (count(scandir($delDir)) > 2) {
                throw new \InvalidArgumentException("Folder is not empty.");
            }
            rmdir($delDir);
            $pageActionSuccess = "Folder deleted";
            $pages = FileSystemUtils::getFileList($pagesDir, true);
            usort($pages, function($a, $b) { return strcmp($a['name'], $b['name']); });

        } elseif ($pageAction === "rename_folder") {
            $newName = trim($request->getParam("new_name") ?? "");
            if (!preg_match('/^[\w\-]+$/', $newName)) {
                throw new \InvalidArgumentException("Invalid name.");
            }
            $oldDir = realpath($pagesDir . "/" . $targetName);
            if (!$oldDir || strncmp($oldDir, $pagesDir, strlen($pagesDir)) !== 0 || !is_dir($oldDir)) {
                throw new \InvalidArgumentException("Invalid folder.");
            }
            $newDir = dirname($oldDir) . "/" . $newName;
            if (file_exists($newDir)) {
                throw new \InvalidArgumentException("A folder with that name already exists.");
            }
            rename($oldDir, $newDir);
            moveFolderHistory($targetName, (dirname($targetName) === "." ? "" : dirname($targetName) . "/") . $newName, $historyDir);
            $pageActionSuccess = "Folder renamed";
            // Update editPageName if it was inside the renamed folder
            if ($editPageName) {
                $oldPrefix = "/" . $targetName . "/";
                $newPrefix = "/" . dirname($targetName) . "/" . $newName . "/";
                if (dirname($targetName) === ".") {
                    $oldPrefix = "/" . $targetName . "/";
                    $newPrefix = "/" . $newName . "/";
                }
                if (str_starts_with($editPageName, $oldPrefix)) {
                    $editPageName = $newPrefix . substr($editPageName, strlen($oldPrefix));
                }
            }
            $pages = FileSystemUtils::getFileList($pagesDir, true);
            usort($pages, function($a, $b) { return strcmp($a['name'], $b['name']); });
        } elseif ($pageAction === "restore_page") {
            $version = $request->getParam("version") ?? "";
            $version = basename($version); // sanitize
            $pagePath = realpath($pagesDir . $targetName);
            if (!$pagePath || strncmp($pagePath, $pagesDir, strlen($pagesDir)) !== 0 || !is_file($pagePath)) {
                throw new \InvalidArgumentException("Invalid page.");
            }
            $relPath = preg_replace('/\.md$/', '', $targetName);
            $snapshotFile = $historyDir . $relPath . "/" . $version;
            $resolvedSnapshot = realpath($snapshotFile);
            if (!$resolvedSnapshot || strncmp($resolvedSnapshot, realpath($historyDir), strlen(realpath($historyDir))) !== 0 || !is_file($resolvedSnapshot)) {
                throw new \InvalidArgumentException("Invalid version.");
            }
            // Save current version as snapshot before restoring
            savePageSnapshot($pagePath, $pagesDir, $historyDir, $historyMaxVersions);
            copy($resolvedSnapshot, $pagePath);
            $editPageName = $targetName;
            $pageActionSuccess = "Page restored to " . basename($version, '.md');
        }
    } catch (\Exception $e) {
        $pageActionError = $e->getMessage();
    }
}

if (!$editPageName && file_exists($pagesDir . "/index.md")) {
    $editPageName = "/index.md";
}
if($editPageName) {
    Logger::debug("Editing page " . $editPageName);
}

// Build tree structure from flat file list
function buildPageTree(array $pages, string $pagesDir): array {
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
    addEmptyDirs($pagesDir, $pagesDir, $tree);
    return $tree;
}

function addEmptyDirs(string $dir, string $pagesDir, array &$tree): void {
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
        addEmptyDirs($dir . "/" . $entry, $pagesDir, $tree);
    }
}

function renderTree(array $tree, ?string $editPageName = null, bool &$editable = false, string $prefix = ""): string {
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
    usort($files, function($a, $b) {
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
        $html .= renderTree($children, $editPageName, $editable, $prefix . $folderName . "/");
        $html .= "</div>";
        $html .= "</li>";
    }
    $html .= "</ul>";
    return $html;
}

// Collect folder paths for the move dropdown
function collectFolders(string $dir, string $base = ""): array {
    $folders = [$base ?: "/"];
    $entries = scandir($dir);
    foreach ($entries as $entry) {
        if ($entry[0] === '.' || !is_dir($dir . "/" . $entry)) continue;
        $path = $base . "/" . $entry;
        $folders[] = $path;
        $folders = array_merge($folders, collectFolders($dir . "/" . $entry, $path));
    }
    return $folders;
}
$allFolders = collectFolders($pagesDir);

$pageTree = buildPageTree($pages, $pagesDir);
?>
<div class="container-fluid">
    <?php if ($pageActionError) { ?>
        <script>statusMessage("<?= htmlspecialchars($pageActionError, ENT_QUOTES) ?>", "text-bg-danger")</script>
    <?php } ?>
    <?php if ($pageActionSuccess) { ?>
        <script>statusMessage("<?= htmlspecialchars($pageActionSuccess, ENT_QUOTES) ?>")</script>
    <?php } ?>
    <div class="row gx-2">
        <div class="col-md-3 col-xl-2 col-sidebar order-md-0 order-1 mt-4 mt-md-0">
            <nav class="nav nav-compact flex-column">
                <?php
                echo renderTree($pageTree, $editPageName, $editable);
                if (!$editable && $editPageName) {
                    Logger::error("" . $editPageName . " is not editable.");
                    $editPageName = null;
                }
                ?>
            </nav>
            <div class="mt-3 d-flex flex-column gap-1" style="font-size: 0.85rem;">
                <div class="d-flex gap-2 justify-content-start border-1 border-top">
                    <button type="button" class="btn btn-sm opacity-75 btn-link text-body-secondary text-decoration-none p-0" data-bs-toggle="collapse" data-bs-target="#add-page-form">+ Page</button>
                    <button type="button" class="btn btn-sm opacity-75 btn-link text-body-secondary text-decoration-none p-0" data-bs-toggle="collapse" data-bs-target="#add-folder-form">+ Folder</button>
                </div>
                <div class="collapse" id="add-page-form">
                    <form method="post" action="pages" class="d-flex gap-1 mt-1">
                        <input type="hidden" name="csrf_token" value="<?= CsrfProtection::getToken() ?>">
                        <input type="hidden" name="action" value="add_page">
                        <input type="text" name="name" class="form-control form-control-sm" placeholder="path/name" required pattern="[\w\-\/]+">
                        <button class="btn btn-sm btn-primary text-nowrap">Add</button>
                    </form>
                </div>
                <div class="collapse" id="add-folder-form">
                    <form method="post" action="pages" class="d-flex gap-1 mt-1">
                        <input type="hidden" name="csrf_token" value="<?= CsrfProtection::getToken() ?>">
                        <input type="hidden" name="action" value="add_folder">
                        <input type="text" name="name" class="form-control form-control-sm" placeholder="path/folder" required pattern="[\w\-\/]+">
                        <button class="btn btn-sm btn-primary text-nowrap">Add</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-md-9 col-xl-10 order-md-1 order-0" id="editor-column">
            <?php if ($editPageName) {
                $fullPath = $pagesDir . $editPageName;
                // Validate the resolved path stays within the pages directory
                $resolvedPath = realpath(dirname($fullPath));
                if ($resolvedPath === false || strncmp($resolvedPath, $pagesDir, strlen($pagesDir)) !== 0) {
                    Logger::error("Path traversal attempt blocked: " . $editPageName);
                    $editPageName = null;
                } else {
                    $edited = $request->getParam("edited");
                    $validationErrors = [];
                    $pageChanged = false;
                    if ($edited !== null) {
                        CsrfProtection::validate($request);
                        $pageChanged = !file_exists($fullPath) || file_get_contents($fullPath) !== $edited;
                        if ($pageChanged) {
                            // Save snapshot before overwriting
                            savePageSnapshot($fullPath, $pagesDir, $historyDir, $historyMaxVersions);
                            file_put_contents($fullPath, $edited);
                        }
                        // Validate by rendering the page and collecting schema errors
                        Block::resetAllValidationErrors();
                        $validationPage = new Page($reboot, $defaultSite);
                        $pagePath = preg_replace('/\.md$/', '', $editPageName);
                        $pagePath = preg_replace('/\/index$/', '/', $pagePath);
                        ob_start();
                        $validationPage->render($pagePath);
                        ob_end_clean();
                        $validationErrors = Block::getAllValidationErrors();
                        $blockExamples = Block::getAllExamples();
                    }

                    // Collect block examples for the insert-block tool
                    $blocksDir = $defaultSite->getFsPath() . '/blocks';
                    $blockExamplesForInsert = [];
                    if (is_dir($blocksDir)) {
                        Block::resetAllValidationErrors();
                        foreach (glob($blocksDir . '/*.php') as $blockFile) {
                            $blockName = basename($blockFile, '.php');
                            $block = new Block($defaultSite, $blockName, '');
                            ob_start();
                            $block->render(null);
                            ob_end_clean();
                            $block->collectExample();
                        }
                        $blockExamplesForInsert = Block::getAllExamples();
                        Block::resetAllValidationErrors();
                    }
                ?>
                <script>document.title = <?= json_encode(basename($editPageName, '.md') . ' – Reboot CMS Admin') ?>;</script>
                <!--suppress HtmlUnknownTarget -->
                <form method="post" action="pages?page=<?= urlencode($editPageName) ?>">
                    <input type="hidden" name="csrf_token" value="<?= CsrfProtection::getToken() ?>">
                    <!--suppress HtmlFormInputWithoutLabel -->
                    <textarea name="edited" class="form-control cm-md-editor markdown editor-font"><?= htmlspecialchars(file_get_contents($fullPath)) ?></textarea>
                    <button type="button" class="btn btn-sm btn-primary px-3" onclick="savePageAsync()">Save</button>
                    <?php
                    $viewPath = preg_replace('/\.md$/', '', $editPageName);
                    $viewPath = preg_replace('/\/index$/', '/', $viewPath);
                    $viewUrl = $reboot->getBaseWebPath() . $viewPath;
                    ?>
                    <button type="button" class="btn btn-sm btn-outline-secondary ms-2" onclick="togglePreview()" id="preview-toggle">Preview</button>
                    <a href="<?= htmlspecialchars($viewUrl) ?>" target="_blank" class="btn btn-sm btn-outline-secondary ms-2">View Page</a>
                    <?php $currentBaseName = basename($editPageName, '.md'); ?>
                    <div class="dropdown d-inline-block ms-2">
                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">&#8230;</button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="#" onclick="toggleHistory(); return false;">History</a></li>
                            <li><a class="dropdown-item" href="#" onclick="renamePage(); return false;">Rename Page</a></li>
                            <li><a class="dropdown-item" href="#" onclick="movePage(); return false;">Move Page</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="#" onclick="deletePage(); return false;">Delete Page</a></li>
                        </ul>
                    </div>
                </form>
                <div class="modal fade" id="page-history-modal" tabindex="-1" aria-labelledby="page-history-label" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="page-history-label">History</h5>
                                <span id="page-history-count" class="text-body-secondary ms-2"></span>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body p-0" id="page-history-body">
                                <div class="p-4 text-center text-body-secondary">Loading…</div>
                            </div>
                        </div>
                    </div>
                </div>
                <script id="block-examples" type="application/json"><?= json_encode($blockExamplesForInsert) ?></script>
                <?php if ($edited !== null) { ?>
                <script id="save-result" type="application/json"><?= json_encode([
                    'changed' => $pageChanged,
                    'validationErrors' => $validationErrors,
                    'examples' => $blockExamples ?? []
                ]) ?></script>
                <?php } ?>
                <?php } ?>
            <?php } ?>
        </div>
        <div class="col-4 order-md-2 d-none" id="preview-column" style="position:sticky;top:56px;height:calc(100vh - 120px);">
            <iframe id="preview-iframe" name="preview-iframe" style="width:100%;height:100%;border:1px solid rgba(128,128,128,0.3);border-radius:4px;background:#fff;"></iframe>
        </div>
    </div>
</div>
<script>
function savePageAsync() {
    var form = document.querySelector('form[action^="pages?page="]');
    if (!form) return;
    var formData = new FormData(form);
    fetch(form.action, {
        method: 'POST',
        body: formData
    }).then(function(response) {
        if (response.ok) {
            return response.text().then(function(html) {
                var parser = new DOMParser();
                var doc = parser.parseFromString(html, 'text/html');
                var resultEl = doc.getElementById('save-result');
                if (resultEl) {
                    var data = JSON.parse(resultEl.textContent);
                    if (!data.changed) {
                        statusMessage("No changes", "text-bg-secondary");
                        return;
                    }
                    pageUnsaved = false;
                    savedContent = editorTextarea ? editorTextarea.value : '';
                    if (data.validationErrors && data.validationErrors.length > 0) {
                        var list = data.validationErrors.map(function(e) { return "<li>" + e + "</li>"; }).join("");
                        var msg = "Page saved with " + data.validationErrors.length + " schema warning(s):<ul class='mb-0 mt-1'>" + list + "</ul>";
                        var failingBlocks = {};
                        data.validationErrors.forEach(function(e) {
                            var m = e.match(/^Block '([^']+)':/);
                            if (m && data.examples[m[1]]) failingBlocks[m[1]] = true;
                        });
                        for (var blockName in failingBlocks) {
                            msg += "<hr class='my-2'><strong>Expected markdown for &quot;" + blockName + "&quot;:</strong>";
                            msg += "<pre class='mb-1 p-1 bg-light text-dark rounded' style='font-size:0.8rem;white-space:pre-wrap'>" + data.examples[blockName].replace(/</g, "&lt;") + "</pre>";
                        }
                        statusMessage(msg, "text-bg-warning");
                    } else {
                        statusMessage("Page saved");
                    }
                } else {
                    statusMessage("Page saved");
                }
            });
        } else {
            statusMessage("Error saving page", "text-bg-danger");
        }
    }).catch(function() {
        statusMessage("Error saving page", "text-bg-danger");
    });
}
var pageUnsaved = false;
var editorTextarea = document.querySelector('textarea[name="edited"]');
var savedContent = editorTextarea ? editorTextarea.value : '';
if (editorTextarea) {
    editorTextarea.addEventListener('input', function() {
        pageUnsaved = editorTextarea.value !== savedContent;
    });
}
window.addEventListener('beforeunload', function(e) {
    if (pageUnsaved) {
        e.preventDefault();
    }
});
document.addEventListener('keydown', function(e) {
    if ((e.metaKey || e.ctrlKey) && e.key === 's') {
        e.preventDefault();
        savePageAsync();
    }
});
document.querySelectorAll('.page-tree-folder').forEach(function(folder) {
    var target = document.querySelector(folder.getAttribute('href'));
    if (target) {
        target.addEventListener('show.bs.collapse', function() {
            folder.querySelector('.folder-icon').innerHTML = '&#9660;';
        });
        target.addEventListener('hide.bs.collapse', function() {
            folder.querySelector('.folder-icon').innerHTML = '&#9654;';
        });
    }
});
var csrfToken = '<?= CsrfProtection::getToken() ?>';
var currentPage = <?= json_encode($editPageName ?? '') ?>;
var allFolders = <?= json_encode($allFolders) ?>;

function submitPageAction(action, name, extra) {
    var form = document.createElement('form');
    form.method = 'POST';
    form.action = 'pages';
    form.innerHTML = '<input type="hidden" name="csrf_token" value="' + csrfToken + '">'
        + '<input type="hidden" name="action" value="' + action + '">'
        + '<input type="hidden" name="name" value="' + name + '">';
    if (extra) {
        for (var key in extra) {
            form.innerHTML += '<input type="hidden" name="' + key + '" value="' + extra[key] + '">';
        }
    }
    document.body.appendChild(form);
    form.submit();
}

function renamePage() {
    if (!currentPage) return;
    var baseName = currentPage.replace(/\.md$/, '').split('/').pop();
    var newName = prompt('Rename page:', baseName);
    if (newName === null || newName === baseName) return;
    submitPageAction('rename_page', currentPage, {new_name: newName});
}

function movePage() {
    if (!currentPage) return;
    var currentFolder = currentPage.replace(/\/[^/]+$/, '') || '/';
    var options = allFolders.map(function(f) {
        return (f === currentFolder ? '> ' : '  ') + f;
    }).join('\n');
    var dest = prompt('Move page to folder:\n\n' + options + '\n\nEnter folder path:', currentFolder);
    if (dest === null || dest === currentFolder) return;
    dest = dest.replace(/^\//, '').replace(/\/$/, '');
    submitPageAction('move_page', currentPage, {destination: dest});
}

function deletePage() {
    if (!currentPage) return;
    var baseName = currentPage.replace(/\.md$/, '').split('/').pop();
    if (!confirm('Delete page \'' + baseName + '\'?')) return;
    submitPageAction('delete_page', currentPage);
}

function renameFolder(folderPath, folderName) {
    var newName = prompt('Rename folder:', folderName);
    if (newName === null || newName === folderName) return;
    submitPageAction('rename_folder', folderPath, {new_name: newName});
}

function deleteFolder(folderPath, folderName) {
    if (!confirm('Delete empty folder \'' + folderName + '\'?')) return;
    submitPageAction('delete_folder', folderPath);
}

var historyCache = null;

function toggleHistory() {
    var modal = document.getElementById('page-history-modal');
    if (!modal) return;
    var body = document.getElementById('page-history-body');
    var count = document.getElementById('page-history-count');
    body.innerHTML = '<div class="p-4 text-center text-body-secondary">Loading…</div>';
    count.textContent = '';
    new bootstrap.Modal(modal).show();
    fetch('pages?history=1&page=' + encodeURIComponent(currentPage))
        .then(function(r) { return r.json(); })
        .then(function(versions) {
            historyCache = versions;
            count.textContent = versions.length + ' version(s)';
            if (versions.length === 0) {
                body.innerHTML = '<div class="p-4 text-body-secondary">No history available.</div>';
                return;
            }
            var html = '<ul class="list-group list-group-flush" style="max-height:60vh;overflow-y:auto;">';
            versions.forEach(function(v) {
                var kb = (v.size / 1024).toFixed(1);
                html += '<li class="list-group-item d-flex align-items-center" style="font-size:0.85rem;">'
                    + '<span class="flex-grow-1">' + escapeHtml(v.timestamp) + '</span>'
                    + '<span class="text-body-secondary me-2">' + kb + ' KB</span>'
                    + '<a href="#" class="btn btn-sm btn-outline-secondary me-1" onclick="previewVersion(\'' + escapeHtml(v.filename) + '\'); return false;">Preview</a>'
                    + '<a href="#" class="btn btn-sm btn-outline-primary" onclick="restoreVersion(\'' + escapeHtml(v.filename) + '\'); return false;">Restore</a>'
                    + '</li>';
            });
            html += '</ul>';
            body.innerHTML = html;
        })
        .catch(function() {
            body.innerHTML = '<div class="p-4 text-danger">Failed to load history.</div>';
        });
}

function previewVersion(filename) {
    if (!historyCache) return;
    var version = historyCache.find(function(v) { return v.filename === filename; });
    if (!version) return;
    var win = window.open('', '_blank');
    win.document.write('<html><head><title>Preview: ' + filename + '</title>'
        + '<style>'
        + ':root { color-scheme: light dark; }'
        + 'body { font-family: ui-monospace, SFMono-Regular, "SF Mono", Menlo, Consolas, monospace;'
        + ' padding: 2rem; white-space: pre-wrap; max-width: 80ch; margin: 0 auto;'
        + ' color: light-dark(#1a1a1a, #e0e0e0); background: light-dark(#fff, #1a1a1a); }'
        + '</style></head>'
        + '<body>' + version.content.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;') + '</body></html>');
    win.document.close();
}

function restoreVersion(filename) {
    if (!confirm('Restore this version? Current content will be saved as a snapshot first.')) return;
    submitPageAction('restore_page', currentPage, {version: filename});
}

function escapeHtml(str) {
    return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/'/g,'&#39;').replace(/"/g,'&quot;');
}

var previewActive = localStorage.getItem('reboot_preview') === 'true';
var previewDebounceTimer = null;

function togglePreview() {
    previewActive = !previewActive;
    localStorage.setItem('reboot_preview', previewActive);
    var editorCol = document.getElementById('editor-column');
    var previewCol = document.getElementById('preview-column');
    var toggleBtn = document.getElementById('preview-toggle');
    if (previewActive) {
        editorCol.classList.remove('col-md-9', 'col-xl-10');
        editorCol.classList.add('col-md-5', 'col-xl-6');
        previewCol.classList.remove('d-none');
        toggleBtn.classList.remove('btn-outline-secondary');
        toggleBtn.classList.add('btn-secondary');
        updatePreview();
        if (editorTextarea) {
            editorTextarea.addEventListener('input', schedulePreviewUpdate);
            editorTextarea.addEventListener('click', syncPreviewToBlock);
            editorTextarea.addEventListener('keyup', syncPreviewToBlock);
        }
    } else {
        editorCol.classList.remove('col-md-5', 'col-xl-6');
        editorCol.classList.add('col-md-9', 'col-xl-10');
        previewCol.classList.add('d-none');
        toggleBtn.classList.remove('btn-secondary');
        toggleBtn.classList.add('btn-outline-secondary');
        previewInitialized = false;
        if (editorTextarea) {
            editorTextarea.removeEventListener('input', schedulePreviewUpdate);
            editorTextarea.removeEventListener('click', syncPreviewToBlock);
            editorTextarea.removeEventListener('keyup', syncPreviewToBlock);
        }
    }
}

function schedulePreviewUpdate() {
    if (previewDebounceTimer) clearTimeout(previewDebounceTimer);
    previewDebounceTimer = setTimeout(updatePreview, 500);
}

var lastSyncedBlock = -1;

function syncPreviewToBlock() {
    if (!previewActive || !previewInitialized || !editorTextarea) return;
    var iframe = document.getElementById('preview-iframe');
    try {
        var doc = iframe.contentDocument;
        var sections = doc.querySelectorAll('section.block');
        if (sections.length === 0) return;

        // Find which block the cursor is in
        var cursorPos = editorTextarea.selectionStart;
        var textBeforeCursor = editorTextarea.value.substring(0, cursorPos);
        var blockIndex = (textBeforeCursor.match(/<!--[\s\S]*?-->/g) || []).length - 1;
        if (blockIndex < 0) blockIndex = 0;
        if (blockIndex >= sections.length) blockIndex = sections.length - 1;

        // Only scroll if the block changed
        if (blockIndex === lastSyncedBlock) return;
        lastSyncedBlock = blockIndex;

        // Center the block in the iframe viewport
        var section = sections[blockIndex];
        var iframeHeight = iframe.clientHeight;
        var scrollTarget = section.offsetTop - (iframeHeight / 2) + (section.offsetHeight / 2);
        doc.documentElement.scrollTo({ top: Math.max(0, scrollTarget), behavior: 'smooth' });
    } catch(e) {}
}

var previewInitialized = false;

function getPreviewForm() {
    var previewForm = document.getElementById('preview-form');
    if (!previewForm) {
        previewForm = document.createElement('form');
        previewForm.id = 'preview-form';
        previewForm.method = 'POST';
        previewForm.action = 'pages?preview=1';
        previewForm.target = 'preview-iframe';
        previewForm.style.display = 'none';
        previewForm.innerHTML = '<input type="hidden" name="csrf_token" value="' + csrfToken + '">'
            + '<input type="hidden" name="page" value="">'
            + '<input type="hidden" name="content" value="">';
        document.body.appendChild(previewForm);
    }
    return previewForm;
}

function updatePreview() {
    if (!previewActive || !currentPage) return;
    var iframe = document.getElementById('preview-iframe');
    var content = editorTextarea ? editorTextarea.value : '';

    if (!previewInitialized) {
        // First load: use form submit so the iframe gets its own CSP from the response
        var form = getPreviewForm();
        form.querySelector('[name="page"]').value = currentPage;
        form.querySelector('[name="content"]').value = content;
        iframe.onload = function() {
            iframe.onload = null;
            previewInitialized = true;
        };
        form.submit();
    } else {
        // Subsequent updates: fetch and replace only <main> to preserve scroll
        var formData = new FormData();
        formData.append('csrf_token', csrfToken);
        formData.append('page', currentPage);
        formData.append('content', content);
        fetch('pages?preview=1', {
            method: 'POST',
            body: formData
        }).then(function(r) { return r.text(); })
        .then(function(html) {
            var doc = iframe.contentDocument;
            var parser = new DOMParser();
            var newDoc = parser.parseFromString(html, 'text/html');
            var newMain = newDoc.querySelector('main');
            var oldMain = doc.querySelector('main');
            if (newMain && oldMain) {
                oldMain.innerHTML = newMain.innerHTML;
            } else {
                var scrollTop = doc.documentElement.scrollTop || doc.body.scrollTop;
                doc.body.innerHTML = newDoc.body.innerHTML;
                doc.documentElement.scrollTop = scrollTop;
            }
        });
    }
}

// Restore preview state on load
if (previewActive && currentPage) {
    previewActive = false; // togglePreview will flip it back to true
    togglePreview();
}
</script>