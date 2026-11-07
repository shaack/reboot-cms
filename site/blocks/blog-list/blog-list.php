<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 *
 * The posts of the blog, the entries of `site/data/blog/`. Each one links to
 * its page, which the blueprint `site/blueprints/blog.yml` makes with `url: blog`.
 * Fields: blog-list.yml
 */

/** @var \Shaack\Reboot\Block $block */
/** @var \Shaack\Reboot\Site $site */
require_once __DIR__ . "/../_helpers.php";
$posts = $site->collection("blog");
if ($block->get("count")) {
    $posts = $posts->limit((int)$block->get("count"));
}
?>
<section class="<?= blockClasses($block) ?>">
    <div class="container-fluid">
        <?php if ($block->get("heading")) { ?>
            <h1><?= $block->get("heading") ?></h1>
        <?php } ?>
        <?php if ($block->get("lead")) { ?>
            <div class="blog-lead"><?= $block->html("lead") ?></div>
        <?php } ?>
        <div class="blog-list">
            <?php foreach ($posts as $post) { ?>
                <article class="blog-teaser">
                    <time datetime="<?= $post->get("date") ?>"><?= $post->date("date", "F j, Y") ?></time>
                    <h2><a href="<?= $post->getUrl() ?>"><?= $post->get("title") ?></a></h2>
                    <?php if ($post->get("description")) { ?>
                        <p><?= $post->get("description") ?></p>
                    <?php } ?>
                </article>
            <?php } ?>
        </div>
    </div>
</section>
