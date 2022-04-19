<?php

namespace Shaack\Reboot;

class Admin extends AddOn
{
    public function getDefaultSite(): Site
    {
        return new Site($this->reboot, "/sites/default", "");
    }
}