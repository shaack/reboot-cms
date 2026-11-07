<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

/**  @var \Shaack\Reboot\Block $block */
require_once __DIR__ . "/_helpers.php";
?>
<section class="<?= blockClasses($block) ?>">
    <div class="container-fluid">
        <?= $block->content() ?>
    </div>
</section>
