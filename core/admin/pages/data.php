<?php
/** @var \Shaack\Reboot\Reboot $reboot */
/** @var \Shaack\Reboot\Site $site */
/** @var \Shaack\Reboot\Request $request */
/** @var Shaack\Reboot\AddOns\Admin $admin */
$admin = $site->getAddOn("Admin");

use Shaack\Reboot\AddOns\Admin\AdminHelper;
use Shaack\Reboot\AddOns\Admin\DataEntryHandler;
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

/**
 * One field of the entry form, as the blueprint describes it.
 */
function renderDataField(string $name, array $props, $value, ?string $error): string
{
    $id = "field-" . $name;
    $inputName = "fields[" . htmlspecialchars($name) . "]";
    $label = htmlspecialchars($props["label"]) . (!empty($props["required"]) ? ' <span class="text-danger" title="Required">*</span>' : '');
    $required = !empty($props["required"]) ? " required" : "";
    $invalid = $error ? " is-invalid" : "";
    $default = $props["default"] ?? null;
    $placeholder = is_scalar($default) && !is_bool($default) ? ' placeholder="' . htmlspecialchars((string)$default) . '"' : "";
    $valueText = is_scalar($value) && !is_bool($value) ? (string)$value : "";
    $attr = ' class="form-control' . $invalid . '" id="' . $id . '" name="' . $inputName . '"' . $required;
    switch ($props["type"]) {
        case "textarea":
            $control = '<textarea' . $attr . ' rows="3"' . $placeholder . '>' . htmlspecialchars($valueText) . '</textarea>';
            break;
        case "markdown":
            $control = '<textarea class="form-control markdown editor-font' . $invalid . '" id="' . $id . '" name="' . $inputName . '" rows="10">'
                . htmlspecialchars($valueText) . '</textarea>';
            break;
        case "number":
            $control = '<input type="number" step="any"' . $attr . $placeholder
                . (isset($props["min"]) ? ' min="' . htmlspecialchars((string)$props["min"]) . '"' : '')
                . (isset($props["max"]) ? ' max="' . htmlspecialchars((string)$props["max"]) . '"' : '')
                . ' value="' . htmlspecialchars($valueText) . '">';
            break;
        case "date":
            $control = '<input type="date"' . $attr . ' value="' . htmlspecialchars($valueText) . '">';
            break;
        case "boolean":
            $checked = $value === null ? (bool)$default : (bool)$value;
            return '<div class="mb-3 form-check form-switch">'
                . '<input type="hidden" name="' . $inputName . '" value="0">'
                . '<input class="form-check-input' . $invalid . '" type="checkbox" role="switch" id="' . $id . '" name="' . $inputName . '" value="1"' . ($checked ? ' checked' : '') . '>'
                . '<label class="form-check-label" for="' . $id . '">' . $label . '</label>'
                . renderDataFieldHelp($props, $error) . '</div>';
        case "select":
            $options = '<option value="">' . (is_scalar($default) ? '(' . htmlspecialchars((string)$default) . ')' : '') . '</option>';
            foreach ((array)($props["options"] ?? []) as $option) {
                $option = (string)$option;
                $options .= '<option' . ($option === $valueText ? ' selected' : '') . '>' . htmlspecialchars($option) . '</option>';
            }
            $control = '<select class="form-select' . $invalid . '" id="' . $id . '" name="' . $inputName . '"' . $required . '>' . $options . '</select>';
            break;
        case "image":
            $control = '<div class="input-group data-image">'
                . '<input type="text"' . $attr . $placeholder . ' value="' . htmlspecialchars($valueText) . '">'
                . '<button type="button" class="btn btn-outline-secondary" data-choose-image="' . $id . '">Choose</button>'
                . '</div>'
                . '<img class="data-image-preview" alt="" data-preview-for="' . $id . '">';
            break;
        default:
            $control = '<input type="text"' . $attr . $placeholder . ' value="' . htmlspecialchars($valueText) . '">';
    }
    return '<div class="mb-3"><label class="form-label" for="' . $id . '">' . $label . '</label>'
        . $control . renderDataFieldHelp($props, $error) . '</div>';
}

