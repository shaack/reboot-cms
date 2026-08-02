<?php

use Shaack\Reboot\Block;

/** @var \Shaack\Reboot\Reboot $reboot */
/** @var \Shaack\Reboot\Site $site */
/** @var \Shaack\Reboot\Page $page */
/** @var \Shaack\Reboot\Request $request */

// This loads the README.md and renders it as a `text` block.

$content = file_get_contents($reboot->getBaseFsPath() . "/README.md");

// The README lives at the repo root and links its screenshots with a `web/` prefix
// (e.g. `web/media/screenshots/x.png`) so they resolve on GitHub, where the repo
// root is the base. On the running site the document root IS web/, so that prefix
// must be dropped and the path pointed at the site root. This keeps the README
// correct on GitHub while the screenshots also work here.
$content = preg_replace('#\]\(web/#', '](' . $reboot->getBaseWebPath() . '/', $content);

$block = new Block($site,"text", $content);
echo($block->render($request));