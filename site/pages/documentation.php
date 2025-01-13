<?php

use Shaack\Reboot\Block;

/** @var \Shaack\Reboot\Reboot $reboot */
/** @var \Shaack\Reboot\Site $site */
/** @var \Shaack\Reboot\Page $page */
/** @var \Shaack\Reboot\Request $request */

// This loads the README.md and renders it as a `text` block.

$content = file_get_contents($reboot->getBaseFsPath() . "/README.md");
$block = new Block($site,"text", $content);
echo($block->render($request));