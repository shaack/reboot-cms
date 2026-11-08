<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 *
 * The introduction of a structured page, the counterpart of the Markdown block
 * `intro`, a white band, or a dark one with `inverse`. Fields: intro.yml
 */

/** @var \Shaack\Reboot\Block $block */
require_once __DIR__ . "/../_helpers.php";
$image = $block->get("image");
?>
<section class="<?= blockClasses($block, $request) ?>">
    <div class="container-fluid">
        <div class="intro-layout<?= $image ? "" : " intro-layout-text" ?>">
            <div class="intro-text">
                <h1><?= $block->get("heading") ?></h1>
                <?php if ($block->get("lead")) { ?>
                    <p class="intro-lead"><?= nl2br($block->get("lead")) ?></p>
                <?php } ?>
                <?php if ($block->get("text")) { ?>
                    <hr class="my-4">
                    <div class="mb-4"><?= $block->html("text") ?></div>
                <?php } ?>
                <?php if ($block->get("button-link")) { ?>
                    <p><a class="btn btn-primary btn-lg" href="<?= $block->get("button-link") ?>"><?= $block->get("button-label", "More") ?></a></p>
                <?php } ?>
            </div>
            <?php if ($image) { ?>
                <img class="intro-image" src="<?= $image ?>" alt="">
            <?php } ?>
        </div>
    </div>
</section>
