<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 *
 * A text of a structured page, the counterpart of the Markdown block `text`.
 * Fields: text.yml
 */

/** @var \Shaack\Reboot\Block $block */
// a text is always dark, it takes no `inverse`
?>
<section class="block block-text">
    <div class="container-fluid">
        <?= $block->html("text") ?>
    </div>
</section>
