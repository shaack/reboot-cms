<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 *
 * A text next to an image, the counterpart of the Markdown block `image-text`.
 * Fields: image-text.yml
 */

/** @var \Shaack\Reboot\Block $block */
require_once __DIR__ . "/../_helpers.php";
?>
<section class="<?= blockClasses($block) ?>">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-6 <?= $block->get("image-position") === "left" ? "order-md-1" : "" ?>">
                <?= $block->html("text") ?>
            </div>
            <div class="col-md-6">
                <img class="img-fluid" src="<?= $block->get("image") ?>" alt="<?= $block->get("alt", "") ?>"
                    <?php if ($block->get("caption")) { ?> title="<?= $block->get("caption") ?>"<?php } ?>/>
            </div>
        </div>
    </div>
</section>
