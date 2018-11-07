<?php

use Shaack\Reboot\Block;

global $reboot;
$content = file_get_contents($reboot->baseDir . "/README.md");
$block = new Block("markdown", $content);
echo($block->render());