function renderDataFieldHelp(array $props, ?string $error): string
{
    $html = $error ? '<div class="invalid-feedback d-block">' . htmlspecialchars($error) . '</div>' : '';
    if (!empty($props["help"])) {
        $html .= '<div class="form-text">' . htmlspecialchars((string)$props["help"]) . '</div>';
    }
    return $html;
}

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
                <form method="post" class="data-form max-width-lg" id="data-form"
                      action="data?collection=<?= urlencode($collectionName) ?><?= $entry ? "&entry=" . urlencode($entry->getSlug()) : "&new=1" ?>">
                    <input type="hidden" name="csrf_token" value="<?= CsrfProtection::getToken() ?>">
                    <input type="hidden" name="action" value="save" id="data-action">
                    <?php foreach ($blueprint->getFields() as $name => $props) {
                        echo renderDataField($name, $props, $formValues[$name] ?? null, $errors[$name] ?? null);
                    } ?>
                    <div class="mb-4 form-check form-switch">
                        <input type="hidden" name="draft" value="0">
                        <input class="form-check-input" type="checkbox" role="switch" id="field-draft" name="draft" value="1"<?= $entry && $entry->isDraft() ? " checked" : "" ?>>
                        <label class="form-check-label" for="field-draft">Draft, not shown on the website</label>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <button type="submit" class="btn btn-sm btn-primary px-3" id="save-button">Save<span class="unsaved-dot d-none" aria-hidden="true"></span></button>
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
<script type="module">
    import {InsertMedia} from "./assets/InsertMedia.js"

    // a click anywhere in a row of the table opens the entry
    document.querySelectorAll(".data-table tr[data-href]").forEach(row => {
        row.addEventListener("click", event => {
            if (!event.target.closest("a")) location.href = row.dataset.href
        })
    })

    // an image field: preview, and "Choose" opens the media browser of the editor
    document.querySelectorAll("[data-choose-image]").forEach(button => {
        const input = document.getElementById(button.dataset.chooseImage)
        const preview = document.querySelector('[data-preview-for="' + input.id + '"]')
        const update = () => {
            const src = input.value || input.placeholder
            preview.src = src || ""
            preview.hidden = !src
        }
        const picker = new InsertMedia(null, {mediaUrl: "media"})
        picker.insertImage = (webPath) => {
            input.value = webPath
            input.dispatchEvent(new Event("input", {bubbles: true}))
            update()
        }
        button.addEventListener("click", () => picker.openBrowser())
        input.addEventListener("input", update)
        update()
    })

    const form = document.getElementById("data-form")
    if (form) {
        const saveDot = document.querySelector("#save-button .unsaved-dot")
        let dirty = false
        const markDirty = () => {
            dirty = true
            saveDot.classList.remove("d-none")
        }
        form.addEventListener("input", markDirty)
        form.addEventListener("change", markDirty)
        // the Markdown editor changes its textarea without an input event on the form
        form.querySelectorAll("textarea.markdown").forEach(textarea => {
            textarea.addEventListener("input", markDirty)
        })
        form.addEventListener("submit", () => dirty = false)
        window.addEventListener("beforeunload", event => {
            if (dirty) event.preventDefault()
        })
        document.addEventListener("keydown", event => {
            if ((event.metaKey || event.ctrlKey) && event.key === "s") {
                event.preventDefault()
                form.requestSubmit()
            }
        })
        const deleteButton = document.getElementById("delete-button")
        if (deleteButton) {
            deleteButton.addEventListener("click", () => {
                if (!confirm("Delete this entry? The last version is kept in the history.")) return
                document.getElementById("data-action").value = "delete"
                dirty = false
                form.noValidate = true
                form.submit()
            })
        }
    }
</script>
