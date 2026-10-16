<?php

namespace Shaack\Reboot\AddOns;

use Shaack\Reboot\AddOn;
use Shaack\Reboot\AddOns\EmailObfuscation\EmailObfuscator;
use Shaack\Reboot\Request;

/**
 * Built-in AddOn that hides e-mail addresses from harvesters. After a page
 * has been rendered, every `mailto:` link and every address in the visible
 * text is replaced by a small inline script that writes the original markup
 * back into the page. Browsers show the address as before, a crawler that
 * does not run JavaScript sees only base64.
 *
 * Enable it by adding `EmailObfuscation` to the `addons` list in
 * `site/config.yml`. There is nothing to configure.
 */
class EmailObfuscation extends AddOn
{
    public function postRender(Request $request, string $content): string
    {
        return EmailObfuscator::obfuscatePage(parent::postRender($request, $content));
    }
}
