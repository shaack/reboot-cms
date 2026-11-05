<?php
/** @var \Shaack\Reboot\Reboot $reboot */
/** @var \Shaack\Reboot\Site $site */
/** @var \Shaack\Reboot\Request $request */
/** @var Shaack\Reboot\AddOns\Admin $admin */
$admin = $site->getAddOn("Admin");

use Shaack\Reboot\AddOns\Admin\AdminHelper;
use Shaack\Reboot\AddOns\Admin\MediaHelper;
use Shaack\Reboot\CsrfProtection;
use Shaack\Utils\FileSystemUtils;

$mediaDir = $reboot->getBaseFsPath() . "/web/media";
if (!is_dir($mediaDir)) {
    mkdir($mediaDir, 0755, true);
}

$error = null;
$success = null;
$currentPath = $request->getParam("path") ?? "";
$currentPath = trim($currentPath, "/");
$currentPath = str_replace("..", "", $currentPath);
$currentPath = preg_replace('#/+#', '/', $currentPath);

$fullCurrentPath = $mediaDir . ($currentPath ? "/" . $currentPath : "");
$resolvedPath = realpath($fullCurrentPath);
if (!FileSystemUtils::isInside($resolvedPath, realpath($mediaDir))) {
    $currentPath = "";
    $fullCurrentPath = $mediaDir;
    $resolvedPath = realpath($mediaDir);
}

// JSON API endpoint
if ($request->getParam("list")) {
    require __DIR__ . "/../api/media-list.php";
    return;
}

$allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'svg',
    'mp4', 'webm', 'ogg', 'mp3', 'wav',
    'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'odp',
    'txt', 'csv', 'json', 'xml', 'yml', 'yaml',
    'zip', 'gz', 'tar', 'woff', 'woff2', 'ttf', 'otf', 'eot', 'ico'];

$action = $request->getParam("action");
if ($action) {
    $result = AdminHelper::handleAction($request, function() use ($action, $request, $resolvedPath, $mediaDir, $allowedExtensions) {

        if ($action === "upload" && isset($_FILES["files"])) {
            $uploadCount = 0;
            $skipped = [];
            $active = [];
            $files = $_FILES["files"];
            for ($i = 0; $i < count($files["name"]); $i++) {
                if ($files["error"][$i] !== UPLOAD_ERR_OK) {
                    continue;
                }
                $fileName = basename($files["name"][$i]);
                $fileName = preg_replace('/[^\w\s\-.]/', '_', $fileName);
                // no dotfiles, a `.htaccess` would reconfigure the folder
                if (empty($fileName) || str_starts_with($fileName, ".")) {
                    continue;
                }
                $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                if (!in_array($ext, $allowedExtensions)) {
                    $skipped[] = $fileName;
                    continue;
                }
                if (MediaHelper::hasActiveContent($files["tmp_name"][$i], $ext)) {
                    $active[] = $fileName;
                    continue;
                }
                $targetPath = $resolvedPath . "/" . $fileName;
                if (move_uploaded_file($files["tmp_name"][$i], $targetPath)) {
                    $uploadCount++;
                }
            }
            $errors = [];
            if ($skipped) {
                $errors[] = "Blocked: " . implode(", ", $skipped) . " (extension not allowed)";
            }
            if ($active) {
                $errors[] = "Blocked: " . implode(", ", $active) . " (contains scripts or event handlers)";
            }
            return [
                'success' => $uploadCount > 0 ? "$uploadCount file(s) uploaded" : null,
                'error' => $errors ? implode(". ", $errors) : null,
            ];
        } elseif ($action === "create_folder") {
            $folderName = trim($request->getParam("folder_name") ?? "");
            if (!preg_match('/^[\w\s\-.]+$/', $folderName) || str_starts_with($folderName, ".")) {
                throw new \InvalidArgumentException("Invalid folder name");
            }
            $newFolderPath = $resolvedPath . "/" . $folderName;
            if (is_dir($newFolderPath)) {
                throw new \InvalidArgumentException("Folder already exists");
            }
            if (!mkdir($newFolderPath, 0755)) {
                throw new \RuntimeException("Failed to create folder");
            }
            return "Folder '$folderName' created";
        } elseif ($action === "replace" && isset($_FILES["replace_file"])) {
            $targetName = basename($request->getParam("name") ?? "");
            // Only files the upload would accept, never a dotfile like `.htaccess`
            if (empty($targetName) || str_starts_with($targetName, ".")
                || !in_array(strtolower(pathinfo($targetName, PATHINFO_EXTENSION)), $allowedExtensions)) {
                throw new \InvalidArgumentException("Invalid file name");
            }
            $targetPath = $resolvedPath . "/" . $targetName;
            $resolvedTarget = realpath($targetPath);
            if ($resolvedTarget === false || !is_file($resolvedTarget) || !FileSystemUtils::isInside($resolvedTarget, realpath($mediaDir))) {
                throw new \InvalidArgumentException("Invalid target file");
            }
            $file = $_FILES["replace_file"];
            if ($file["error"] !== UPLOAD_ERR_OK) {
                throw new \RuntimeException("Upload failed");
            }
            $replaceExt = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
            if (!in_array($replaceExt, $allowedExtensions)) {
                throw new \InvalidArgumentException("File extension not allowed");
            }
            // the file is served under the name of the target, so both extensions count
            if (MediaHelper::hasActiveContent($file["tmp_name"], $replaceExt)
                || MediaHelper::hasActiveContent($file["tmp_name"], pathinfo($targetName, PATHINFO_EXTENSION))) {
                throw new \InvalidArgumentException("File contains scripts or event handlers");
            }
            if (move_uploaded_file($file["tmp_name"], $resolvedTarget)) {
                return "File '$targetName' replaced";
            }
            throw new \RuntimeException("Failed to replace file");
        } elseif ($action === "delete") {
            $deleteName = basename($request->getParam("name") ?? "");
            if ($deleteName === "" || str_starts_with($deleteName, ".")) {
                throw new \InvalidArgumentException("Invalid path");
            }
            $deletePath = $resolvedPath . "/" . $deleteName;
            $resolvedDeletePath = realpath($deletePath);
            if (!FileSystemUtils::isInside($resolvedDeletePath, realpath($mediaDir))) {
                throw new \InvalidArgumentException("Invalid path");
            }
            if (is_dir($resolvedDeletePath)) {
                if (count(scandir($resolvedDeletePath)) > 2) {
                    throw new \InvalidArgumentException("Folder is not empty");
                }
                rmdir($resolvedDeletePath);
                return "Folder '$deleteName' deleted";
            } elseif (is_file($resolvedDeletePath)) {
                unlink($resolvedDeletePath);
                return "File '$deleteName' deleted";
            }
        }
    });
    $error = $result['error'];
    $success = $result['success'];
}

