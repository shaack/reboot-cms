<?php
/** @var \Shaack\Reboot\Reboot $reboot */
/** @var \Shaack\Reboot\Site $site */
/** @var \Shaack\Reboot\Request $request */
/** @var Shaack\Reboot\AddOns\Admin $admin */
$admin = $site->getAddOn("Admin");

use Shaack\Reboot\AddOns\Admin\AdminHelper;
use Shaack\Reboot\AddOns\Admin\DataEntryHandler;
use Shaack\Reboot\AddOns\Admin\FieldForm;
use Shaack\Reboot\AddOns\Admin\LanguageSwitch;
use Shaack\Reboot\Blueprint;
use Shaack\Reboot\Collection;
use Shaack\Reboot\CsrfProtection;
use Shaack\Reboot\Entry;

$defaultSite = $admin->getDefaultSite();
$localConfig = $admin->getLocalConfig();
$dataDir = $defaultSite->getFsPath() . "/data";
$historyDir = $reboot->getBaseFsPath() . "/local/history/data";
$historyMaxVersions = $localConfig['history']['maxVersions'] ?? 50;

$blueprints = Blueprint::all($defaultSite);
// on a multilingual site the text fields get an input per language, the switcher above shows one language
FieldForm::setLanguages($defaultSite->getLanguages(), $defaultSite->getDefaultLanguage());
$editLanguages = $defaultSite->isMultilingual() ? $defaultSite->getLanguages() : [];
$collectionName = (string)($request->getParam("collection") ?? array_key_first($blueprints) ?? "");
$blueprint = $blueprints[$collectionName] ?? null;
$slug = $request->getParam("entry");
$slug = is_string($slug) && $slug !== "" ? $slug : null;
$isNew = $slug === null && $request->getParam("new");
$handler = $blueprint ? new DataEntryHandler($blueprint, $dataDir, $historyDir, $historyMaxVersions) : null;
if ($handler && $slug !== null && !$handler->file($slug)) {
    $slug = null;
}

// save or delete an entry
$actionError = null;
$actionSuccess = null;
$action = $request->getParam("action", "post");
$input = $request->getParam("fields", "post");
if ($handler && $action) {
    $result = AdminHelper::handleAction($request, function () use ($handler, $action, &$slug, &$isNew, $input, $request) {
        if ($action === "delete" && $slug !== null) {
            $handler->delete($slug);
            $slug = null;
            return "Entry deleted";
        }
        if ($action === "save") {
            $created = $slug === null;
            $slug = $handler->save($slug, is_array($input) ? $input : [], $request->getParam("draft", "post") === "1");
            $isNew = false;
            return $created ? "Entry created" : "Entry saved";
        }
        throw new \InvalidArgumentException("Unknown action: " . $action);
    });
    $actionError = $result['error'];
    $actionSuccess = $result['success'];
}

$collection = $blueprint ? Collection::load($defaultSite, $collectionName, true) : null;
$entry = $collection && $slug !== null ? $collection->find($slug) : null;

function dataCell(Entry $entry, string $field, array $props): string
{
    $value = $entry->getValues()[$field] ?? null;
    if ($props["type"] === "boolean") {
        return $value ? "✓" : "";
    }
    if (!is_scalar($value)) {
        return "";
    }
    return htmlspecialchars(mb_strimwidth((string)$value, 0, 60, "…"));
}

