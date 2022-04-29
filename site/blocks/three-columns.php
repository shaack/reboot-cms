<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

/**  @var \Shaack\Reboot\Block $block */
?>
<section class="block block-three-columns">
    <div class="container">
        <div class="row">
            <div class="col-md-4">
                <?= $block->nodeHtml($block->xpath("/*[part(1)]")) ?>
            </div>
            <div class="col-md-4">
                <?= $block->nodeHtml($block->xpath("/*[part(2)]")) ?>
            </div>
            <div class="col-md-4">
                <?= $block->nodeHtml($block->xpath("/*[part(3)]")) ?>
            </div>
        </div>
    </div>
</section>
