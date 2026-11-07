<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 *
 * Example of a block for structured pages that points to data: the field
 * `seminar` names an entry of `site/data/seminars/`, `$block->entry()` reads it.
 * Fields: seminar-spotlight.yml
 */

/** @var \Shaack\Reboot\Block $block */
require_once __DIR__ . "/_helpers.php";
$seminar = $block->entry("seminar");
?>
<section class="<?= blockClasses($block) ?>">
    <div class="container-fluid">
        <h2><?= $block->get("heading", "In focus") ?></h2>
        <?php if ($seminar) { ?>
            <div class="seminar-list"><?= seminarRow($seminar) ?></div>
        <?php } else { ?>
            <p class="seminar-none">The seminar is not available.</p>
        <?php } ?>
        <?php if ($block->get("note")) { ?>
            <div class="seminar-note"><?= $block->html("note") ?></div>
        <?php } ?>
    </div>
</section>
