<?php
/**
 * Backups of the content: `site/` and the uploaded files in `web/media/`.
 * The archives live in `local/backups/`, outside the document root, and are
 * handed out through this page, which requires an admin login.
 */

/** @var \Shaack\Reboot\Reboot $reboot */
/** @var \Shaack\Reboot\Site $site */
/** @var \Shaack\Reboot\Request $request */

use Shaack\Reboot\AddOns\Admin\AdminHelper;
use Shaack\Reboot\AddOns\Admin\BackupHelper;
use Shaack\Reboot\CsrfProtection;

if (!AdminHelper::requireAdmin($site, $reboot)) return;

$error = null;
$success = null;

// Download first, it answers with the file instead of a page
if ($request->getParam("download", "get")) {
    try {
        BackupHelper::send($reboot, (string)$request->getParam("download", "get"));
    } catch (\Throwable $e) {
        $error = $e->getMessage();
    }
}

$action = $request->getParam("action");
if ($action) {
    $result = AdminHelper::handleAction($request, function () use ($action, $request, $reboot) {
        if ($action === "create") {
            // Packing can take a moment on a large site
            @set_time_limit(300);
            $host = (string)($_SERVER['HTTP_HOST'] ?? 'site');
            $name = BackupHelper::create($reboot, $host);
            return "Backup created: $name";
        }
        if ($action === "delete") {
            $name = BackupHelper::delete($reboot, (string)$request->getParam("name"));
            return "Backup deleted: $name";
        }
        throw new \RuntimeException("Unknown action");
    });
    $error = $result['error'];
    $success = $result['success'];
}

try {
    $backups = BackupHelper::all($reboot);
} catch (\Throwable $e) {
    $backups = [];
    $error = $error ?? $e->getMessage();
}
$zipAvailable = class_exists(\ZipArchive::class);
?>

<div class="container-fluid max-width-lg">
    <?= AdminHelper::renderStatusMessages($error, $success) ?>

    <div class="card mb-4">
        <div class="card-header d-flex flex-wrap align-items-center gap-2">
            <h5 class="mb-0 me-auto">Backup</h5>
            <form method="post" action="backup">
                <input type="hidden" name="csrf_token" value="<?= CsrfProtection::getToken() ?>">
                <input type="hidden" name="action" value="create">
                <button class="btn btn-sm btn-primary" <?= $zipAvailable ? '' : 'disabled' ?>>Create backup</button>
            </form>
        </div>
        <div class="card-body">
            <p class="mb-0 text-body-secondary">
                An archive holds the content of this site, that is <code>site/</code> with pages, blocks and
                configuration, plus the uploaded files in <code>web/media/</code>. The CMS itself and the local
                configuration with the user accounts are not included, they come from the release and stay on
                the server. The archives are stored in <code>local/backups/</code> and are not reachable over
                the web.
            </p>
            <?php if (!$zipAvailable) { ?>
                <p class="alert alert-warning mt-3 mb-0">
                    The PHP extension <code>zip</code> is missing, so no archive can be created.
                    Enable <code>extension=zip</code> in the php.ini.
                </p>
            <?php } ?>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h5 class="mb-0">Archives</h5></div>
        <?php if (empty($backups)) { ?>
            <div class="card-body">
                <p class="mb-0 text-body-secondary">No backups yet.</p>
            </div>
        <?php } else { ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                    <tr>
                        <th>Name</th>
                        <th style="width: 120px">Size</th>
                        <th style="width: 180px">Created</th>
                        <th style="width: 180px"></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($backups as $backup) { ?>
                        <tr>
                            <td><?= htmlspecialchars($backup['name']) ?></td>
                            <td class="text-body-secondary"><?= BackupHelper::formatFileSize($backup['size']) ?></td>
                            <td class="text-body-secondary"><?= date("Y-m-d H:i", $backup['modified']) ?></td>
                            <td>
                                <div class="d-flex gap-1 justify-content-end">
                                    <a class="btn btn-sm btn-outline-secondary"
                                       href="backup?download=<?= urlencode($backup['name']) ?>">Download</a>
                                    <form method="post" action="backup"
                                          onsubmit="return confirm('Delete \'<?= htmlspecialchars($backup['name'], ENT_QUOTES) ?>\'?')">
                                        <input type="hidden" name="csrf_token" value="<?= CsrfProtection::getToken() ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="name" value="<?= htmlspecialchars($backup['name']) ?>">
                                        <button class="btn btn-sm btn-outline-danger">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
        <?php } ?>
    </div>
</div>
