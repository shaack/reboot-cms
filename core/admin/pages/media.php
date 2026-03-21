<?php
/** @var \Shaack\Reboot\Reboot $reboot */
/** @var \Shaack\Reboot\Site $site */
/** @var \Shaack\Reboot\Request $request */
/** @var Shaack\Reboot\Admin $admin */
$admin = $site->getAddOn("Admin");

use Shaack\Reboot\Admin\AdminHelper;
use Shaack\Reboot\Admin\MediaHelper;
use Shaack\Reboot\CsrfProtection;

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
if ($resolvedPath === false || strncmp($resolvedPath, realpath($mediaDir), strlen(realpath($mediaDir))) !== 0) {
    $currentPath = "";
    $fullCurrentPath = $mediaDir;
    $resolvedPath = realpath($mediaDir);
}

// JSON API endpoint
if ($request->getParam("list")) {
    require __DIR__ . "/api/media-list.php";
    return;
}

$action = $request->getParam("action");
if ($action) {
    $result = AdminHelper::handleAction($request, function() use ($action, $request, $resolvedPath, $mediaDir) {
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'svg',
            'mp4', 'webm', 'ogg', 'mp3', 'wav',
            'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'odp',
            'txt', 'csv', 'json', 'xml', 'yml', 'yaml',
            'zip', 'gz', 'tar', 'woff', 'woff2', 'ttf', 'otf', 'eot', 'ico'];

        if ($action === "upload" && isset($_FILES["files"])) {
            $uploadCount = 0;
            $skipped = [];
            $files = $_FILES["files"];
            for ($i = 0; $i < count($files["name"]); $i++) {
                if ($files["error"][$i] !== UPLOAD_ERR_OK) {
                    continue;
                }
                $fileName = basename($files["name"][$i]);
                $fileName = preg_replace('/[^\w\s\-.]/', '_', $fileName);
                if (empty($fileName) || $fileName === "." || $fileName === "..") {
                    continue;
                }
                $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                if (!in_array($ext, $allowedExtensions)) {
                    $skipped[] = $fileName;
                    continue;
                }
                $targetPath = $resolvedPath . "/" . $fileName;
                if (move_uploaded_file($files["tmp_name"][$i], $targetPath)) {
                    $uploadCount++;
                }
            }
            return [
                'success' => $uploadCount > 0 ? "$uploadCount file(s) uploaded" : null,
                'error' => !empty($skipped) ? "Blocked: " . implode(", ", $skipped) . " (extension not allowed)" : null,
            ];
        } elseif ($action === "create_folder") {
            $folderName = trim($request->getParam("folder_name") ?? "");
            if (!preg_match('/^[\w\s\-.]+$/', $folderName)) {
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
            if (empty($targetName) || $targetName === "." || $targetName === "..") {
                throw new \InvalidArgumentException("Invalid file name");
            }
            $targetPath = $resolvedPath . "/" . $targetName;
            $resolvedTarget = realpath($targetPath);
            if ($resolvedTarget === false || !is_file($resolvedTarget) || strncmp($resolvedTarget, realpath($mediaDir), strlen(realpath($mediaDir))) !== 0) {
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
            if (move_uploaded_file($file["tmp_name"], $resolvedTarget)) {
                return "File '$targetName' replaced";
            }
            throw new \RuntimeException("Failed to replace file");
        } elseif ($action === "delete") {
            $deleteName = basename($request->getParam("name") ?? "");
            $deletePath = $resolvedPath . "/" . $deleteName;
            $resolvedDeletePath = realpath($deletePath);
            if ($resolvedDeletePath === false || strncmp($resolvedDeletePath, realpath($mediaDir), strlen(realpath($mediaDir))) !== 0) {
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
            'type' => $isDir ? 'folder' : mime_content_type($entryPath),
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

?>
<div class="container-fluid max-width-xxl">
    <?= AdminHelper::renderStatusMessages($error, $success) ?>

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
