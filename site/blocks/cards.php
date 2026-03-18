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
    <div class="container-fluid">
        <h2><?= $block->nodeHtml($block->xpath("//h2", ["required" => true, "description" => "Card group heading"])) ?></h2>
        <div class="card-group">
        <?php
        $images = $block->xpath("//li/img", ["min" => 1, "description" => "Card images"]);
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