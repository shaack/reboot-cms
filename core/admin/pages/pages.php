<?php
/** @var \Shaack\Reboot\Reboot $reboot */
/** @var \Shaack\Reboot\Site $site */
/** @var \Shaack\Reboot\Request $request */
/** @var Shaack\Reboot\AddOns\Admin $admin */
$admin = $site->getAddOn("Admin");

use Shaack\Logger;
use Shaack\Utils\FileSystemUtils;
use Shaack\Reboot\AddOns\Admin\AdminHelper;
use Shaack\Reboot\CsrfProtection;
use Shaack\Reboot\Page;
use Shaack\Reboot\Block;
use Shaack\Reboot\Blueprint;
use Shaack\Reboot\AddOns\Admin\PageActionHandler;
use Shaack\Reboot\AddOns\Admin\PageHistoryHelper;
use Shaack\Reboot\AddOns\Admin\PageTreeHelper;

$defaultSite = $admin->getDefaultSite();
$pagesDir = realpath($defaultSite->getFsPath() . "/pages");
$localConfig = $admin->getLocalConfig();
$historyMaxVersions = $localConfig['history']['maxVersions'] ?? 50;
$historyDir = $reboot->getBaseFsPath() . "/local/history/pages";

$editPageName = $request->getParam("page");
$pages = FileSystemUtils::getFileList($pagesDir, true);
usort($pages, function($a, $b) {
    return strcmp($a['name'], $b['name']);
});