$entryTitle = null;
if ($entry) {
    $firstField = array_key_first($blueprint->getFields());
    $entryTitle = $firstField ? (string)($entry->getValues()[$firstField] ?? "") : "";
    $entryTitle = $entryTitle !== "" ? $entryTitle : $entry->getSlug();
}
// After a save or delete the address names what is shown now, so reloading the
// page shows it again instead of creating the entry a second time
$canonicalUrl = null;
if ($actionSuccess) {
    $canonicalUrl = "data?collection=" . urlencode($collectionName) . ($entry ? "&entry=" . urlencode($entry->getSlug()) : "");
}
$titleExtra = "";
if ($blueprint && ($entry || $isNew)) {
    $titleExtra = '<span class="page-location"><code>'
        . htmlspecialchars("data/" . $collectionName . "/" . ($entry ? $entry->getSlug() . ".md" : "…")) . '</code></span>';
}
?>
<div class="container-fluid">
    <?= AdminHelper::renderStatusMessages($actionError, $actionSuccess) ?>
    <?= AdminHelper::renderPageTitle('Data', '', '
        <p>Data are records with fixed fields, like seminars, events or team members. They are no pages, a page shows them with a block. Each entry is a Markdown file in <code>site/data/&lt;collection&gt;/</code>, its fields are the frontmatter, a field <code>body</code> is the text below it.</p>
        <h6>Collections</h6>
        <ul>
            <li>The list on the left shows the collections. Each one has a blueprint in <code>site/blueprints/</code> that names its fields, their types and which are required. Blueprints are written by the developer of the site, see the README section "Data".</li>
            <li>The table shows all entries of a collection. A click on a row opens the entry.</li>
        </ul>
        <h6>Entries</h6>
        <ul>
            <li>Fields marked with <span class="text-danger">*</span> are required. An empty field takes its default, shown in grey.</li>
            <li>An entry whose fields do not fit the blueprint is marked in the table and is not shown on the website until it is fixed.</li>
            <li><em>Draft</em> keeps an entry off the website.</li>
            <li>Every save and delete keeps the previous version in <code>local/history/data/</code>.</li>
            <li>The file name is made from the first date and text field when the entry is created, and stays the same afterwards.</li>
            <li>On a multilingual site the switcher above the form shows the text fields of one language, the other fields are the same in all of them. A field of a language without a value has a dashed border, the website shows the default language there. The table shows the default language.</li>
        </ul>
    ', $titleExtra) ?>
    <?php if (!$blueprints) { ?>
        <div class="card max-width-lg">
            <div class="card-body">
                <p>There are no collections yet. A collection needs a blueprint in <code>site/blueprints/&lt;name&gt;.yml</code> that names its fields:</p>
                <pre class="mb-3">title: Seminars
sort: date
fields:
  title: {type: text, required: true}
  date:  {type: date, required: true}
  body:  {type: markdown, label: Description}</pre>
                <p class="mb-0">The entries then go into <code>site/data/&lt;name&gt;/</code>, one Markdown file each. See the README section "Data".</p>
            </div>
        </div>
    <?php } else { ?>
    <div class="row gx-2">
        <div class="col-md-3 col-xl-2 col-sidebar order-md-0 order-1 mt-4 mt-md-0">
            <nav class="nav nav-compact flex-column data-collections">
                <?php foreach ($blueprints as $name => $collectionBlueprint) {
                    $count = count(glob($dataDir . "/" . $name . "/*.md") ?: []); ?>
                    <a class="page-tree-file<?= $name === $collectionName ? " active" : "" ?>" href="data?collection=<?= urlencode($name) ?>">
                        <?= AdminHelper::icon('folder') ?><span class="flex-grow-1"><?= htmlspecialchars($collectionBlueprint->getTitle()) ?></span>
                        <span class="data-count"><?= $count ?></span>
                    </a>
                <?php } ?>
            </nav>
        </div>
        <div class="col-12 col-md-9 col-xl-10 order-md-1 order-0">
            <?php if (!$blueprint) { ?>
                <p class="text-danger">There is no collection '<?= htmlspecialchars($collectionName) ?>'.</p>
            <?php } else if ($entry || $isNew) {
                [$formValues, $formBody] = $entry ? $handler->read($entry->getSlug()) : [[], ""];
                if (array_key_exists("body", $blueprint->getFields())) {
                    $formValues["body"] = $formBody;
                }
                $errors = $entry ? $entry->getErrors() : [];
                ?>
                <script>document.title = <?= json_encode(($entryTitle ?? "New entry") . ' – Reboot CMS Admin') ?>;</script>
                <p class="data-back"><a href="data?collection=<?= urlencode($collectionName) ?>"><?= AdminHelper::icon('chevron-left') ?> <?= htmlspecialchars($blueprint->getTitle()) ?></a></p>
                <h2 class="h4 mb-3"><?= $entry ? htmlspecialchars($entryTitle) : "New entry" ?></h2>
                <?php if ($errors) { ?>
                    <div class="alert alert-warning max-width-lg">This entry is not shown on the website until the marked fields are fixed.</div>
                <?php } ?>
                <?php if ($editLanguages) { ?>
                    <style><?php foreach ($editLanguages as $language) { ?>.data-form[data-edit-lang="<?= $language ?>"] .field-language:not([data-lang="<?= $language ?>"]){display:none}<?php } ?></style>
                    <?= LanguageSwitch::render($editLanguages, $defaultSite->getDefaultLanguage(), null) ?>
                <?php } ?>
                <form method="post" class="data-form max-width-lg" id="data-form" data-field-form<?= $editLanguages ? ' data-edit-lang="' . htmlspecialchars($defaultSite->getDefaultLanguage()) . '" data-default-lang="' . htmlspecialchars($defaultSite->getDefaultLanguage()) . '"' : '' ?>
                      action="data?collection=<?= urlencode($collectionName) ?><?= $entry ? "&entry=" . urlencode($entry->getSlug()) : "&new=1" ?>">
                    <input type="hidden" name="csrf_token" value="<?= CsrfProtection::getToken() ?>">
                    <input type="hidden" name="action" value="save" id="data-action">
                    <?= FieldForm::render($blueprint, $formValues, $errors) ?>
                    <div class="mb-4 form-check form-switch">
                        <input type="hidden" name="draft" value="0">
                        <input class="form-check-input" type="checkbox" role="switch" id="field-draft" name="draft" value="1"<?= $entry && $entry->isDraft() ? " checked" : "" ?>>
                        <label class="form-check-label" for="field-draft">Draft, not shown on the website</label>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <button type="submit" class="btn btn-sm btn-primary px-3" id="save-button">Save<span class="unsaved-dot d-none" aria-hidden="true" data-save-dot></span></button>
                        <a class="btn btn-sm btn-outline-secondary" href="data?collection=<?= urlencode($collectionName) ?>">Close</a>
                        <?php if ($entry) { ?>
                            <button type="button" class="btn btn-sm btn-outline-danger ms-auto" id="delete-button">Delete</button>
                        <?php } ?>
                    </div>
                </form>
            <?php } else { ?>
                <div class="d-flex align-items-center gap-3 mb-3">
                    <h2 class="h4 mb-0"><?= htmlspecialchars($blueprint->getTitle()) ?></h2>
                    <span class="text-body-secondary"><?= count($collection) ?> <?= count($collection) === 1 ? "entry" : "entries" ?></span>
                    <a class="btn btn-sm btn-primary ms-auto" href="data?collection=<?= urlencode($collectionName) ?>&new=1">New entry</a>
                </div>
                <?php if (!count($collection)) { ?>
                    <p class="text-body-secondary">No entries yet. They are Markdown files in <code>site/data/<?= htmlspecialchars($collectionName) ?>/</code>.</p>
                <?php } else {
                    $fields = $blueprint->getFields(); ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-sm data-table align-middle">
                            <thead>
                            <tr>
                                <?php foreach ($blueprint->getColumns() as $column) { ?>
                                    <th><?= htmlspecialchars($fields[$column]["label"]) ?></th>
                                <?php } ?>
                                <th></th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($collection as $row) {
                                $href = "data?collection=" . urlencode($collectionName) . "&entry=" . urlencode($row->getSlug()); ?>
                                <tr data-href="<?= htmlspecialchars($href) ?>" class="<?= $row->isDraft() ? "data-draft" : "" ?>">
                                    <?php foreach ($blueprint->getColumns() as $i => $column) { ?>
                                        <td><?php if ($i === 0) { ?><a href="<?= htmlspecialchars($href) ?>"><?= dataCell($row, $column, $fields[$column]) ?: htmlspecialchars($row->getSlug()) ?></a><?php } else { ?><?= dataCell($row, $column, $fields[$column]) ?><?php } ?></td>
                                    <?php } ?>
                                    <td class="text-end text-nowrap">
                                        <?php if ($row->isDraft()) { ?><span class="badge text-bg-secondary">Draft</span><?php } ?>
                                        <?php if (!$row->isValid()) { ?><span class="badge text-bg-warning" title="<?= htmlspecialchars(implode("\n", $row->getErrors())) ?>">Not valid</span><?php } ?>
                                    </td>
                                </tr>
                            <?php } ?>
                            </tbody>
                        </table>
                    </div>
                <?php } ?>
            <?php } ?>
        </div>
    </div>
    <?php } ?>
</div>
<?php if ($canonicalUrl) { ?>
<script>history.replaceState(null, "", <?= json_encode($canonicalUrl) ?>)</script>
<?php } ?>
<script type="module" src="assets/field-form.js?v=<?= @filemtime($reboot->getBaseFsPath() . "/web/admin/assets/field-form.js") ?>"></script>
<script>
    // a click anywhere in a row of the table opens the entry
    document.querySelectorAll(".data-table tr[data-href]").forEach(function (row) {
        row.addEventListener("click", function (event) {
            if (!event.target.closest("a")) location.href = row.dataset.href
        })
    })
    var deleteButton = document.getElementById("delete-button")
    if (deleteButton) {
        deleteButton.addEventListener("click", function () {
            if (!confirm("Delete this entry? The last version is kept in the history.")) return
            var form = document.getElementById("data-form")
            document.getElementById("data-action").value = "delete"
            if (form.clean) form.clean()
            form.noValidate = true
            form.submit()
        })
    }
</script>
