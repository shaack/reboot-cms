<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

/**  @var \Shaack\Reboot\Block $block */

/*
 * This one demonstrates the usage of a block configuration.
 */

// read the configuration
$imagePosition = $block->getConfig()["image-position"];
?>
<section class="block block-text-image">
    <div class="container">
        <div class="row">
            <div class="col-md-7 <?= $imagePosition === "left" ? "order-md-1" : "" ?>">
                <!-- all text from part 1 (xpath statement) -->
                <?= $block->xpath("/*[part(1)]") ?>
            </div>
            <div class="col-md-5">
                <!-- using attributes of the image in part 2 -->
                <img class="img-fluid" src="/media/<?= $block->xpath("//img[part(2)]/@src") ?>"
                     alt="<?= $block->xpath("//img[part(2)]/@alt") ?>"
                     title="<?= $block->xpath("//img[part(2)]/@title") ?>"/>
            </div>
        </div>
    </div>
</section>
