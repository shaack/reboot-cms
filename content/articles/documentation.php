<?php

use Shaack\Reboot\Block;

$content = file_get_contents($this->reboot->baseDir . "/README.md");
$block = new Block($this->reboot,"markdown", $content);
echo($block->render());