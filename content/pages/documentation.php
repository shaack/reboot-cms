<?php

use Shaack\Reboot\Block;

/** @var \Shaack\Reboot\Reboot $reboot */
/** @var \Shaack\Reboot\Page $page */

$content = file_get_contents($reboot->getBaseDir() . "/README.md");
$block = new Block($reboot, $page, "text", $content);
echo($block->render());