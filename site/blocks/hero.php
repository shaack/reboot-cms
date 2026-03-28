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
                    <h1 class="display-4"><?= $block->nodeHtml($block->field("/h1[part(1)]/text()", "Hero heading", true)) ?></h1>
                    <p class="lead"><?= $block->nodeHtml($block->field("/p[part(1)]/text()", "Lead text")) ?></p>
                    <hr class="my-4">
                    <div class="mb-4">
                        <?= $block->nodeHtml($block->field("/*[part(2)]", "Description", false, "md-editor")) ?>
                    </div>
                    <p>
                        <a class="btn btn-primary btn-lg"
                           href="<?= $block->nodeHtml($block->field("//a[part(3)]/@href", "Call-to-action link", true, "link")) ?>"
                           role="button"><?= $block->nodeHtml($block->field("//a[part(3)]/text()", "Call-to-action label", true)) ?></a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>