<?php
/** JSON API: check remote version / commit */

use Shaack\Reboot\AddOns\Admin\AdminHelper;

$result = ["version" => $updater->getRemoteVersion(), "nextMajor" => $updater->getNextMajor()];
if ($isMain) {
    $result["remoteCommit"] = $updater->getRemoteCommitSha();
    $result["localCommit"] = $updater->getLocalCommitSha();
}
AdminHelper::jsonResponse($result);
