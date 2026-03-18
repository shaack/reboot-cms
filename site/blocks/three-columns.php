<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

/**  @var \Shaack\Reboot\Block $block */
?>
<section class="block block-three-columns">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-4">
                <?= $block->nodeHtml($block->xpath("/*[part(1)]", ["required" => true, "description" => "First column"])) ?>
            </div>
            <div class="col-md-4">
                <?= $block->nodeHtml($block->xpath("/*[part(2)]", ["required" => true, "description" => "Second column"])) ?>
            </div>
            <div class="col-md-4">
                <?= $block->nodeHtml($block->xpath("/*[part(3)]", ["required" => true, "description" => "Third column"])) ?>
            </div>
        </div>
    </div>
</section>
