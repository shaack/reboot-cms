<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 *
 * Example of dynamic lists
 */

/** @var \Shaack\Reboot\Block $block */
?>

<section class="block block-cards">
    <div class="container">
        <h2><?= $block->nodeHtml($block->xpath("//h2")) ?></h2>
        <div class="card-group">
        <?php
        $images = $block->xpath("//li/img");
        foreach ($images as $image) { ?>
            <div class="card">
                <img src="<?= $image->getAttribute("src") ?>" class="card-img-top" alt="<?= $image->getAttribute("alt") ?>">
                <div class="card-body">
                    <?= $image->getAttribute("alt") ?>
                </div>
            </div>
        <?php } ?>
        </div>
    </div>
</section>