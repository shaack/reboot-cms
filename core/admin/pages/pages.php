<?php
/** @var \Shaack\Reboot\Reboot $reboot */
/** @var \Shaack\Reboot\Site $site */
/** @var \Shaack\Reboot\Request $request */
/** @var Shaack\Reboot\Admin $admin */
$admin = $site->getAddOn("Admin");

use Shaack\Logger;
use Shaack\Utils\FileSystemUtils;
use Shaack\Reboot\CsrfProtection;

$defaultSite = $admin->getDefaultSite();
$pagesDir = realpath($defaultSite->getFsPath() . "/pages");
$editPageName = $request->getParam("page");
$editable = false;
$pages = FileSystemUtils::getFileList($pagesDir, true);
usort($pages, function($a, $b) {
    return strcmp($a['name'], $b['name']);
});
if($editPageName) {
    Logger::debug("Editing page " . $editPageName);
}

// Build tree structure from flat file list
function buildPageTree(array $pages, string $pagesDir): array {
    $tree = [];
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
    return $tree;
}

function renderTree(array $tree, string $editPageName = null, bool &$editable = false, string $prefix = ""): string {
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
    // Render files first, then folders
    foreach ($files as $filePath) {
        $fileName = basename($filePath);
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
        $html .= "<li>";
        $html .= "<a class='page-tree-folder' data-bs-toggle='collapse' href='#" . $folderId . "' role='button' aria-expanded='" . ($expanded ? "true" : "false") . "'>";
        $html .= "<span class='folder-icon'>" . ($expanded ? "&#9660;" : "&#9654;") . "</span> " . htmlspecialchars($folderName);
        $html .= "</a>";
        $html .= "<div class='collapse" . ($expanded ? " show" : "") . "' id='" . $folderId . "'>";
        $html .= renderTree($children, $editPageName, $editable, $prefix . $folderName . "/");
        $html .= "</div>";
        $html .= "</li>";
    }
    $html .= "</ul>";
    return $html;
}

$pageTree = buildPageTree($pages, $pagesDir);
?>
<div class="container-fluid">
    <div class="row">
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
        </div>
        <div class="col-md-9 order-md-1 order-0">
            <?php if ($editPageName) {
                $fullPath = $pagesDir . $editPageName;
                // Validate the resolved path stays within the pages directory
                $resolvedPath = realpath(dirname($fullPath));
                if ($resolvedPath === false || strncmp($resolvedPath, $pagesDir, strlen($pagesDir)) !== 0) {
                    Logger::error("Path traversal attempt blocked: " . $editPageName);
                    $editPageName = null;
                } else {
                    $edited = $request->getParam("edited");
                    if ($edited !== null) {
                        CsrfProtection::validate($request);
                        file_put_contents($fullPath, $edited);
                    }
                ?>
                <!--suppress HtmlUnknownTarget -->
                <form method="post" action="pages?page=<?= urlencode($editPageName) ?>">
                    <input type="hidden" name="csrf_token" value="<?= CsrfProtection::getToken() ?>">
                    <!--suppress HtmlFormInputWithoutLabel -->
                    <textarea name="edited" class="form-control cm-md-editor markdown"><?= htmlspecialchars(file_get_contents($fullPath)) ?></textarea>
                    <button class="btn btn-primary">Save</button>
                    <?php
                    $viewPath = preg_replace('/\.md$/', '', $editPageName);
                    $viewPath = preg_replace('/\/index$/', '/', $viewPath);
                    $viewUrl = $reboot->getBaseWebPath() . $viewPath;
                    ?>
                    <a href="<?= htmlspecialchars($viewUrl) ?>" target="_blank" class="btn btn-outline-secondary ms-2">View Page</a>
                    <?= $edited !== null ? "<span class='ms-2 text-info fade-out'>Page saved…</span>" : "" ?>
                </form>
                <?php } ?>
            <?php } ?>
        </div>
    </div>
</div>
<script>
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
</script>