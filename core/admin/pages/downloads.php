<?php
/** @var \Shaack\Reboot\Reboot $reboot */
/** @var \Shaack\Reboot\Site $site */
/** @var \Shaack\Reboot\Request $request */
/** @var Shaack\Reboot\Admin $admin */
$admin = $site->getAddOn("Admin");

use Shaack\Reboot\Admin\AdminHelper;
use Shaack\Reboot\CsrfProtection;
use Shaack\Reboot\Downloads\DownloadStore;

if (!AdminHelper::requireAdmin($site, $reboot)) return;

$defaultSite = $admin->getDefaultSite();
$siteConfig = $defaultSite->getConfig();
$downloadsConfig = $siteConfig['downloads'] ?? [];
$addonEnabled = in_array('Downloads', $siteConfig['addons'] ?? [], true);
$linkPrefix = '/' . trim((string)($downloadsConfig['path'] ?? '/downloads'), '/');

$store = new DownloadStore($reboot->getBaseFsPath(), $downloadsConfig);
$store->purge();

$error = null;
$success = null;

// A POST larger than post_max_size arrives with empty $_POST, which would only
// show up as a failing CSRF check. Name the real cause instead.
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && empty($_POST) && empty($_FILES)) {
    $error = "Upload too large. The server accepts at most " . ini_get('post_max_size')
        . " per request (post_max_size) and " . ini_get('upload_max_filesize') . " per file (upload_max_filesize).";
}

$action = $error ? null : $request->getParam("action");
if ($action) {
    $result = AdminHelper::handleAction($request, function () use ($action, $request, $store) {
        if ($action === "upload") {
            if (!isset($_FILES["files"])) {
                throw new \InvalidArgumentException("No file selected");
            }
            $label = $request->getParam("label");
            $files = $_FILES["files"];
            $added = 0;
            $failed = [];
            for ($i = 0; $i < count($files["name"]); $i++) {
                $name = (string)$files["name"][$i];
                if ($files["error"][$i] !== UPLOAD_ERR_OK) {
                    if ($files["error"][$i] !== UPLOAD_ERR_NO_FILE) {
                        $failed[] = $name;
                    }
                    continue;
                }
                $tmpName = $files["tmp_name"][$i];
                try {
                    $store->add(function (string $target) use ($tmpName) {
                        return move_uploaded_file($tmpName, $target);
                    }, $name, is_string($label) ? $label : null);
                    $added++;
                } catch (\Exception $e) {
                    $failed[] = $name;
                }
            }
            return [
                'success' => $added > 0 ? "$added file(s) uploaded" : null,
                'error' => !empty($failed) ? "Upload failed: " . implode(", ", $failed) : null,
            ];
        } elseif ($action === "delete") {
            $token = (string)$request->getParam("token");
            if (!$store->delete($token)) {
                throw new \InvalidArgumentException("Unknown download");
            }
            return "Download deleted";
        }
        return null;
    });
    $error = $result['error'];
    $success = $result['success'];
}