// Read directory contents
$entries = [];
if (is_dir($resolvedPath)) {
    $d = dir($resolvedPath);
    while (false !== ($entry = $d->read())) {
        if ($entry[0] === ".") continue;
        $entryPath = $resolvedPath . "/" . $entry;
        $isDir = is_dir($entryPath);
        $entries[] = [
            'name' => $entry,
            'isDir' => $isDir,
            'size' => $isDir ? 0 : filesize($entryPath),
            'type' => $isDir ? 'folder' : \mime_content_type($entryPath),
            'modified' => filemtime($entryPath),
        ];
    }
    $d->close();
}
usort($entries, function ($a, $b) {
    if ($a['isDir'] !== $b['isDir']) return $b['isDir'] - $a['isDir'];
    return strcasecmp($a['name'], $b['name']);
});

// Build breadcrumb
$breadcrumbs = [];
if ($currentPath) {
    $parts = explode("/", $currentPath);
    $accumulated = "";
    foreach ($parts as $part) {
        $accumulated .= ($accumulated ? "/" : "") . $part;
        $breadcrumbs[] = ['name' => $part, 'path' => $accumulated];
    }
}

$mediaWebPath = $reboot->getBaseWebPath() . "/media";

// for the side column
$libraryStats = MediaHelper::collectStats(realpath($mediaDir));
$folderStats = $currentPath ? MediaHelper::collectStats($resolvedPath) : null;
$freeSpace = @disk_free_space($mediaDir);

