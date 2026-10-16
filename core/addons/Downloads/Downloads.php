<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

namespace Shaack\Reboot\AddOns;

use Shaack\Logger;
use Shaack\Reboot\AddOnLoader;
use Shaack\Reboot\Reboot;
use Shaack\Reboot\Site;

require_once __DIR__ . '/../SecureDownloads/SecureDownloads.php';
AddOnLoader::registerAutoloader('SecureDownloads', dirname(__DIR__) . '/SecureDownloads');

/**
 * Deprecated alias, the AddOn is called `SecureDownloads` now. Sites that
 * still list `Downloads` in `site/config.yml` get the same AddOn with the
 * admin page under `/admin/downloads`. Replace the entry by `SecureDownloads`.
 */
class Downloads extends SecureDownloads
{
    public function __construct(Reboot $reboot, Site $site, ?string $dir = null)
    {
        // Use the folder of SecureDownloads, so its admin pages are found.
        parent::__construct($reboot, $site, dirname(__DIR__) . '/SecureDownloads');
        Logger::info($this->getDeprecation());
    }

    public function getDeprecation(): ?string
    {
        return "The AddOn 'Downloads' was renamed to 'SecureDownloads'. Replace the entry in the addons list of site/config.yml.";
    }
}
