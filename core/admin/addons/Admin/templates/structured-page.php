<?php
/**
 * The editor of a structured page, a YAML file in `site/pages/`, included by
 * the page Pages instead of the Markdown editor. Its fields come from the page
 * blueprint, its blocks from the blueprints of the block types.
 *
 * @var \Shaack\Reboot\Reboot $reboot
 * @var \Shaack\Reboot\Site $defaultSite
 * @var \Shaack\Reboot\Request $request
 * @var string $fullPath the file of the page
 * @var string $editPageName the file of the page in `site/pages/`, like `/about.yml`
 * @var string $pagesDir
 * @var string $historyDir
 * @var int $historyMaxVersions
 */

use Shaack\Logger;
use Shaack\Reboot\AddOns\Admin\AdminHelper;
use Shaack\Reboot\AddOns\Admin\FieldForm;
use Shaack\Reboot\AddOns\Admin\StructuredPageHandler;
use Shaack\Reboot\Blueprint;
use Shaack\Reboot\CsrfProtection;

$pageBlueprint = Blueprint::forPage($defaultSite);
$structuredHandler = new StructuredPageHandler($pageBlueprint, $pagesDir, $historyDir, $historyMaxVersions);
$structuredMessage = $request->getParam("converted", "get") ? "The page is a structured page now. Its Markdown version is in the history." : null;
$structuredError = null;
if ($request->getParam("structured", "post") !== null) {
    try {
        CsrfProtection::validate($request);
        $input = $request->getParam("fields", "post");
        $changed = $structuredHandler->save($fullPath, is_array($input) ? $input : []);
        $structuredMessage = $changed ? "Page saved" : "No changes to save";
    } catch (\RuntimeException $e) {
        Logger::error("Saving " . $editPageName . " failed: " . $e->getMessage());
        $structuredError = $e->getMessage();
    }
}
try {
    $structuredValues = $structuredHandler->readForForm($fullPath);
    $structuredProblems = $structuredHandler->validate($fullPath);
} catch (\RuntimeException $e) {
    $structuredError = $e->getMessage();
    $structuredValues = null;
    $structuredProblems = [];
}
?>
<script>document.title = <?= json_encode(basename($editPageName, '.yml') . ' – Reboot CMS Admin') ?>;</script>
<?php if ($structuredMessage) { ?>
    <script>statusMessage(<?= json_encode($structuredMessage) ?>)</script>
<?php } ?>
<?php if ($structuredError) { ?>
    <div class="alert alert-danger"><?= htmlspecialchars($structuredError) ?></div>
<?php } ?>
<?php if ($structuredValues !== null) { ?>
    <?php if ($structuredProblems) { ?>
        <div class="alert alert-warning">
            Some fields do not fit their blueprint, they are marked below. The page shows them anyway, as far as the templates can.
        </div>
    <?php } ?>
    <!--suppress HtmlUnknownTarget -->
    <form method="post" action="pages?page=<?= urlencode($editPageName) ?>" class="structured-page" data-field-form>
        <input type="hidden" name="csrf_token" value="<?= CsrfProtection::getToken() ?>">
        <input type="hidden" name="structured" value="1">
        <?php
        // the fields of the page, title and description for search engines, fold away above the blocks
        $settingFields = array_keys(array_filter($pageBlueprint->getFields(), fn($props) => $props["type"] !== "blocks"));
        $blockFields = array_diff(array_keys($pageBlueprint->getFields()), $settingFields);
        $settingsOpen = trim((string)($structuredValues["title"] ?? "")) === ""
            || array_filter(array_keys($structuredProblems), fn($key) => in_array(explode(".", $key)[0], $settingFields, true));
        $settingsSummary = trim((string)($structuredValues["title"] ?? ""));
        ?>
        <div class="structured-page-fields">
        <?php if ($settingFields) { ?>
            <?php // open when something is missing there, otherwise field-form.js restores the state the editor left ?>
            <details class="page-settings" data-fold="settings"<?= $settingsOpen ? " open data-fold-forced" : "" ?>>
                <summary>
                    <?= AdminHelper::icon("chevron", "page-settings-chevron") ?>
                    <strong>Page settings</strong>
                    <span class="page-settings-summary"><?= htmlspecialchars($settingsSummary !== "" ? $settingsSummary : "Title and description for search engines") ?></span>
                </summary>
                <div class="page-settings-body">
                    <?= FieldForm::render($pageBlueprint, $structuredValues, $structuredProblems, "fields", "", $settingFields) ?>
                </div>
            </details>
        <?php } ?>
        <?= FieldForm::render($pageBlueprint, $structuredValues, $structuredProblems, "fields", "", array_values($blockFields)) ?>
        </div>
        <div class="structured-page-actions">
            <button type="submit" class="btn btn-sm btn-primary px-3" id="save-button">Save<span class="unsaved-dot d-none" aria-hidden="true" data-save-dot></span></button>
            <button type="button" class="btn btn-sm btn-outline-secondary ms-2 d-none d-lg-inline-block" id="structured-preview-toggle" aria-pressed="false" title="Show the page next to the form, before saving">Preview</button>
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
        </div>
    </form>
    <?php require __DIR__ . "/history-modal.php"; ?>
<?php } ?>
<script type="module" src="assets/field-form.js?v=<?= @filemtime($reboot->getBaseFsPath() . "/web/admin/assets/field-form.js") ?>"></script>
