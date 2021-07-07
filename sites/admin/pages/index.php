<?php

/** @var \Shaack\Reboot\Reboot $reboot */
/** @var \Shaack\Reboot\Site $site */

$reboot->redirect($reboot->getBaseWebPath() . "/" . $site->getName() . "/login");