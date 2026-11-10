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
use Shaack\Reboot\AddOns\Admin\LanguageSwitch;
use Shaack\Reboot\AddOns\Admin\StructuredPageHandler;
use Shaack\Reboot\AddOns\Admin\Translator;
use Shaack\Reboot\Blueprint;
use Shaack\Reboot\CsrfProtection;

$pageBlueprint = Blueprint::forPage($defaultSite);
// on a multilingual site the text fields get an input per language, the switcher above shows one language
// with a key for DeepL in the local configuration, a field in another language can be translated
$translatable = $defaultSite->isMultilingual() && Translator::fromConfig($localConfig) !== null;
FieldForm::setLanguages($defaultSite->getLanguages(), $defaultSite->getDefaultLanguage(), $translatable);
$editLanguages = $defaultSite->isMultilingual() ? $defaultSite->getLanguages() : [];
$structuredHandler = new StructuredPageHandler($pageBlueprint, $pagesDir, $historyDir, $historyMaxVersions);
$structuredMessage = $request->getParam("converted", "get") ? "The page is a structured page now. Its Markdown version is in the history." : null;
$structuredError = null;
$structuredChanged = null;
if ($request->getParam("structured", "post") !== null) {
    try {
        CsrfProtection::validate($request);
        $input = $request->getParam("fields", "post");
        $structuredChanged = $structuredHandler->save($fullPath, is_array($input) ? $input : []);
        $structuredMessage = $structuredChanged ? "Page saved" : "No changes to save";
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
<?php if ($request->getParam("structured", "post") !== null) { ?>
    <?php // read by pages.js, which saves the form in the background ?>
    <script id="save-result" type="application/json"><?= json_encode([
        "changed" => $structuredChanged,
        "error" => $structuredError,
        "problems" => count($structuredProblems),
    ]) ?></script>
<?php } ?>
<?php if ($structuredMessage) { ?>
    <script>statusMessage(<?= json_encode($structuredMessage) ?>)</script>
<?php } ?>
<?php if ($structuredError) { ?>
    <div class="alert alert-danger"><?= htmlspecialchars($structuredError) ?></div>
<?php } ?>
<?php if ($structuredValues !== null) { ?>
    <?php if ($structuredProblems) { ?>
        <div class="alert alert-warning" id="structured-problems">
            Some fields do not fit their blueprint, they are marked below. The page shows them anyway, as far as the templates can.
        </div>
    <?php } ?>
    <?php if ($editLanguages) { ?>
        <style><?php foreach ($editLanguages as $language) { ?>.structured-page[data-edit-lang="<?= $language ?>"] .field-language:not([data-lang="<?= $language ?>"]){display:none}<?php } ?></style>
        <div class="language-tools">
            <?= LanguageSwitch::render($editLanguages, $defaultSite->getDefaultLanguage(), null) ?>
            <?php if ($translatable) { ?>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="translate-empty" hidden
                        title="Translate every empty field of this language from <?= htmlspecialchars(strtoupper($defaultSite->getDefaultLanguage())) ?> with DeepL">Translate empty fields</button>
            <?php } ?>
        </div>
    <?php } ?>
    <!--suppress HtmlUnknownTarget -->
    <form method="post" action="pages?page=<?= urlencode($editPageName) ?>" class="structured-page" data-field-form<?= $editLanguages ? ' data-edit-lang="' . htmlspecialchars($defaultSite->getDefaultLanguage()) . '" data-default-lang="' . htmlspecialchars($defaultSite->getDefaultLanguage()) . '"' : '' ?>>
        <input type="hidden" name="csrf_token" value="<?= CsrfProtection::getToken() ?>">
        <input type="hidden" name="structured" value="1">
        <?php
        // the fields of the page, title and description for search engines, fold away above the blocks
        $settingFields = array_keys(array_filter($pageBlueprint->getFields(), fn($props) => $props["type"] !== "blocks"));
        $blockFields = array_diff(array_keys($pageBlueprint->getFields()), $settingFields);
        // the title in the default language, a value per language is a map
        $pageTitle = $defaultSite->localize($structuredValues["title"] ?? "", $defaultSite->getDefaultLanguage());
        $pageTitle = is_scalar($pageTitle) ? trim((string)$pageTitle) : "";
        $settingsOpen = $pageTitle === ""
            || array_filter(array_keys($structuredProblems), fn($key) => in_array(explode(".", $key)[0], $settingFields, true));
        $settingsSummary = $pageTitle;
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
            <a class="btn btn-sm btn-outline-secondary ms-2" href="<?= htmlspecialchars($viewUrl) ?>" target="_blank" title="Open the saved page in a new tab"><?= AdminHelper::icon("external", "me-1") ?>Open page</a>
            <button type="button" class="btn btn-sm btn-outline-secondary ms-2" data-fold-all title="Fold or unfold all blocks"><?= AdminHelper::icon("arrows-collapse", "me-1") ?><?= str_replace("<svg ", "<svg hidden ", AdminHelper::icon("arrows-expand", "me-1")) ?><span>Collapse all</span></button>
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
