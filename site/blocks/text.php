<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

/**  @var \Shaack\Reboot\Block $block */
// a text is always dark, it takes no `inverse`
?>
<section class="block block-text">
    <div class="container-fluid">
        <?= $block->content() ?>
    </div>
</section>
