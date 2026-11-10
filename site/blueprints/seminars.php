<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 *
 * A seminar as a page, `/seminars/<slug>`. The blueprint seminars.yml has
 * `url: seminars`, so Reboot renders this template for each of its entries.
 */

/** @var \Shaack\Reboot\Entry $entry */
require_once __DIR__ . "/../blocks/_helpers.php";
?>
<section class="block block-post-head">
    <div class="container-fluid">
        <h1><?= $entry->get("title") ?></h1>
    </div>
</section>
<section class="block block-seminar-page">
    <div class="container-fluid">
        <?= seminarRow($entry, true) ?>
        <?php if ($entry->get("body")) { ?>
            <div class="seminar-description"><?= $entry->html("body") ?></div>
        <?php } ?>
    </div>
</section>
