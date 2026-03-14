<?php
/** @var \Shaack\Reboot\Reboot $reboot */
/** @var \Shaack\Reboot\Site $site */
/** @var \Shaack\Reboot\Request $request */

use Shaack\Reboot\Authentication;
use Shaack\Reboot\CsrfProtection;
use Shaack\Reboot\Updater;

/** @var Authentication $authentication */
$authentication = $site->getAddOn("Authentication");
if (!$authentication->isAdmin()) {
    $reboot->redirect($site->getWebPath() . "/pages");
    return;
}

$updater = new Updater($reboot->getBaseFsPath());
$localVersion = $updater->getLocalVersion() ?? "unknown";
$error = null;
$success = null;

// API endpoint for fetching remote version via AJAX
if ($request->getParam("check_version")) {
    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/json');
    $remoteVersion = $updater->getRemoteVersion();
    echo json_encode(["version" => $remoteVersion]);
    exit;
}

$action = $request->getParam("action");
if ($action === "update") {
    CsrfProtection::validate($request);
    try {
        $updater->update();
        $success = "Update complete.";
        $localVersion = $updater->getLocalVersion() ?? "unknown";
    } catch (\Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<div class="container-fluid max-width-lg">
    <?php if ($error) { ?>
        <script>statusMessage("<?= htmlspecialchars($error, ENT_QUOTES) ?>", "text-bg-danger")</script>
    <?php } ?>
    <?php if ($success) { ?>
        <script>statusMessage("<?= htmlspecialchars($success, ENT_QUOTES) ?>")</script>
    <?php } ?>

    <div class="card">
        <div class="card-header"><h5 class="mb-0">Reboot CMS Update</h5></div>
        <div class="card-body">
            <table class="table table-borderless mb-3 max-width-md">
                <tr>
                    <td>Installed version</td>
                    <td><strong><?= htmlspecialchars($localVersion) ?></strong></td>
                </tr>
                <tr id="remote-version-row">
                    <td>Available version</td>
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
document.addEventListener("DOMContentLoaded", function () {
    const localVersion = "<?= htmlspecialchars($localVersion, ENT_QUOTES) ?>"
    const csrfToken = "<?= CsrfProtection::getToken() ?>"
    fetch("update?check_version=1")
        .then(function (r) { return r.json() })
        .then(function (data) {
            const cell = document.getElementById("remote-version-cell")
            const actions = document.getElementById("update-actions")
            if (data.version) {
                const version = document.createElement("strong")
                version.textContent = data.version
                cell.innerHTML = ""
                cell.appendChild(version)
                if (data.version !== localVersion) {
                    const safeVersion = data.version.replace(/[<>"'&]/g, '')
                    actions.innerHTML =
                        '<form method="post" action="update" onsubmit="return confirm(\'Update Reboot CMS to version ' + safeVersion + '. To be safe, you should make a backup of the project folder first. This will replace core/, web/admin/ and vendor/.\')">' +
                        '<input type="hidden" name="csrf_token" value="' + csrfToken + '">' +
                        '<input type="hidden" name="action" value="update">' +
                        '<button class="btn btn-sm btn-primary">Update to ' + safeVersion + '</button>' +
                        '</form>'
                } else {
                    actions.innerHTML = '<p class="text-muted mb-0">You are running the latest version.</p>'
                }
            } else {
                cell.innerHTML = '<span class="text-muted">unavailable</span>'
                actions.innerHTML = '<p class="text-muted mb-0">Could not check for updates. Please verify your internet connection.</p>'
            }
        })
        .catch(function () {
            document.getElementById("remote-version-cell").innerHTML = '<span class="text-muted">unavailable</span>'
            document.getElementById("update-actions").innerHTML = '<p class="text-muted mb-0">Could not check for updates. Please verify your internet connection.</p>'
        })
})
</script>
<?php } ?>
