<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

include "core/Reboot.php";

$reboot = new Shaack\Reboot\Reboot($_SERVER['REQUEST_URI']);
echo($reboot->render());