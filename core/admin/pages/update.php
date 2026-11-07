<?php
/** @var \Shaack\Reboot\Reboot $reboot */
/** @var \Shaack\Reboot\Site $site */
/** @var \Shaack\Reboot\Request $request */

use Shaack\Reboot\AddOns\Admin\AdminHelper;
use Shaack\Reboot\CsrfProtection;
use Shaack\Reboot\Updater;

if (!AdminHelper::requireAdmin($site, $reboot)) return;

// the branches of the installed major version, a major update is never installed here
$branches = Updater::branches((new Updater($reboot->getBaseFsPath()))->getLocalVersion());
$branch = $request->getParam("branch") ?? $branches["stable"];
if (!in_array($branch, $branches, true)) {
    $branch = $branches["stable"];
}
$isMain = ($branch === $branches["unstable"]);

$updater = new Updater($reboot->getBaseFsPath(), "shaack/reboot-cms", $branch);
$localVersion = $updater->getLocalVersion() ?? "unknown";
$error = null;
$success = null;

// API endpoint for fetching remote version via AJAX
if ($request->getParam("check_version")) {
    require __DIR__ . "/../api/update-check.php";
    return;
}

if ($request->getParam("action") === "update") {
    $result = AdminHelper::handleAction($request, function() use ($updater, &$localVersion) {
        $updater->update();
        $localVersion = $updater->getLocalVersion() ?? "unknown";
        return "Update complete.";
    });
    $error = $result['error'];
    $success = $result['success'];
}
?>

<div class="container-fluid max-width-lg">
    <?= AdminHelper::renderStatusMessages($error, $success) ?>
    <div id="major-notice"></div>

    <div class="card">
        <div class="card-header"><h5 class="mb-0">Reboot CMS Update</h5></div>
        <div class="card-body">
            <table class="table table-borderless mb-3 max-width-md">
                <tr>
                    <td>Branch</td>
                    <td>
                        <select id="branch-select" class="form-select form-select-sm" style="width: auto; display: inline-block;">
                            <option value="<?= htmlspecialchars($branches["stable"]) ?>"<?= !$isMain ? " selected" : "" ?>><?= htmlspecialchars($branches["stable"]) ?> (stable)</option>
                            <option value="<?= htmlspecialchars($branches["unstable"]) ?>"<?= $isMain ? " selected" : "" ?>><?= htmlspecialchars($branches["unstable"]) ?> (unstable)</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <td>Installed version</td>
                    <td><strong><?= htmlspecialchars($localVersion) ?></strong><?php
                        if ($isMain) {
                            $localCommit = $updater->getLocalCommitSha();
                            if ($localCommit) {
                                echo ' <span class="text-muted">(commit ' . htmlspecialchars(substr($localCommit, 0, 7)) . ')</span>';
                            }
                        }
                    ?></td>
                </tr>
                <tr id="remote-version-row">
                    <td><?= $isMain ? "Latest commit" : "Available version" ?></td>
                    <td id="remote-version-cell">
                        <div class="spinner-border spinner-border-sm text-secondary" role="status">
                            <span class="visually-hidden">Checking...</span>
                        </div>
                    </td>
                </tr>
            </table>

            <div id="update-actions"></div>
            <?php if ($success) { ?>
                <script>setTimeout(function() { window.location.href = "update"; }, 1000)</script>
            <?php } ?>
        </div>
    </div>
</div>

<?php if (!$success) { ?>
<script>
window.updateConfig = {
    localVersion: <?= json_encode($localVersion) ?>,
    csrfToken: <?= json_encode(CsrfProtection::getToken()) ?>,
    branch: <?= json_encode($branch) ?>,
    isUnstable: <?= json_encode($isMain) ?>
};
</script>
<script src="assets/update.js"></script>
<?php } ?>
