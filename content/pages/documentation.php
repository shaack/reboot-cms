<?php

use Shaack\Reboot\Block;

/** @var \Shaack\Reboot\Reboot $reboot */
/** @var \Shaack\Reboot\Page $page */

// This loads the README.md an renders it a a `text` block.

$content = file_get_contents($reboot->getBaseDir() . "/README.md");
$block = new Block($reboot, $page, "text", $content);
echo($block->render());