// JSON API endpoints
if ($request->getParam("list")) {
    require __DIR__ . "/../api/pages-list.php";
    return;
}
if ($request->getParam("history")) {
    require __DIR__ . "/../api/pages-history.php";
    return;
}
if ($request->getParam("preview") && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require __DIR__ . "/../api/pages-preview.php";
    return;
}
if ($request->getParam("validate") && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require __DIR__ . "/../api/pages-validate.php";
    return;
}
// a GET only, the form of a structured page posts its values as `fields`
if ($request->getParam("fields", "get")) {
    require __DIR__ . "/../api/pages-fields.php";
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

if (!$editPageName) {
    foreach (["/index.md", "/index.yml"] as $homepage) {
        if (file_exists($pagesDir . $homepage)) {
            $editPageName = $homepage;
            break;
        }
    }
}
if($editPageName) {
    Logger::debug("Editing page " . $editPageName);
}

$allFolders = PageTreeHelper::collectFolders($pagesDir);
$pageTree = PageTreeHelper::buildPageTree($pages, $pagesDir);
$editable = false;
$treeHtml = PageTreeHelper::renderTree($pageTree, $editPageName, $editable);
if (!$editable && $editPageName) {
    Logger::error("" . $editPageName . " is not editable.");
    $editPageName = null;
}
$pageCount = substr_count($treeHtml, "class='page-tree-file");
// the page being edited and its address, shown next to the title
$titleExtra = '<button type="button" class="btn btn-sm btn-link text-body-secondary p-0 d-none d-md-inline-flex align-self-center"'
    . ' id="sidebar-toggle" title="Show or hide the page list" aria-label="Show or hide the page list" aria-controls="pages-sidebar">'
    . AdminHelper::icon('sidebar') . '</button>';
if ($editPageName) {
    $viewPath = preg_replace('/\/index$/', '/', preg_replace('/\.(md|yml)$/', '', $editPageName));
    $viewUrl = $reboot->getBaseWebPath() . $viewPath;
    $titleExtra .= '<span class="page-location">'
        . '<code>' . htmlspecialchars(ltrim($editPageName, '/')) . '</code>'
        . '<a href="' . htmlspecialchars($viewUrl) . '" target="_blank" title="View the page in a new tab">'
        . htmlspecialchars($viewPath) . AdminHelper::icon('external') . '</a>'
        . '<span class="unsaved-dot d-none" id="unsaved-badge" title="Unsaved changes" role="img" aria-label="Unsaved changes"></span>'
        . '</span>';
}
?>
<div class="container-fluid">
    <?= AdminHelper::renderStatusMessages($pageActionError, $pageActionSuccess) ?>
    <?= AdminHelper::renderPageTitle('Pages', '', '
        <p>Every page is a file in <code>site/pages/</code>, and its path is its address. <code>index</code> is the homepage <code>/</code>, <code>about</code> is <code>/about</code>, <code>blog/index</code> is <code>/blog</code>. Pages written in PHP are not listed here.</p>
        <h6>Pages</h6>
        <ul>
            <li>A page opens as a form: the title, the description and a panel per block, each with the fields of its block type. A right click in the page list creates one with <em>New page</em>.</li>
            <li>A click on the name of a block folds it, the switch turns it off and on, the arrows move it, the cross removes it. <em>Add block</em> below adds one. A list in a block, like the images of a gallery, adds, moves and removes its items the same way.</li>
            <li><em>Preview</em> shows the page with the changes next to the form and follows the field you type in. The block types and their fields are made by the developer of the site, a folder in <code>site/blocks/</code> with a template and a YAML file of its fields.</li>
            <li><em>Add block › Markdown blocks</em> offers the blocks of Markdown pages as well. They keep their Markdown in one field.</li>
        </ul>
        <h6>Markdown pages</h6>
        <ul>
            <li>A page marked with the Markdown sign in the list is a Markdown page, written as text in an editor. It suits long texts and those who like Markdown. A right click in the page list creates one with <em>New Markdown page</em>.</li>
            <li><em>… › Convert to structured page</em> below a Markdown page turns it into a page with a form at the same address. It shows first what becomes of each block. The Markdown page is kept in the history of the page, restoring it there turns the page back into a Markdown page.</li>
            <li>Markdown formats the text: <code># Heading</code>, <code>**bold**</code>, <code>[link](/about)</code>, lists with <code>-</code>. The toolbar inserts it for you, also links to other pages and images from <a href="media">Media</a>.</li>
            <li>An optional block at the very top sets the page title and other values for the template:
                <pre class="mb-1">---
title: About us
description: Shown by search engines
---</pre></li>
            <li>A comment like <code>&lt;!-- hero --&gt;</code> starts a block, a section with its own layout from <code>site/blocks/</code>. It runs up to the next block. A line with <code>---</code> separates the parts of a block, for example heading and text.</li>
            <li>The toolbar button <em>Insert block</em> inserts a block with example content. The line below the editor shows in which block and part the cursor is and what belongs there. While you type, the page is checked: a button below the editor lists what does not fit, for example a missing heading or a misspelled block name, jumps to the block and shows the expected Markdown.</li>
            <li>The lines with a block comment and the <code>---</code> between the parts of a block are protected against typing by mistake. To delete or replace one, select the whole line, or everything. In the frontmatter only the values can be changed, not the names before the colon, and no line can be added or removed. To change or delete these lines, switch the protection off with the lock in the editor toolbar, for this browser.</li>
            <li>With the cursor in a block, the arrows below the editor move the block up or down, and the switch turns it off and on. A block that is off stays in the page but is not shown, its comment then reads <code>&lt;!-- hero: {disabled: true} --&gt;</code>.</li>
            <li>A comment that is no block name, like <code>&lt;!-- TODO: check prices --&gt;</code>, stays an invisible note.</li>
        </ul>
        <h6>Saving and history</h6>
        <ul>
            <li>Save with the button or <kbd>⌘ S</kbd> / <kbd>Ctrl S</kbd>. A yellow dot next to the page name and on <em>Save</em> shows that there is something to save. Leaving the page with unsaved changes asks first.</li>
            <li>The eye in the editor toolbar opens the <em>preview</em>. It shows the page with your changes next to the editor, before saving. It needs a wide window. The zoom below the preview sets its size, <em>Fit desktop</em> shows the page as on a desktop screen, scaled down to the column. Drag the bar between editor and preview to change their widths, a double click resets them.</li>
            <li>Every save keeps the previous version. <em>… › History</em> shows the last ' . (int)$historyMaxVersions . ' versions and restores one; the current text is kept as a version too. This works the same for pages with forms and for Markdown pages.</li>
            <li>A right click in the page list, on a touch screen touch and hold, opens a menu. On a page it creates a new page or folder next to it, renames, moves or deletes the page. On a folder it does the same inside the folder, an empty folder can be deleted. On the free space below it creates pages and folders at the top level. A new folder starts with an empty <code>index</code> page, marked with a house. <em>…</em> below the editor has the same actions for the open page. From 30 pages on a field above the list finds a page by its name.</li>
            <li>The button next to the title hides the page list, so editor and preview get the whole width. The address next to the file name opens the page in a new tab.</li>
        </ul>
    ', $titleExtra) ?>
    <div class="row gx-2" id="pages-row">
    <script>try { if (localStorage.getItem('reboot_sidebar_collapsed') === 'true') document.getElementById('pages-row').classList.add('sidebar-collapsed') } catch (e) {}</script>
        <div class="col-md-3 col-xl-2 col-sidebar order-md-0 order-1 mt-4 mt-md-0" id="pages-sidebar">
            <?php if ($pageCount >= 30) { ?>
                <div class="page-tree-tools">
                    <input type="search" class="form-control form-control-sm" id="page-filter" placeholder="Find page" aria-label="Find page">
                </div>
            <?php } ?>
            <nav class="nav nav-compact flex-column" id="page-tree-nav">
                <?= $treeHtml ?>
            </nav>
            <p class="page-tree-hint">Right click or touch and hold for new pages and folders, renaming and deleting.</p>
        </div>
        <div class="col-12 col-lg-9 col-xl-10 order-md-1 order-0 editor-loading" id="editor-column">
            <?php if ($editPageName) {
                $fullPath = $pagesDir . $editPageName;
                $resolvedPath = realpath(dirname($fullPath));
                if (!FileSystemUtils::isInside($resolvedPath, $pagesDir)) {
                    Logger::error("Path traversal attempt blocked: " . $editPageName);
                    $editPageName = null;
                } else if (str_ends_with($editPageName, ".yml")) {
                    require __DIR__ . "/../addons/Admin/templates/structured-page.php";
                } else if ($request->getParam("convert", "get")) {
                    require __DIR__ . "/../addons/Admin/templates/convert-page.php";
                } else {
                    $edited = $request->getParam("edited");
                    $validationErrors = [];
                    $pageChanged = false;
                    $saveError = null;
                    if ($edited !== null) {
                        // Browsers submit textarea content with CRLF line endings, the files are kept with LF
                        $edited = str_replace("\r\n", "\n", (string)$edited);
                        CsrfProtection::validate($request);
                        $pageChanged = !file_exists($fullPath) || file_get_contents($fullPath) !== $edited;
                        if ($pageChanged) {
                            try {
                                PageHistoryHelper::savePageSnapshot($fullPath, $pagesDir, $historyDir, $historyMaxVersions);
                                FileSystemUtils::writeAtomic($fullPath, $edited);
                            } catch (\RuntimeException $e) {
                                Logger::error("Saving " . $editPageName . " failed: " . $e->getMessage());
                                $saveError = $e->getMessage();
                            }
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
                        // Block::names() leaves out the helpers with an underscore, like _helpers.php,
                        // rendering them here would declare their functions a second time
                        foreach (Block::names($defaultSite) as $blockName) {
                            // a block type with a blueprint is for structured pages, unless it says otherwise
                            $blockBlueprint = Blueprint::forBlock($defaultSite, $blockName);
                            if ($blockBlueprint && !$blockBlueprint->isForMarkdown()) {
                                continue;
                            }
                            $block = new Block($defaultSite, $blockName, '');
                            ob_start();
                            $block->render($request);
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
                    <button type="button" class="btn btn-sm btn-primary px-3" onclick="savePageAsync()" id="save-button">Save<span class="unsaved-dot d-none" aria-hidden="true"></span></button>
                    <?php /* <button type="button" class="btn btn-sm btn-outline-secondary ms-2" onclick="toggleStructuredEditor()" id="structured-toggle">Structured</button> */ ?>
                    <?php $currentBaseName = basename($editPageName, '.md'); ?>
                    <div class="dropdown d-inline-block ms-2">
                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">&#8230;</button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="#" onclick="toggleHistory(); return false;">History</a></li>
                            <li><a class="dropdown-item" href="#" onclick="renamePage(); return false;">Rename Page</a></li>
                            <li><a class="dropdown-item" href="#" onclick="movePage(); return false;">Move Page</a></li>
                            <li><a class="dropdown-item" href="pages?page=<?= urlencode($editPageName) ?>&amp;convert=1">Convert to structured page</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="#" onclick="deletePage(); return false;">Delete Page</a></li>
                        </ul>
                    </div>
                    <div class="dropup d-inline-block ms-2">
                        <button type="button" class="btn btn-sm btn-outline-warning dropdown-toggle d-none" id="editor-problems-toggle" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false"></button>
                        <div class="dropdown-menu editor-problems" id="editor-problems"></div>
                    </div>
                    <span class="block-actions d-none" id="block-actions" role="group" aria-label="Block">
                        <button type="button" class="btn btn-sm btn-link" id="block-up" title="Move the block up" aria-label="Move the block up"><?= AdminHelper::icon('arrow-up') ?></button>
                        <button type="button" class="btn btn-sm btn-link" id="block-down" title="Move the block down" aria-label="Move the block down"><?= AdminHelper::icon('arrow-down') ?></button>
                        <button type="button" class="btn btn-sm btn-link" id="block-toggle" title="Switch the block off" aria-label="Switch the block off"><?= AdminHelper::icon('toggle-on', 'icon-on') ?><?= AdminHelper::icon('toggle-off', 'icon-off') ?></button>
                    </span>
                    <span class="editor-status" id="editor-status" aria-live="polite"></span>
                </form>
                <?php require __DIR__ . "/../addons/Admin/templates/history-modal.php"; ?>
                <div id="structured-editor" class="d-none mt-2">
                    <div id="structured-editor-blocks"></div>
                    <button type="button" class="btn btn-sm btn-primary px-3 mt-2" onclick="saveStructuredEditor()">Save</button>
                </div>
                <script id="block-examples" type="application/json"><?= json_encode($blockExamplesForInsert) ?></script>
                <?php if ($edited !== null) { ?>
                <script id="save-result" type="application/json"><?= json_encode([
                    'changed' => $pageChanged,
                    'error' => $saveError,
                    'validationErrors' => $validationErrors,
                    'examples' => $blockExamples ?? []
                ]) ?></script>
                <?php } ?>
                <?php } ?>
            <?php } ?>
        </div>
        <div class="col-lg-4 order-md-2 d-none d-lg-none" id="preview-column" style="position:sticky;top:48px;">
            <div id="preview-resizer" role="separator" aria-orientation="vertical" aria-label="Resize the preview" tabindex="0" title="Drag to resize, double click to reset"></div>
            <div id="preview-frames" style="position:relative;width:100%;height:calc(100vh - 189px);overflow:hidden;border:1px solid rgba(128,128,128,0.3);border-radius:4px;">
                <iframe id="preview-iframe-a" name="preview-iframe-a" style="position:absolute;top:0;left:0;width:100%;height:100%;border:0;transform-origin:0 0;visibility:hidden;"></iframe>
                <iframe id="preview-iframe-b" name="preview-iframe-b" style="position:absolute;top:0;left:0;width:100%;height:100%;border:0;transform-origin:0 0;visibility:hidden;"></iframe>
            </div>
            <div class="d-flex align-items-center justify-content-between gap-2 mt-3">
                <div class="form-check form-switch small mb-0">
                    <input class="form-check-input" type="checkbox" role="switch" id="preview-follow" checked>
                    <label class="form-check-label text-body-secondary" for="preview-follow">Follow the cursor</label>
                </div>
                <select class="form-select form-select-sm w-auto" id="preview-zoom" aria-label="Preview zoom">
                    <option value="fit">Fit desktop</option>
                    <option value="1">100 %</option>
                    <option value="0.75">75 %</option>
                    <option value="0.5">50 %</option>
                </select>
            </div>
        </div>
    </div>
</div>
<script>
window.pagesConfig = {
    csrfToken: <?= json_encode(CsrfProtection::getToken()) ?>,
    currentPage: <?= json_encode($editPageName ?? '') ?>,
    blockNames: <?= json_encode(Block::names($defaultSite)) ?>,
    allFolders: <?= json_encode($allFolders) ?>
};
</script>
<script src="assets/pages.js?v=<?= @filemtime($reboot->getBaseFsPath() . "/web/admin/assets/pages.js") ?>"></script>
