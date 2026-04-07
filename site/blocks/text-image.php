<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

/**  @var \Shaack\Reboot\Block $block */

// read the configuration
$imagePosition = @$block->getConfig()["image-position"];
?>
<section class="block block-text-image">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-6 <?= $imagePosition === "left" ? "order-md-1" : "" ?>">
                <?= $block->nodeHtml($block->field("/*[part(1)]", "Text content", true, "md-editor")) ?>
            </div>
            <div class="col-md-6">
                <img class="img-fluid" src="<?= $block->nodeHtml($block->field("//img[part(2)]/@src", "Image", true, "media")) ?>"
                     alt="<?= $block->nodeHtml($block->field("//img[part(2)]/@alt", "Alt text")) ?>"
                     title="<?= $block->nodeHtml($block->field("//img[part(2)]/@title", "Title")) ?>"/>
            </div>
        </div>
    </div>
</section>
