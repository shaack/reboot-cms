<?php
/**
 * Converts a Markdown page into a structured page, included by the page Pages
 * for `pages?page=/about.md&convert=1`. It shows first what becomes of each
 * block and the YAML, the conversion itself is a POST. The Markdown file goes
 * into the history, the YAML file takes its place.
 *
 * @var \Shaack\Reboot\Site $defaultSite
 * @var \Shaack\Reboot\Request $request
 * @var string $fullPath the Markdown file
 * @var string $editPageName like `/about.md`
 * @var string $pagesDir
 * @var string $historyDir
 * @var int $historyMaxVersions
 */

use Shaack\Logger;
use Shaack\Reboot\AddOns\Admin\FieldInput;
use Shaack\Reboot\AddOns\Admin\PageConverter;
use Shaack\Reboot\AddOns\Admin\PageHistoryHelper;
use Shaack\Reboot\Blueprint;
use Shaack\Reboot\CsrfProtection;
use Shaack\Reboot\YamlWriter;
use Shaack\Utils\FileSystemUtils;

$convertTarget = preg_replace('/\.md$/', '.yml', $fullPath);
$convertTargetName = preg_replace('/\.md$/', '.yml', $editPageName);
$conversion = (new PageConverter($defaultSite))->convert((string)file_get_contents($fullPath));
$convertYaml = YamlWriter::dump(FieldInput::clean(Blueprint::forPage($defaultSite), $conversion["values"]));
$convertError = is_file($convertTarget) ? "There is already a structured page " . ltrim($convertTargetName, "/") . "." : null;

if (!$convertError && $request->getParam("convert_confirm", "post") !== null) {
    try {
        CsrfProtection::validate($request);
        FileSystemUtils::writeAtomic($convertTarget, $convertYaml);
        PageHistoryHelper::savePageSnapshot($fullPath, $pagesDir, $historyDir, $historyMaxVersions);
        unlink($fullPath);
        Logger::info("Converted " . $editPageName . " to a structured page");
        ?>
        <script>location.replace(<?= json_encode("pages?page=" . rawurlencode($convertTargetName) . "&converted=1") ?>)</script>
        <?php
        return;
    } catch (\RuntimeException $e) {
        $convertError = $e->getMessage();
    }
}
$formCount = count(array_filter($conversion["report"], fn($row) => $row["to"] !== null));
?>
<script>document.title = <?= json_encode('Convert ' . basename($editPageName, '.md') . ' – Reboot CMS Admin') ?>;</script>
<div class="convert-page">
    <h2 class="h4">Convert to a structured page</h2>
    <p>
        <code><?= htmlspecialchars(ltrim($editPageName, "/")) ?></code> becomes <code><?= htmlspecialchars(ltrim($convertTargetName, "/")) ?></code>, the address stays the same.
        <?= $formCount ?> of <?= count($conversion["report"]) ?> blocks become forms, the others keep their Markdown and look as before.
        The Markdown file is kept in the history.
    </p>
    <?php if ($convertError) { ?>
        <div class="alert alert-danger"><?= htmlspecialchars($convertError) ?></div>
    <?php } ?>
    <table class="table table-sm convert-report">
        <thead><tr><th>Block</th><th>Becomes</th></tr></thead>
        <tbody>
        <?php foreach ($conversion["report"] as $row) { ?>
            <tr>
                <td><code><?= htmlspecialchars($row["from"]) ?></code></td>
                <td><?php if ($row["to"]) { ?>
                        the form block <code><?= htmlspecialchars($row["to"]) ?></code>
                    <?php } else { ?>
                        <span class="text-body-secondary">a Markdown block, as before</span>
                    <?php } ?></td>
            </tr>
        <?php } ?>
        </tbody>
    </table>
    <?php foreach ($conversion["notes"] as $note) { ?>
        <p class="text-warning"><?= htmlspecialchars($note) ?></p>
    <?php } ?>
    <details class="mb-3">
        <summary>The YAML of the structured page</summary>
        <pre class="convert-yaml editor-font"><?= htmlspecialchars($convertYaml) ?></pre>
    </details>
    <form method="post" action="pages?page=<?= urlencode($editPageName) ?>&amp;convert=1" class="d-flex gap-2">
        <input type="hidden" name="csrf_token" value="<?= CsrfProtection::getToken() ?>">
        <input type="hidden" name="convert_confirm" value="1">
        <button type="submit" class="btn btn-sm btn-primary px-3"<?= $convertError ? " disabled" : "" ?>>Convert</button>
        <a class="btn btn-sm btn-outline-secondary" href="pages?page=<?= urlencode($editPageName) ?>">Cancel</a>
    </form>
</div>
