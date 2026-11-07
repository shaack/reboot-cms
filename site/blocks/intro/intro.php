<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 *
 * Example of a block for structured pages, its fields are in intro.yml.
 */

/** @var \Shaack\Reboot\Block $block */
require_once __DIR__ . "/../_helpers.php";
$image = $block->get("image");
?>
<section class="<?= blockClasses($block) ?>">
    <div class="container-fluid">
        <div class="intro-layout<?= $image ? "" : " intro-layout-text" ?>">
            <div class="intro-text">
                <h1><?= $block->get("heading") ?></h1>
                <?php if ($block->get("lead")) { ?>
                    <p class="intro-lead"><?= nl2br($block->get("lead")) ?></p>
                <?php } ?>
                <?= $block->html("text") ?>
                <?php if ($block->get("button-link")) { ?>
                    <p><a class="btn btn-primary" href="<?= $block->get("button-link") ?>"><?= $block->get("button-label", "More") ?></a></p>
                <?php } ?>
            </div>
            <?php if ($image) { ?>
                <img class="intro-image" src="<?= $image ?>" alt="">
            <?php } ?>
        </div>
    </div>
</section>
