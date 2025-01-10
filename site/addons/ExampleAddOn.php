<?php

namespace addons;

use Shaack\Logger;
use Shaack\Reboot\AddOn;
use Shaack\Reboot\Request;

class ExampleAddOn extends AddOn
{
    protected function init()
    {
        Logger::info("ExampleAddOn init()");
    }

    public function preRender(Request $request): bool
    {
        Logger::info("ExampleAddOn preRender(), " . $request->getPath());
        return true;
    }

    public function postRender(Request $request, string $content): string
    {
        Logger::info("ExampleAddOn postRender(), " . $request->getPath());
        return parent::postRender($request, $content); // or return the filtered (modified) content
    }


}