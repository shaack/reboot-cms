<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 *
 * Helpers shared by the block templates. The name starts with an underscore,
 * so it is no block, see "Block" in the README.
 */

/**
 * The classes of the section a block template writes: `block block-<name>`,
 * plus `block-inverse` for a block written as `<!-- name: {inverse: true} -->`.
 * The stylesheet turns an inverse block into a white band over the full width,
 * the intro, which is white already, into a dark one.
 */
function blockClasses(\Shaack\Reboot\Block $block): string
{
    $classes = "block block-" . $block->getName();
    if (!empty($block->getConfig()["inverse"])) {
        $classes .= " block-inverse";
    }
    return htmlspecialchars($classes, ENT_QUOTES, 'UTF-8');
}

/**
 * One seminar as a row with date, facts and booking, used by the blocks
 * `seminar-teaser` and `seminar-spotlight`.
 */
function seminarRow(\Shaack\Reboot\Entry $seminar): string
{
    $bookedOut = (bool)$seminar->get("booked-out");
    ob_start(); ?>
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
    <?php return ob_get_clean();
}
