<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 *
 * Example of a block for structured pages with a list field, `items` repeats
 * title and text as often as the editor adds them. Always a white band over
 * the full width, `block-inverse`. Fields: features.yml
 */

/** @var \Shaack\Reboot\Block $block */
?>
<section class="block block-features block-inverse">
    <div class="container-fluid">
        <?php if ($block->get("heading")) { ?>
            <h2><?= $block->get("heading") ?></h2>
        <?php } ?>
        <div class="feature-grid">
            <?php foreach ($block->items("items") as $item) { ?>
                <div class="feature">
                    <h3><?= $item->get("title") ?></h3>
                    <?= $item->html("text") ?>
                </div>
            <?php } ?>
        </div>
    </div>
</section>
