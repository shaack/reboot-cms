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

$allowedBranches = ["distrib", "master"];
$branch = $request->getParam("branch") ?? "distrib";
if (!in_array($branch, $allowedBranches, true)) {
    $branch = "distrib";
}
$isMaster = ($branch === "master");

$updater = new Updater($reboot->getBaseFsPath(), "shaack/reboot-cms", $branch);
$localVersion = $updater->getLocalVersion() ?? "unknown";
$error = null;
$success = null;

// API endpoint for fetching remote version via AJAX
if ($request->getParam("check_version")) {
    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/json');
    $result = ["version" => $updater->getRemoteVersion()];
    if ($isMaster) {
        $result["remoteCommit"] = $updater->getRemoteCommitSha();
        $result["localCommit"] = $updater->getLocalCommitSha();
    }
    echo json_encode($result);
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
                    <td>Branch</td>
                    <td>
                        <select id="branch-select" class="form-select form-select-sm" style="width: auto; display: inline-block;">
                            <option value="distrib"<?= $branch === "distrib" ? " selected" : "" ?>>distrib (stable)</option>
                            <option value="main"<?= $branch === "main" ? " selected" : "" ?>>master (unstable)</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <td>Installed version</td>
                    <td><strong><?= htmlspecialchars($localVersion) ?></strong><?php
                        if ($isMaster) {
                            $localCommit = $updater->getLocalCommitSha();
                            if ($localCommit) {
                                echo ' <span class="text-muted">(commit ' . htmlspecialchars(substr($localCommit, 0, 7)) . ')</span>';
                            }
                        }
                    ?></td>
                </tr>
                <tr id="remote-version-row">
                    <td><?= $branch === "main" ? "Latest commit" : "Available version" ?></td>
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
    const branch = "<?= htmlspecialchars($branch, ENT_QUOTES) ?>"

    document.getElementById("branch-select").addEventListener("change", function () {
        window.location.href = "update?branch=" + encodeURIComponent(this.value)
    })

    fetch("update?check_version=1&branch=" + encodeURIComponent(branch))
        .then(function (r) { return r.json() })
        .then(function (data) {
            data = data || {}
            const cell = document.getElementById("remote-version-cell")
            const actions = document.getElementById("update-actions")
            if (branch === "master") {
                const remoteCommit = data.remoteCommit
                const localCommit = data.localCommit
                if (remoteCommit) {
                    const shortRemote = remoteCommit.substring(0, 7)
                    const shortLocal = localCommit ? localCommit.substring(0, 7) : null
                    const isUpToDate = localCommit && remoteCommit.startsWith(localCommit)
                    cell.innerHTML = '<strong>' + shortRemote + '</strong>'
                    if (isUpToDate) {
                        actions.innerHTML = '<p class="text-muted mb-0">You are running the latest commit (' + shortLocal + ').</p>'
                    } else {
                        const installedInfo = shortLocal ? ' (installed: ' + shortLocal + ')' : ''
                        actions.innerHTML =
                            '<form method="post" action="update?branch=' + encodeURIComponent(branch) + '" onsubmit="return confirm(\'Update Reboot CMS from branch master (unstable) to commit ' + shortRemote + '.' + installedInfo + ' To be safe, you should make a backup of the project folder first. This will replace core/, web/admin/ and vendor/.\')">' +
                            '<input type="hidden" name="csrf_token" value="' + csrfToken + '">' +
                            '<input type="hidden" name="action" value="update">' +
                            '<button class="btn btn-sm btn-primary">Update to ' + shortRemote + '</button>' +
                            '</form>'
                    }
                } else {
                    cell.innerHTML = '<span class="text-muted">unavailable</span>'
                    actions.innerHTML = '<p class="text-muted mb-0">Could not check for updates. Please verify your internet connection.</p>'
                }
            } else if (data.version) {
                const version = document.createElement("strong")
                version.textContent = data.version
                cell.innerHTML = ""
                cell.appendChild(version)
                if (data.version.localeCompare(localVersion, undefined, {numeric: true, sensitivity: 'base'}) > 0) {
                    const safeVersion = data.version.replace(/[<>"'&]/g, '')
                    actions.innerHTML =
                        '<form method="post" action="update?branch=' + encodeURIComponent(branch) + '" onsubmit="return confirm(\'Update Reboot CMS to version ' + safeVersion + '. To be safe, you should make a backup of the project folder first. This will replace core/, web/admin/ and vendor/.\')">' +
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
