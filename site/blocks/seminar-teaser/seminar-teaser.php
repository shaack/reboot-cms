<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 *
 * Example of a block that shows data. The seminars are entries in
 * `site/data/seminars/`, described by `site/blueprints/seminars.yml`.
 * The page decides where the block stands and writes its heading and link,
 * the block lists the next seminars.
 *
 * The block works in both kinds of pages. In a Markdown page the heading and
 * the link are Markdown and the config sets the number:
 *
 *   <!-- seminar-teaser: {count: 3} -->
 *
 *   ## Upcoming seminars
 *
 *   [All seminars](seminars)
 *
 * In a structured page the fields of `seminar-teaser.yml` hold them.
 */

/** @var \Shaack\Reboot\Block $block */
require_once __DIR__ . "/../_helpers.php";
/** @var \Shaack\Reboot\Site $site */
$structured = $block->getBlueprint() !== null;
$count = (int)($structured ? $block->get("count", 3) : ($block->getConfig()["count"] ?? 3));
$seminars = $site->collection("seminars")
    ->where("date", ">=", "today")
    ->limit(max(1, $count));
if ($structured) {
    $headingHtml = $block->get("heading", "");
    $linkUrl = $block->get("link", "");
    $linkLabel = $block->get("link-label", "All seminars");
} else {
    $heading = $block->field("//h2", "Heading");
    $headingHtml = $heading instanceof \DOMElement ? $block->nodeHtml($heading) : "";
    $linkHref = $block->field("//a/@href", "Link to all seminars", false, "link");
    $linkUrl = $linkHref instanceof \DOMNode ? $block->nodeHtml($linkHref) : "";
    $linkLabel = $block->nodeHtml($block->field("//a/text()", "Link label"));
}
?>
<section class="<?= blockClasses($block) ?>">
    <div class="container-fluid">
        <?php if ($headingHtml !== "") { ?>
            <h2><?= $headingHtml ?></h2>
        <?php } ?>
        <?php if (!count($seminars)) { ?>
            <p class="seminar-none">No seminars are planned at the moment.</p>
        <?php } else { ?>
            <div class="seminar-list">
                <?php foreach ($seminars as $seminar) {
                    echo seminarRow($seminar);
                } ?>
            </div>
        <?php } ?>
        <?php if ($linkUrl !== "") { ?>
            <p class="seminar-more">
                <a href="<?= $linkUrl ?>"><?= $linkLabel ?> <span aria-hidden="true">→</span></a>
            </p>
        <?php } ?>
    </div>
</section>
