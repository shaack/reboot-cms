<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 *
 * Example of dynamic lists. Each image links to itself, the script
 * `assets/scripts/lightbox.js` opens it enlarged in a dialog. Without
 * JavaScript the link shows the image on its own.
 */

/** @var \Shaack\Reboot\Block $block */
$group = 'cards-' . substr(md5($block->content()), 0, 8);
?>

<section class="block block-cards">
    <div class="container-fluid">
        <h2><?= $block->nodeHtml($block->field("//h2", "Card group heading", true)) ?></h2>
        <div class="card-group">
        <?php
        $images = $block->field("//li/img", "Card images", true, "media-list", ["min" => 1]);
        if ($images instanceof \DOMElement) {
            $images = [$images];
        }
        foreach ($images as $image) {
            $src = htmlspecialchars($image->getAttribute("src"), ENT_QUOTES, 'UTF-8');
            $alt = htmlspecialchars($image->getAttribute("alt"), ENT_QUOTES, 'UTF-8');
            ?>
            <a class="card" href="<?= $src ?>" data-lightbox="<?= $group ?>" data-caption="<?= $alt ?>">
                <img src="<?= $src ?>" class="card-img-top" alt="<?= $alt ?>">
                <span class="card-body"><?= $alt ?></span>
            </a>
        <?php } ?>
        </div>
    </div>
</section>
