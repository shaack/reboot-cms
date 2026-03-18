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
                <?= $block->nodeHtml($block->xpath("/*[part(1)]", ["required" => true, "description" => "Text content"])) ?>
            </div>
            <div class="col-md-6">
                <img class="img-fluid" src="<?= $block->nodeHtml($block->xpath("//img[part(2)]/@src", ["required" => true, "description" => "Image"])) ?>"
                     alt="<?= $block->nodeHtml($block->xpath("//img[part(2)]/@alt")) ?>"
                     title="<?= $block->nodeHtml($block->xpath("//img[part(2)]/@title")) ?>"/>
            </div>
        </div>
    </div>
</section>
