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

if (!$editPageName && file_exists($pagesDir . "/index.md")) {
    $editPageName = "/index.md";
}
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
        <div class="col-md-9 col-xl-10 order-md-1 order-0">
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
                    if ($edited !== null) {
                        CsrfProtection::validate($request);
                        file_put_contents($fullPath, $edited);
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
                    <a href="<?= htmlspecialchars($viewUrl) ?>" target="_blank" class="btn btn-sm btn-outline-secondary ms-2">View Page</a>
                </form>
                <script id="block-examples" type="application/json"><?= json_encode($blockExamplesForInsert) ?></script>
                <?php if (!empty($validationErrors)) { ?>
                <script id="validation-data" type="application/json"><?= json_encode(['errors' => $validationErrors, 'examples' => $blockExamples ?? []]) ?></script>
                <?php } ?>
                <?php } ?>
            <?php } ?>
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
                var dataEl = doc.getElementById('validation-data');
                if (dataEl) {
                    var data = JSON.parse(dataEl.textContent);
                    var list = data.errors.map(function(e) { return "<li>" + e + "</li>"; }).join("");
                    var msg = "Page saved with " + data.errors.length + " schema warning(s):<ul class='mb-0 mt-1'>" + list + "</ul>";
                    // Extract failing block names from errors
                    var failingBlocks = {};
                    data.errors.forEach(function(e) {
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
            });
        } else {
            statusMessage("Error saving page", "text-bg-danger");
        }
    }).catch(function() {
        statusMessage("Error saving page", "text-bg-danger");
    });
}
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
</script>