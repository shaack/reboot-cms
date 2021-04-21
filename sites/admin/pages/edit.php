<?php
/** @var \Shaack\Reboot\Reboot $reboot */
/** @var \Shaack\Reboot\SiteExtension $site */
/** @var \Shaack\Reboot\Request $request */

use Shaack\Utils\FileSystem;
use Shaack\Utils\Logger;

$defaultSite = $site->getDefaultSite();
$pagesDir = $defaultSite->getFsPath() . "/pages";
$editPageName = $request->getParam("page");
$editable = false;
$pages = FileSystem::getFileList($pagesDir, true);
usort($pages, function($a, $b) {
    return $a['name'] >= $b['name'];
});
if($editPageName) {
    Logger::debug("Editing page " . $editPageName);
}
?>
<div class="container-fluid">
    <div class="row">
        <div class="col-auto">
            <nav class="nav nav-compact flex-column">
                <?php
                foreach ($pages as $page) {
                    if ($page["type"] != "text/x-php" && ($page["type"] == "text/plain" || $page["type"] == "application/x-empty")) {
                        $name = str_replace($pagesDir, "", $page["name"]);
                        $active = false;
                        if ($editPageName && $name == $editPageName) {
                            $editable = true;
                            $active = true;
                        }
                        ?>
                        <!--suppress HtmlUnknownTarget -->
                        <a class="nav-link<?= $active ? " active" : "" ?>" href="/admin/edit?page=<?= urlencode($name) ?>"><?= $name ?></a>
                        <?php
                    }
                }
                if (!$editable && $editPageName) {
                    Logger::error("" . $editPageName . " is not editable.");
                    $editPageName = null;
                }
                ?>
            </nav>
        </div>
        <div class="col">
            <?php if ($editPageName) {
                $fullPath = $defaultSite->getFsPath() . "/pages" . $editPageName;
                Logger::tmp("fullPath: " . $fullPath);
                $edited = $request->getParam("edited");
                if ($edited !== null) {
                    file_put_contents($fullPath, $edited);
                }
                ?>
                <!--suppress HtmlUnknownTarget -->
                <form method="post" action="/admin/edit?page=<?= urlencode($editPageName) ?>">
                    <!--suppress HtmlFormInputWithoutLabel -->
                    <textarea name="edited" class="form-control"
                              style="height: calc(100vh - 240px)"><?= file_get_contents($fullPath) ?></textarea>
                    <button class="btn btn-primary">Save</button>
                    <?= $edited !== null ? "<span class='ml-2 text-info fade-out'>Page saved…</span>" : "" ?>
                </form>
            <?php } ?>
        </div>
    </div>
</div>