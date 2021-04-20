<?php
/** @var \Shaack\Reboot\Reboot $reboot */

use Shaack\Utils\FileSystem;

$pagesDir = $reboot->getBaseFsPath() . "/sites/pages";
$globalsPath = $reboot->getBaseFsPath() . "/sites/config.yml";;
?>
<!--<link href="/vendor/twbs/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet"> -->
<div class="container-fluid">
    <div class="row">
        <div class="col-auto">
            <ul class="list-unstyled">
                <?php
                $edit = $reboot->getRequest()->getParam("edit");
                $editable = false;
                $pages = FileSystem::getFileList($pagesDir, true);
                foreach ($pages as $page) {
                    if ($page["type"] != "text/x-php" && ($page["type"] == "text/plain" || $page["type"] == "application/x-empty")) {
                        $name = str_replace($pagesDir, "", $page["name"]);
                        if ($edit && $page["name"] == $edit) {
                            $editable = true;
                        }
                        ?>
                        <li><a href="?edit=<?= urlencode($page["name"]) ?>"><?= $name ?></a></li>
                        <?php
                    }
                }
                if (!$editable) {
                    $edit = null;
                }
                ?>
            </ul>
        </div>
        <div class="col">
            <?php if ($edit) {
                $name = str_replace($pagesDir, "", $edit);
                $edited = $reboot->getRequest()->getParam("edited");
                if($edited !== null) {
                    file_put_contents($edit, $edited);
                }
                ?>
                <h2><?= $name ?></h2>
                <form method="post">
                    <div class="form-group">
                    <textarea name="edited" class="form-control"
                              style="height: calc(100vh - 240px)"><?= file_get_contents($edit) ?></textarea>
                    </div>
                    <button class="btn btn-primary">Save</button>
                    <?= $edited !== null ? "<span class='ml-2 text-info fade-out'>sites saved…</span>" : "" ?>
                </form>
            <?php } ?>
        </div>
    </div>
</div>