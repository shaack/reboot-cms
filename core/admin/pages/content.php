<?php
/** @var \Shaack\Reboot\Reboot $reboot */

use Shaack\Utils\FileSystem;

$pagesDir = $reboot->getBaseDir() . "/content/pages";
$globalsPath = $reboot->getBaseDir() . "/content/globals.yml";;
?>
<!-- <link href="/vendor/twbs/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet"> -->
<div class="container-fluid">
    <div class="row">
        <div class="col-auto">
            <ul class="list-unstyled">
                <?php

                $editable = false;
                $pages = FileSystem::getFileList($pagesDir, true);
                foreach ($pages as $page) {
                    if ($page["type"] != "text/x-php" && ($page["type"] == "text/plain" || $page["type"] == "application/x-empty")) {
                        $name = str_replace($pagesDir, "", $page["name"]);
                        \Shaack\Utils\Logger::tmp($page["name"] . ", " . $edit);
                        if($edit && $page["name"] == $edit) {
                            $editable = true;
                        }
                        ?>
                        <li><a href="?edit=<?= urlencode($page["name"]) ?>"><?= $name ?></a></li>
                        <?php
                    }
                }
                if(!$editable) {
                    $edit = null;
                }
                ?>
            </ul>
        </div>
        <div class="col">
            <?= print_r($_GET, 1) ?>
        </div>
    </div>
</div>