<?php
/** @var \Shaack\Reboot\Site $site */
/** @var Shaack\Reboot\AddOns\Authentication $authentication */
$authentication = $site->getAddOn("Authentication");

$authentication->logout();
