<?php

namespace Shaack\Reboot;

class Admin extends AddOn
{
    public function getDefaultSite(): Site
    {
        return new Site($this->reboot, "/site", "");
    }

    public function getLocalConfig(): array
    {
        return $this->reboot->getConfig();
    }

    /**
     * The installed Reboot CMS version, read from `composer.json`. No network
     * access, the comparison with the published version happens in the browser.
     */
    public function getLocalVersion(): ?string
    {
        return (new Updater($this->reboot->getBaseFsPath()))->getLocalVersion();
    }
}