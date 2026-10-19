<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 *
 * An article with a box of links next to it. The block has two parts,
 * separated by a line of three dashes. The first part is the article text, the
 * second the links, which stand to the right on wide screens and stay in view
 * while scrolling:
 *
 *   <!-- article -->
 *
 *   The article text.
 *
 *   ---
 *
 *   - [Title](/path)
 *
 * The heading of the box is "Further reading", the block comment can set
 * another one:
 *
 *   <!--
 *   article:
 *       title: On this page
 *   -->
 *
 * Because the parts are split at that line, the article text cannot contain a
 * horizontal rule.
 */

/** @var \Shaack\Reboot\Block $block */

$title = $block->getConfig()['title'] ?? 'Further reading';
$text = $block->field("/*[part(1)]", "Article text", true, "md-editor");
$aside = $block->field("/*[part(2)]", "Links", false, "md-editor");
$hasAside = $aside instanceof \DOMNode || ($aside instanceof \DOMNodeList && $aside->length > 0);

// A query with a single match returns the element itself, and nodeHtml() renders
// only what is inside it. A part that is just one list would lose its list and
// link tags that way, so a single element is written out as a whole.
$outerHtml = fn(\DOMNode|\DOMNodeList $node): string => $node instanceof \DOMElement
    ? (string)$node->ownerDocument->saveHTML($node)
    : $block->nodeHtml($node);
?>
<section class="block block-article">
    <div class="container-fluid">
        <div class="article-layout<?= $hasAside ? '' : ' article-layout-plain' ?>">
            <div class="article-text"><?= $outerHtml($text) ?></div>
            <?php if ($hasAside) { ?>
                <aside class="article-links" aria-label="<?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>">
                    <h2><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h2>
                    <?= $outerHtml($aside) ?>
                </aside>
            <?php } ?>
        </div>
    </div>
</section>
