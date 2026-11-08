<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 *
 * Example of a block for structured pages, the counterpart of the Markdown
 * block `gallery`: a list of images with captions. Each image opens in the
 * lightbox of `assets/scripts/lightbox.js`. Fields: gallery.yml
 */

/** @var \Shaack\Reboot\Block $block */
require_once __DIR__ . "/../_helpers.php";
$group = 'gallery-' . substr(md5(json_encode($block->getValues())), 0, 8);
?>
<section class="<?= blockClasses($block) ?>">
    <div class="container-fluid">
        <?php if ($block->get("heading")) { ?>
            <h2><?= $block->get("heading") ?></h2>
        <?php } ?>
        <div class="card-group">
            <?php foreach ($block->items("images") as $item) { ?>
                <a class="card" href="<?= $item->get("image") ?>" data-lightbox="<?= $group ?>" data-caption="<?= $item->get("caption") ?>">
                    <img src="<?= $item->get("image") ?>" class="card-img-top" alt="<?= $item->get("caption") ?>">
                    <?php if ($item->get("caption")) { ?>
                        <span class="card-body"><?= $item->get("caption") ?></span>
                    <?php } ?>
                </a>
            <?php } ?>
        </div>
    </div>
</section>