?>
<div class="container-fluid">
    <?= AdminHelper::renderStatusMessages($error, $success) ?>
    <?= AdminHelper::renderPageTitle('Media', '', '
        <p>Images and files for the website. Everything here is stored in <code>web/media/</code> and is reachable by everyone at <code>' . htmlspecialchars($mediaWebPath) . '/…</code>, also files that are only used on pages of a protected area.</p>
        <h6>Using files on a page</h6>
        <p>In the page editor, the toolbar button <em>Insert image from media</em> opens this library and inserts the chosen file. Click a file name here to open the file and copy its address.</p>
        <h6>Uploading</h6>
        <ul>
            <li>Several files can be uploaded at once, up to ' . htmlspecialchars(ini_get('upload_max_filesize')) . ' per file and ' . htmlspecialchars(ini_get('post_max_size')) . ' per upload (limits of the server).</li>
            <li>Allowed types: <code>' . implode('</code> <code>', $allowedExtensions) . '</code></li>
            <li>An SVG or XML file that contains scripts or event handlers is rejected, as are file names starting with a dot.</li>
            <li>A file with the same name is overwritten.</li>
        </ul>
        <h6>Replacing and deleting</h6>
        <p>Replace keeps the file name, so every page that uses the file shows the new version without being edited. Browsers may show the old one for a while because of their cache. Before deleting a file, make sure no page uses it any more. Folders can only be deleted when they are empty.</p>
    ') ?>
    <div class="row gx-4">
    <div class="col-12 col-lg-8">

    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <nav aria-label="breadcrumb" class="me-auto">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="media">media</a></li>
                <?php foreach ($breadcrumbs as $i => $crumb) { ?>
                    <?php if ($i === count($breadcrumbs) - 1) { ?>
                        <li class="breadcrumb-item active"><?= htmlspecialchars($crumb['name']) ?></li>
                    <?php } else { ?>
                        <li class="breadcrumb-item"><a href="media?path=<?= urlencode($crumb['path']) ?>"><?= htmlspecialchars($crumb['name']) ?></a></li>
                    <?php } ?>
                <?php } ?>
            </ol>
        </nav>
        <form method="post" action="media?path=<?= urlencode($currentPath) ?>" class="d-flex align-items-center gap-2">
            <input type="hidden" name="csrf_token" value="<?= CsrfProtection::getToken() ?>">
            <input type="hidden" name="action" value="create_folder">
            <input type="text" name="folder_name" class="form-control form-control-sm" style="width: 160px"
                   placeholder="New folder" required pattern="[\w\s\-\.]+">
            <button class="btn btn-sm btn-outline-secondary text-nowrap">Create Folder</button>
        </form>
    </div>

    <form method="post" action="media?path=<?= urlencode($currentPath) ?>" enctype="multipart/form-data"
          class="card mb-3">
        <div class="card-body d-flex flex-wrap align-items-center gap-2">
            <input type="hidden" name="csrf_token" value="<?= CsrfProtection::getToken() ?>">
            <input type="hidden" name="action" value="upload">
            <input type="file" name="files[]" multiple required class="form-control form-control-sm" style="max-width: 400px">
            <button class="btn btn-sm btn-primary text-nowrap">Upload</button>
        </div>
    </form>

    <?php if (empty($entries)) { ?>
        <p class="text-body-secondary">This folder is empty.</p>
    <?php } else { ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                <tr>
                    <th style="width: 50px"></th>
                    <th>Name</th>
                    <th style="width: 120px">Size</th>
                    <th style="width: 180px">Modified</th>
                    <th style="width: 80px"></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($entries as $entry) {
                    $entryWebPath = $mediaWebPath . ($currentPath ? "/" . $currentPath : "") . "/" . $entry['name'];
                    ?>
                    <tr>
                        <td class="text-center">
                            <?php if ($entry['isDir']) { ?>
                                <span style="font-size: 1.3em">&#128193;</span>
                            <?php } elseif (MediaHelper::isImageType($entry['type'])) { ?>
                                <img src="<?= htmlspecialchars($entryWebPath) ?>" alt="" style="width: 36px; height: 36px; object-fit: cover; border-radius: 3px;">
                            <?php } else { ?>
                                <span style="font-size: 1.3em">&#128196;</span>
                            <?php } ?>
                        </td>
                        <td>
                            <?php if ($entry['isDir']) { ?>
                                <a href="media?path=<?= urlencode(($currentPath ? $currentPath . "/" : "") . $entry['name']) ?>">
                                    <?= htmlspecialchars($entry['name']) ?>
                                </a>
                            <?php } else { ?>
                                <a href="<?= htmlspecialchars($entryWebPath) ?>" target="_blank">
                                    <?= htmlspecialchars($entry['name']) ?>
                                </a>
                            <?php } ?>
                        </td>
                        <td class="text-body-secondary">
                            <?= $entry['isDir'] ? '&mdash;' : MediaHelper::formatFileSize($entry['size']) ?>
                        </td>
                        <td class="text-body-secondary">
                            <?= date("Y-m-d H:i", $entry['modified']) ?>
                        </td>
                        <td>
                            <div class="d-flex gap-1">
                                <?php if (!$entry['isDir']) { ?>
                                <form method="post" action="media?path=<?= urlencode($currentPath) ?>" enctype="multipart/form-data">
                                    <input type="hidden" name="csrf_token" value="<?= CsrfProtection::getToken() ?>">
                                    <input type="hidden" name="action" value="replace">
                                    <input type="hidden" name="name" value="<?= htmlspecialchars($entry['name']) ?>">
                                    <input type="file" name="replace_file" class="d-none" onchange="this.form.submit()">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="this.form.querySelector('input[type=file]').click()">Replace</button>
                                </form>
                                <?php } ?>
                                <form method="post" action="media?path=<?= urlencode($currentPath) ?>"
                                      onsubmit="return confirm('Delete \'<?= htmlspecialchars($entry['name'], ENT_QUOTES) ?>\'?')">
                                    <input type="hidden" name="csrf_token" value="<?= CsrfProtection::getToken() ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="name" value="<?= htmlspecialchars($entry['name']) ?>">
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
    <aside class="col-12 col-lg-4 mt-4 mt-lg-0 media-stats">
        <?php
        $statsRows = function (array $stats) {
            return '<tr><th>Files</th><td>' . $stats['files'] . '</td></tr>'
                . '<tr><th>Folders</th><td>' . $stats['folders'] . '</td></tr>'
                . '<tr><th>Total size</th><td>' . MediaHelper::formatFileSize($stats['size']) . '</td></tr>';
        };
        ?>
        <?php if ($folderStats !== null) { ?>
            <div class="card mb-3">
                <div class="card-header">This folder <span class="text-body-secondary">with subfolders</span></div>
                <table class="table table-sm mb-0"><?= $statsRows($folderStats) ?></table>
            </div>
        <?php } ?>
        <div class="card mb-3">
            <div class="card-header">Library</div>
            <table class="table table-sm mb-0">
                <?= $statsRows($libraryStats) ?>
                <?php if ($freeSpace !== false) { ?>
                    <tr><th>Free disk space</th><td><?= MediaHelper::formatFileSize((int)$freeSpace) ?></td></tr>
                <?php } ?>
            </table>
        </div>
        <?php if ($libraryStats['types']) { ?>
            <div class="card mb-3">
                <div class="card-header">By type</div>
                <table class="table table-sm mb-0">
                    <?php foreach ($libraryStats['types'] as $type => $typeStats) { ?>
                        <tr>
                            <th><?= htmlspecialchars($type) ?></th>
                            <td><?= $typeStats['count'] ?></td>
                            <td><?= MediaHelper::formatFileSize($typeStats['size']) ?></td>
                        </tr>
                    <?php } ?>
                </table>
            </div>
            <div class="card mb-3">
                <div class="card-header">Largest files</div>
                <table class="table table-sm mb-0">
                    <?php foreach ($libraryStats['largest'] as $file) {
                        $folder = dirname($file['path']); ?>
                        <tr>
                            <th>
                                <a href="media?path=<?= urlencode($folder === '.' ? '' : $folder) ?>"><?= htmlspecialchars(basename($file['path'])) ?></a>
                                <?php if ($folder !== '.') { ?><div class="small text-body-secondary fw-normal"><?= htmlspecialchars($folder) ?>/</div><?php } ?>
                            </th>
                            <td><?= MediaHelper::formatFileSize($file['size']) ?></td>
                        </tr>
                    <?php } ?>
                </table>
            </div>
        <?php } ?>
        <div class="card">
            <div class="card-header">Upload limits</div>
            <table class="table table-sm mb-0">
                <tr><th>Per file</th><td><?= htmlspecialchars(ini_get('upload_max_filesize')) ?></td></tr>
                <tr><th>Per upload</th><td><?= htmlspecialchars(ini_get('post_max_size')) ?></td></tr>
            </table>
        </div>
    </aside>
    </div>
</div>
