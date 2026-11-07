<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 *
 * A text of a structured page, the counterpart of the Markdown block `text`.
 * Fields: content.yml
 */

/** @var \Shaack\Reboot\Block $block */
require_once __DIR__ . "/../_helpers.php";
?>
<section class="<?= blockClasses($block) ?>">
    <div class="container-fluid">
        <?= $block->html("text") ?>
    </div>
</section>
