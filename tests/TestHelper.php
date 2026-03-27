<?php

namespace Shaack\Tests;

use Shaack\Reboot\Site;

class TestHelper
{
    /**
     * Create a Site stub with just fsPath and webPath set, bypassing filesystem dependencies.
     */
    public static function createSiteStub(string $fsPath, string $webPath = ""): Site
    {
        $ref = new \ReflectionClass(Site::class);
        $site = $ref->newInstanceWithoutConstructor();
        $ref->getProperty('fsPath')->setValue($site, $fsPath);
        $ref->getProperty('webPath')->setValue($site, $webPath);
        return $site;
    }
}
