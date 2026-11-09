<?php
/** JSON API: check remote version / commit */

use Shaack\Reboot\AddOns\Admin\AdminHelper;
use Shaack\Reboot\Updater;

$result = ["version" => $updater->getRemoteVersion(), "nextMajor" => $updater->getNextMajor()];
if ($result["nextMajor"]) {
    // what the next major version reads differently in this site, named before the upgrade
    $result["nextMajor"]["findings"] = Updater::upgradeFindings($site->getFsPath());
}
if ($isMain) {
    $result["remoteCommit"] = $updater->getRemoteCommitSha();
    $result["localCommit"] = $updater->getLocalCommitSha();
}
AdminHelper::jsonResponse($result);
