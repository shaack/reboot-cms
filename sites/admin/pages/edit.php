<?php
/** @var \Shaack\Reboot\Reboot $reboot */
/** @var \Shaack\Reboot\SiteExtension $site */
/** @var \Shaack\Reboot\Request $request */

use Shaack\Utils\FileSystem;

$defaultSite = $site->getDefaultSite();
$pagesDir = $defaultSite->getFsPath() . "/pages";
// $globalsPath = $site->getFsPath() . "/sites/config.yml";;
?>
<div class="container-fluid">
    <div class="row">
        <div class="col-auto">
            <ul class="list-unstyled">
                <?php
                $editPageName = $request->getParam("edit");
                $editable = false;
                $pages = FileSystem::getFileList($pagesDir, true);
                foreach ($pages as $page) {
                    if ($page["type"] != "text/x-php" && ($page["type"] == "text/plain" || $page["type"] == "application/x-empty")) {
                        $name = str_replace($pagesDir, "", $page["name"]);
                        if ($editPageName && $page["name"] == $editPageName) {
                            $editable = true;
                        }
                        ?>
                        <li><a href="/admin/edit?edit=<?= urlencode($page["name"]) ?>"><?= $name ?></a></li>
                        <?php
                    }
                }
                if (!$editable) {
                    $editPageName = null;
                }
                ?>
            </ul>
        </div>
        <div class="col">
            <?php if ($editPageName) {
                $name = str_replace($pagesDir, "", $editPageName);
                $edited = $request->getParam("edited");
                if($edited !== null) {
                    Logger::tmp("editPageName: " . $editPageName);
                    // file_put_contents($defaultSite->getFsPath() . $editPageName, $edited);
                }
                ?>
                <h2><?= $name ?></h2>
                <form method="post">
                    <div class="form-group">
                    <textarea name="edited" class="form-control"
                              style="height: calc(100vh - 240px)"><?= file_get_contents($editPageName) ?></textarea>
                    </div>
                    <button class="btn btn-primary">Save</button>
                    <?= $edited !== null ? "<span class='ml-2 text-info fade-out'>sites saved…</span>" : "" ?>
                </form>
            <?php } ?>
        </div>
    </div>
</div>