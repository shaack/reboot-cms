<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

/** @var \Shaack\Reboot\Block $block */
?>
<section class="block block-hero">
    <div class="container-fluid">
        <div class="card border-0 bg-gradient">
            <div class="card-body">
                <div class="p-xl-5 p-md-4 p-3">
                    <h1 class="display-4"><?= $block->nodeHtml($block->xpath("/h1[part(1)]/text()", ["required" => true, "description" => "Hero heading (h1)"])) ?></h1>
                    <p class="lead"><?= $block->nodeHtml($block->xpath("/p[part(1)]/text()")) ?></p>
                    <hr class="my-4">
                    <div class="mb-4">
                        <?= $block->nodeHtml($block->xpath("/*[part(2)]")) ?>
                    </div>
                    <p>
                        <a class="btn btn-primary btn-lg"
                           href="<?= $block->nodeHtml($block->xpath("//a[part(3)]/@href", ["required" => true, "description" => "Call-to-action link"])) ?>"
                           role="button"><?= $block->nodeHtml($block->xpath("//a[part(3)]/text()")) ?></a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>