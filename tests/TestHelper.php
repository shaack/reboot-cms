<?php

namespace Shaack\Tests;

use Shaack\Reboot\Site;

class TestHelper
{
    /**
     * Create a Site stub with fsPath, webPath and config set, bypassing filesystem dependencies.
     */
    public static function createSiteStub(string $fsPath, string $webPath = "", array $config = []): Site
    {
        $ref = new \ReflectionClass(Site::class);
        $site = $ref->newInstanceWithoutConstructor();
        $ref->getProperty('fsPath')->setValue($site, $fsPath);
        $ref->getProperty('webPath')->setValue($site, $webPath);
        $ref->getProperty('config')->setValue($site, $config);
        return $site;
    }
}
