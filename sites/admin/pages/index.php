<?php

/** @var \Shaack\Reboot\Reboot $reboot */
/** @var \Shaack\Reboot\Site $site */

$reboot->redirect("/" . $site->getName() . "/login");