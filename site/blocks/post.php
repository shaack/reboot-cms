<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 *
 * A post of the blog, a Markdown page in `site/pages/blog/` without blocks,
 * whose frontmatter says `block: post`. The block renders the whole page, the
 * title and the date from the frontmatter above the text, the links of
 * `links` next to it:
 *
 *   ---
 *   title: Fast pages without tuning
 *   date: 2026-10-01
 *   block: post
 *   links: |
 *     - [All posts](blog)
 *   ---
 *
 *   The text of the post.
 */

/** @var \Shaack\Reboot\Block $block */
$config = $block->getConfig();
$e = fn($value) => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
$date = is_string($config["date"] ?? null) ? \DateTime::createFromFormat("!Y-m-d", $config["date"]) : false;
$links = is_string($config["links"] ?? null) && trim($config["links"]) !== "" ? (new Parsedown())->text($config["links"]) : "";
?>
<section class="block block-post-head">
    <div class="container-fluid">
        <h1><?= $e($config["title"] ?? "") ?></h1>
        <?php if ($date) { ?>
            <time datetime="<?= $e($config["date"]) ?>"><?= $e($date->format("F j, Y")) ?></time>
        <?php } ?>
    </div>
</section>
<section class="block block-article">
    <div class="container-fluid">
        <div class="article-layout<?= $links ? "" : " article-layout-plain" ?>">
            <div class="article-text"><?= $block->content() ?></div>
            <?php if ($links) { ?>
                <aside class="article-links" aria-label="Further reading">
                    <h2>Further reading</h2>
                    <?= $links ?>
                </aside>
            <?php } ?>
        </div>
    </div>
</section>
