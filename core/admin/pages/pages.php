<?php
/** @var \Shaack\Reboot\Reboot $reboot */
/** @var \Shaack\Reboot\Site $site */
/** @var \Shaack\Reboot\Request $request */
/** @var Shaack\Reboot\Admin $admin */
$admin = $site->getAddOn("Admin");

use Shaack\Utils\FileSystem;
use Shaack\Utils\Logger;

$defaultSite = $admin->getDefaultSite();
$pagesDir = $defaultSite->getFsPath() . "/pages";
$editPageName = $request->getParam("page");
$editable = false;
$pages = FileSystem::getFileList($pagesDir, true);
usort($pages, function($a, $b) {
    if($a['name'] == $b['name']) {
        return 0;
    } else {
        return $a['name'] > $b['name'] ? 1 : -1;
    }

});
if($editPageName) {
    Logger::debug("Editing page " . $editPageName);
}
?>
<div class="container-fluid">
    <div class="row">
        <div class="col-md-3 col-sidebar order-md-0 order-1 mt-4 mt-md-0">
            <nav class="nav nav-compact flex-column">
                <?php
                foreach ($pages as $page) {
                    $pagePathInfo = pathinfo($page["name"]);
                    $name = str_replace($pagesDir, "", $page["name"]);
                    // TODO configure, what is allowed
                    if (array_key_exists("extension", $pagePathInfo) && $pagePathInfo["extension"] == "md") {
                        $active = false;
                        if ($editPageName && $name == $editPageName) {
                            $editable = true;
                            $active = true;
                        }
                        ?>
                        <!--suppress HtmlUnknownTarget -->
                        <a class="nav-link<?= $active ? " active" : "" ?>" href="pages?page=<?= urlencode($name) ?>"><?= $name ?></a>
                        <?php
                    } else {
                        Logger::debug("Page " . $name . " not editable. type: " . $page["type"]);
                    }
                }
                if (!$editable && $editPageName) {
                    Logger::error("" . $editPageName . " is not editable.");
                    $editPageName = null;
                }
                ?>
            </nav>
        </div>
        <div class="col-md-9 order-md-1 order-0">
            <?php if ($editPageName) {
                $fullPath = $defaultSite->getFsPath() . "/pages" . $editPageName;
                $edited = $request->getParam("edited");
                if ($edited !== null) {
                    file_put_contents($fullPath, $edited);
                }
                ?>
                <!--suppress HtmlUnknownTarget -->
                <form method="post" action="pages?page=<?= urlencode($editPageName) ?>">
                    <!--suppress HtmlFormInputWithoutLabel -->
                    <textarea name="edited" class="form-control markdown"
                              style="height: calc(100vh - 240px)"><?= file_get_contents($fullPath) ?></textarea>
                    <button class="btn btn-primary">Save</button>
                    <?= $edited !== null ? "<span class='ml-2 text-info fade-out'>Page saved…</span>" : "" ?>
                </form>
            <?php } ?>
        </div>
    </div>
</div>