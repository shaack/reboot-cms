<?php
/** @var \Shaack\Reboot\Reboot $reboot */
/** @var \Shaack\Reboot\Site $site */
/** @var \Shaack\Reboot\Request $request */

use Shaack\Reboot\CsrfProtection;
use Shaack\Reboot\Updater;

$updater = new Updater($reboot->getBaseFsPath());
$localVersion = $updater->getLocalVersion() ?? "unknown";
$error = null;
$success = null;
$remoteVersion = null;

$action = $request->getParam("action");
if ($action === "update") {
    CsrfProtection::validate($request);
    try {
        $updater->update();
        $success = "Update complete. Please reload the page.";
        $localVersion = $updater->getLocalVersion() ?? "unknown";
    } catch (\Exception $e) {
        $error = $e->getMessage();
    }
} else {
    $remoteVersion = $updater->getRemoteVersion();
}
?>

<div class="container-fluid">
    <h1>Update</h1>

    <?php if ($error) { ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php } ?>
    <?php if ($success) { ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php } ?>

    <table class="table" style="max-width: 400px">
        <tr>
            <td>Installed version</td>
            <td><strong><?= htmlspecialchars($localVersion) ?></strong></td>
        </tr>
        <?php if ($remoteVersion !== null) { ?>
            <tr>
                <td>Available version</td>
                <td><strong><?= htmlspecialchars($remoteVersion) ?></strong></td>
            </tr>
        <?php } ?>
    </table>

    <?php if ($remoteVersion !== null && $remoteVersion !== $localVersion) { ?>
        <form method="post" action="update"
              onsubmit="return confirm('Update Reboot CMS to version <?= htmlspecialchars($remoteVersion, ENT_QUOTES) ?>. To be safe, you should make a backup of the project folder first. This will replace core/, web/admin/ and vendor/.')">
            <input type="hidden" name="csrf_token" value="<?= CsrfProtection::getToken() ?>">
            <input type="hidden" name="action" value="update">
            <button class="btn btn-primary">Update to <?= htmlspecialchars($remoteVersion) ?></button>
        </form>
    <?php } elseif ($remoteVersion === null && !$success) { ?>
        <p class="text-muted">Could not check for updates. Please verify your internet connection.</p>
    <?php } elseif (!$success) { ?>
        <p class="text-muted">You are running the latest version.</p>
    <?php } ?>
</div>
