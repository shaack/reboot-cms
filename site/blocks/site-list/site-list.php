<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 *
 * The websites built with Reboot CMS, the entries of `site/data/sites/`.
 * Fields: site-list.yml
 */

/** @var \Shaack\Reboot\Block $block */
/** @var \Shaack\Reboot\Site $site */
require_once __DIR__ . "/../_helpers.php";
?>
<section class="<?= blockClasses($block) ?>">
    <div class="container-fluid">
        <?php if ($block->get("heading")) { ?>
            <h2><?= $block->get("heading") ?></h2>
        <?php } ?>
        <ul class="site-list">
            <?php foreach ($site->collection("sites") as $entry) { ?>
                <li><a href="<?= $entry->get("url") ?>"><?= $entry->get("name") ?></a></li>
            <?php } ?>
        </ul>
    </div>
</section>
