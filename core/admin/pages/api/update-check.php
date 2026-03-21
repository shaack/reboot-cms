<?php
/** JSON API: check remote version / commit */

use Shaack\Reboot\Admin\AdminHelper;

$result = ["version" => $updater->getRemoteVersion()];
if ($isMain) {
    $result["remoteCommit"] = $updater->getRemoteCommitSha();
    $result["localCommit"] = $updater->getLocalCommitSha();
}
AdminHelper::jsonResponse($result);
