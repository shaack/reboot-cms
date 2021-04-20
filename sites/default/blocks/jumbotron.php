<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

/** @var \Shaack\Reboot\Block $block */

/*
 * https://devhints.io/xpath
 */
?>
<section class="block block-jumbotron">
    <div class="container">
        <div class="jumbotron">
            <!-- use the text of the <h1> in part 1 for the display-4 -->
            <h1 class="display-4"><?= $block->xpath("/h1[part(1)]/text()") ?></h1>
            <!-- the lead will be the text of the <p> in part 1 -->
            <p class="lead"><?= $block->xpath("/p[part(1)]/text()") ?></p>
            <hr class="my-4">
            <!-- print everything from part 2 -->
            <?= $block->xpath("/*[part(2)]") ?>
            <p>
                <!-- the link in part 3 will be used as the primary button -->
                <a class="btn btn-primary btn-lg" href="<?= $block->xpath("//a[part(3)]/@href") ?>"
                   role="button"><?= $block->xpath("//a[part(3)]/text()") ?></a>
            </p>
        </div>
    </div>
</section>