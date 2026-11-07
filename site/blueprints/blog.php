<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 *
 * A post of the blog as a page, `/blog/<slug>`. The blueprint blog.yml has
 * `url: blog`, so Reboot renders this template for each of its entries.
 */

/** @var \Shaack\Reboot\Entry $entry */
?>
<section class="block block-post-head">
    <div class="container-fluid">
        <h1><?= $entry->get("title") ?></h1>
        <time datetime="<?= $entry->get("date") ?>"><?= $entry->date("date", "F j, Y") ?></time>
    </div>
</section>
<section class="block block-article">
    <div class="container-fluid">
        <div class="article-layout<?= $entry->get("links") ? "" : " article-layout-plain" ?>">
            <div class="article-text"><?= $entry->html("body") ?></div>
            <?php if ($entry->get("links")) { ?>
                <aside class="article-links" aria-label="Further reading">
                    <h2>Further reading</h2>
                    <?= $entry->html("links") ?>
                </aside>
            <?php } ?>
        </div>
    </div>
</section>
