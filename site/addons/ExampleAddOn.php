<?php

namespace Shaack\Reboot;

use Shaack\Logger;

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
        return $content . "\n<!-- This line was added by ExampleAddOn -->";
    }


}