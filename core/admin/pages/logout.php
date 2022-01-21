<?php
/** @var \Shaack\Reboot\Site $site */
/** @var Shaack\Reboot\Authentication $authentication */
$authentication = $site->getAddOn("Authentication");

$authentication->logout();
