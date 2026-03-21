<?php
/** @var \Shaack\Reboot\Reboot $reboot */
/** @var \Shaack\Reboot\Site $site */
/** @var \Shaack\Reboot\Request $request */
/** @var Shaack\Reboot\Admin $admin */
$admin = $site->getAddOn("Admin");

use Shaack\Logger;
use Shaack\Utils\FileSystemUtils;
use Shaack\Reboot\Admin\AdminHelper;
use Shaack\Reboot\CsrfProtection;
use Shaack\Reboot\Page;
use Shaack\Reboot\Block;
use Shaack\Reboot\Admin\PageActionHandler;
use Shaack\Reboot\Admin\PageHistoryHelper;
use Shaack\Reboot\Admin\PageTreeHelper;

$defaultSite = $admin->getDefaultSite();
$pagesDir = realpath($defaultSite->getFsPath() . "/pages");
$localConfig = $admin->getLocalConfig();
$historyMaxVersions = $localConfig['history']['maxVersions'] ?? 50;
$historyDir = $reboot->getBaseFsPath() . "/local/history/pages";

$editPageName = $request->getParam("page");
$editable = false;
$pages = FileSystemUtils::getFileList($pagesDir, true);
usort($pages, function($a, $b) {
    return strcmp($a['name'], $b['name']);
});

// JSON API endpoints
if ($request->getParam("list")) {
    require __DIR__ . "/api/pages-list.php";
    return;
}
if ($request->getParam("history")) {
    require __DIR__ . "/api/pages-history.php";
    return;
}
if ($request->getParam("preview") && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require __DIR__ . "/api/pages-preview.php";
    return;
}

// Handle page/folder management actions
$pageAction = $request->getParam("action");
$pageActionError = null;
$pageActionSuccess = null;
if ($pageAction) {
    $actionResult = AdminHelper::handleAction($request, function() use ($pageAction, $request, $pagesDir, $historyDir, $historyMaxVersions) {
        $targetName = $request->getParam("name") ?? "";
        $targetName = str_replace("..", "", $targetName);
        $handler = new PageActionHandler($pagesDir, $historyDir, $historyMaxVersions);
        return $handler->handle($pageAction, $targetName, $request);
    });
    $pageActionError = $actionResult['error'];
    $pageActionSuccess = $actionResult['success'];
    if (!$pageActionError) {
        if (array_key_exists('editPageName', $actionResult)) {
            $editPageName = $actionResult['editPageName'];
        }
        // Handle folder rename: update editPageName if it was inside the renamed folder
        if (isset($actionResult['renamedFolder']) && $editPageName) {
            $rf = $actionResult['renamedFolder'];
            $oldPrefix = "/" . $rf['oldPrefix'] . "/";
            $newPrefix = "/" . (dirname($rf['oldPrefix']) === "." ? "" : dirname($rf['oldPrefix']) . "/") . $rf['newName'] . "/";
            if (str_starts_with($editPageName, $oldPrefix)) {
                $editPageName = $newPrefix . substr($editPageName, strlen($oldPrefix));
            }
        }
        // Refresh file list after any action
        $pages = FileSystemUtils::getFileList($pagesDir, true);
        usort($pages, function($a, $b) { return strcmp($a['name'], $b['name']); });
    }
}

if (!$editPageName && file_exists($pagesDir . "/index.md")) {
    $editPageName = "/index.md";
}
if($editPageName) {
    Logger::debug("Editing page " . $editPageName);
}

$allFolders = PageTreeHelper::collectFolders($pagesDir);
$pageTree = PageTreeHelper::buildPageTree($pages, $pagesDir);
?>
<div class="container-fluid">
    <?= AdminHelper::renderStatusMessages($pageActionError, $pageActionSuccess) ?>
    <div class="row gx-2">
        <div class="col-md-3 col-xl-2 col-sidebar order-md-0 order-1 mt-4 mt-md-0">
            <nav class="nav nav-compact flex-column">
                <?php
                echo PageTreeHelper::renderTree($pageTree, $editPageName, $editable);
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
        <div class="col-12 col-lg-9 col-xl-10 order-md-1 order-0" id="editor-column">
            <?php if ($editPageName) {
                $fullPath = $pagesDir . $editPageName;
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
                            PageHistoryHelper::savePageSnapshot($fullPath, $pagesDir, $historyDir, $historyMaxVersions);
                            file_put_contents($fullPath, $edited);
                        }
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
                    <button type="button" class="btn btn-sm btn-outline-secondary ms-2 d-none d-lg-inline-block" onclick="togglePreview()" id="preview-toggle">Preview</button>
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
        <div class="col-lg-4 order-md-2 d-none d-lg-none" id="preview-column" style="position:sticky;top:56px;height:calc(100vh - 167px);">
            <div style="position:relative;width:100%;height:100%;">
                <iframe id="preview-iframe-a" name="preview-iframe-a" style="position:absolute;top:0;left:0;width:100%;height:100%;border:1px solid rgba(128,128,128,0.3);border-radius:4px;visibility:hidden;"></iframe>
                <iframe id="preview-iframe-b" name="preview-iframe-b" style="position:absolute;top:0;left:0;width:100%;height:100%;border:1px solid rgba(128,128,128,0.3);border-radius:4px;visibility:hidden;"></iframe>
            </div>
        </div>
    </div>
</div>
<script>
window.pagesConfig = {
    csrfToken: <?= json_encode(CsrfProtection::getToken()) ?>,
    currentPage: <?= json_encode($editPageName ?? '') ?>,
    allFolders: <?= json_encode($allFolders) ?>
};
</script>
<script src="assets/pages.js"></script>
