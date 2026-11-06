<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 *
 * Example of a block that shows data. The seminars are entries in
 * `site/data/seminars/`, described by `site/blueprints/seminars.yml`.
 * The page decides where the block stands and writes its heading and link,
 * the block lists the next seminars. Config: `count`, default 3.
 *
 * <!-- seminar-teaser: {count: 3} -->
 */

/** @var \Shaack\Reboot\Block $block */
/** @var \Shaack\Reboot\Site $site */
$count = (int)($block->getConfig()["count"] ?? 3);
$seminars = $site->collection("seminars")
    ->where("date", ">=", "today")
    ->limit($count);
$heading = $block->field("//h2", "Heading");
$linkHref = $block->field("//a/@href", "Link to all seminars", false, "link");
?>
<section class="block block-seminar-teaser">
    <div class="container-fluid">
        <?php if ($heading instanceof \DOMElement) { ?>
            <h2><?= $block->nodeHtml($heading) ?></h2>
        <?php } ?>
        <?php if (!count($seminars)) { ?>
            <p class="seminar-none">No seminars are planned at the moment.</p>
        <?php } else { ?>
            <div class="seminar-list">
                <?php foreach ($seminars as $seminar) {
                    $bookedOut = (bool)$seminar->get("booked-out"); ?>
                    <article class="seminar<?= $bookedOut ? " seminar-is-booked-out" : "" ?>">
                        <time class="seminar-date" datetime="<?= $seminar->get("date") ?>">
                            <span class="seminar-day"><?= $seminar->date("date", "d") ?></span>
                            <span class="seminar-month"><?= $seminar->date("date", "M Y") ?></span>
                        </time>
                        <div class="seminar-text">
                            <p class="seminar-facts">
                                <span class="seminar-level"><?= $seminar->get("level") ?></span>
                                <span><?= $seminar->get("location") ?></span>
                                <span><?= $seminar->get("time") ?></span>
                            </p>
                            <h3><?= $seminar->get("title") ?></h3>
                            <?php if ($seminar->get("teaser")) { ?>
                                <p class="seminar-teaser"><?= $seminar->get("teaser") ?></p>
                            <?php } ?>
                        </div>
                        <div class="seminar-action">
                            <span class="seminar-price"><?= $seminar->get("price") ?> €</span>
                            <?php if ($bookedOut) { ?>
                                <span class="seminar-booked-out">Booked out</span>
                            <?php } else if ($seminar->get("link")) { ?>
                                <a href="<?= $seminar->get("link") ?>">Book</a>
                                <?php if ($seminar->get("seats") !== null) { ?>
                                    <small><?= $seminar->get("seats") ?> seats left</small>
                                <?php } ?>
                            <?php } ?>
                        </div>
                    </article>
                <?php } ?>
            </div>
        <?php } ?>
        <?php if ($linkHref instanceof \DOMNode && $linkHref->nodeValue) { ?>
            <p class="seminar-more">
                <a href="<?= $block->nodeHtml($linkHref) ?>"><?= $block->nodeHtml($block->field("//a/text()", "Link label")) ?> <span aria-hidden="true">→</span></a>
            </p>
        <?php } ?>
    </div>
</section>