$entries = $store->getEntries();
$scheme = (($_SERVER['HTTPS'] ?? '') && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$baseUrl = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $reboot->getBaseWebPath();

?>
<div class="container-fluid max-width-xxl">
    <?= AdminHelper::renderStatusMessages($error, $success) ?>

    <?php if (!$addonEnabled) { ?>
        <div class="alert alert-warning">
            The <code>Downloads</code> addon is not active, so the links below do not work yet.
            Add it to the <code>addons</code> list in
            <a href="config" class="alert-link">Site Configuration</a>:
            <code>addons: [ Downloads ]</code>
        </div>
    <?php } ?>

    <form method="post" action="downloads" enctype="multipart/form-data" class="card mb-3">
        <div class="card-body d-flex flex-wrap align-items-center gap-2">
            <input type="hidden" name="csrf_token" value="<?= CsrfProtection::getToken() ?>">
            <input type="hidden" name="action" value="upload">
            <input type="file" name="files[]" multiple required class="form-control form-control-sm"
                   style="max-width: 360px">
            <input type="text" name="label" class="form-control form-control-sm" style="max-width: 260px"
                   placeholder="Note for the recipient (optional)">
            <button class="btn btn-sm btn-primary text-nowrap">Upload</button>
            <span class="text-body-secondary small ms-auto">
                max. <?= htmlspecialchars(ini_get('upload_max_filesize')) ?> per file,
                <?= htmlspecialchars(ini_get('post_max_size')) ?> per upload
            </span>
        </div>
    </form>

    <?php if (empty($entries)) { ?>
        <p class="text-body-secondary">No downloads yet. Upload a file to create a protected link.</p>
    <?php } else { ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                <tr>
                    <th>File</th>
                    <th style="width: 90px">Size</th>
                    <th style="width: 110px">Password</th>
                    <th>Link</th>
                    <th style="width: 150px">Expires</th>
                    <th style="width: 220px">Downloads</th>
                    <th style="width: 80px"></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($entries as $entry) {
                    $expired = $store->isExpired($entry);
                    $locked = $store->isLocked($entry);
                    $url = $baseUrl . DownloadStore::buildLinkPath($linkPrefix, $entry);
                    $downloadCount = count($entry['downloads'] ?? []);
                    ?>
                    <tr class="<?= $expired || $locked ? 'text-body-secondary' : '' ?>">
                        <td>
                            <span class="<?= $expired || $locked ? '' : 'fw-semibold' ?>">
                                <?= htmlspecialchars($entry['fileName']) ?>
                            </span>
                            <?php if ($entry['label']) { ?>
                                <div class="small text-body-secondary"><?= htmlspecialchars($entry['label']) ?></div>
                            <?php } ?>
                            <?php if ($locked) { ?>
                                <span class="badge text-bg-danger">locked</span>
                            <?php } elseif ($expired) { ?>
                                <span class="badge text-bg-secondary">expired</span>
                            <?php } elseif ($downloadCount > 0) { ?>
                                <span class="badge text-bg-warning">downloaded</span>
                            <?php } else { ?>
                                <span class="badge text-bg-success">active</span>
                            <?php } ?>
                        </td>
                        <td><?= DownloadStore::formatSize((int)$entry['size']) ?></td>
                        <td>
                            <?php if ($expired) { ?>
                                &mdash;
                            <?php } else { ?>
                                <span class="font-monospace"><?= htmlspecialchars($entry['password']) ?></span>
                                <button type="button" class="btn btn-sm btn-link p-0 ms-1 copy-button"
                                        data-copy="<?= htmlspecialchars($entry['password']) ?>" title="Copy password">copy</button>
                            <?php } ?>
                        </td>
                        <td class="text-break">
                            <?php if ($expired) { ?>
                                <span class="font-monospace small text-decoration-line-through"><?= htmlspecialchars($url) ?></span>
                            <?php } else { ?>
                                <span class="font-monospace small"><?= htmlspecialchars($url) ?></span>
                                <button type="button" class="btn btn-sm btn-link p-0 ms-1 copy-button"
                                        data-copy="<?= htmlspecialchars($url) ?>" title="Copy link">copy</button>
                            <?php } ?>
                        </td>
                        <td class="small">
                            <?= date("Y-m-d H:i", $store->getExpiresAt($entry)) ?>
                            <div class="text-body-secondary">
                                <?php if ($locked) { ?>
                                    <?= (int)$entry['failedAttempts'] ?> wrong passwords
                                <?php } elseif (!empty($entry['firstDownloadAt'])) { ?>
                                    1 hour after first download
                                <?php } else { ?>
                                    uploaded <?= date("Y-m-d H:i", (int)$entry['createdAt']) ?>
                                <?php } ?>
                            </div>
                        </td>
                        <td class="small">
                            <?php if ($downloadCount === 0) { ?>
                                <span class="text-body-secondary">not downloaded</span>
                            <?php } else { ?>
                                <?php foreach (array_slice($entry['downloads'], -3) as $download) { ?>
                                    <div>
                                        <?= date("Y-m-d H:i", (int)$download['time']) ?>
                                        <span class="text-body-secondary"><?= htmlspecialchars($download['ip']) ?></span>
                                    </div>
                                <?php } ?>
                                <?php if ($downloadCount > 3) { ?>
                                    <div class="text-body-secondary"><?= $downloadCount ?> downloads in total</div>
                                <?php } ?>
                            <?php } ?>
                        </td>
                        <td>
                            <form method="post" action="downloads"
                                  onsubmit="return confirm('Delete \'<?= htmlspecialchars($entry['fileName'], ENT_QUOTES) ?>\'?')">
                                <input type="hidden" name="csrf_token" value="<?= CsrfProtection::getToken() ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="token" value="<?= htmlspecialchars($entry['token']) ?>">
                                <button class="btn btn-sm btn-outline-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    <?php } ?>
</div>
<script>
    document.querySelectorAll(".copy-button").forEach(function (button) {
        button.addEventListener("click", function () {
            var text = button.dataset.copy
            var done = function () {
                var label = button.textContent
                button.textContent = "copied"
                setTimeout(function () { button.textContent = label }, 1200)
            }
            if (navigator.clipboard) {
                navigator.clipboard.writeText(text).then(done)
            } else {
                var input = document.createElement("input")
                input.value = text
                document.body.appendChild(input)
                input.select()
                document.execCommand("copy")
                document.body.removeChild(input)
                done()
            }
        })
    })
</script>
