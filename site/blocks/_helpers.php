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
 * The intro is white on the start page only, on every other page it is dark.
 */
function blockClasses(\Shaack\Reboot\Block $block, ?\Shaack\Reboot\Request $request = null): string
{
    $classes = "block block-" . $block->getName();
    $darkIntro = $block->getName() === "intro" && $request && !isStartPage($request);
    if ($darkIntro || !empty($block->getConfig()["inverse"])) {
        $classes .= " block-inverse";
    }
    return htmlspecialchars($classes, ENT_QUOTES, 'UTF-8');
}

/**
 * True on the start page, in every language. White bands like the intro and
 * the features stand there only, the other pages stay dark.
 */
function isStartPage(?\Shaack\Reboot\Request $request): bool
{
    return $request !== null && $request->getPathWithoutLanguage() === "/";
}

/**
 * One seminar as a row with date, facts and booking, used by the blocks
 * `seminar-teaser` and `seminar-spotlight` and by the page of a seminar,
 * which has its title above the row.
 */
function seminarRow(\Shaack\Reboot\Entry $seminar, bool $onItsPage = false): string
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
            <?php // with `url: seminars` in its blueprint every seminar has a page of its own ?>
            <?php if (!$onItsPage) { ?>
            <h3><?php if ($seminar->getUrl()) { ?><a href="<?= $seminar->getUrl() ?>"><?= $seminar->get("title") ?></a><?php } else { ?><?= $seminar->get("title") ?><?php } ?></h3>
            <?php } ?>
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
