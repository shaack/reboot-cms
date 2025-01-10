<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

namespace Shaack\Reboot;

/**
 * Overwrite the init(), preRender() and postRender() to add functionality
 * to your Site.
 */
class AddOn
{
    protected Reboot $reboot;
    protected Site $site;

    public function __construct(Reboot $reboot, Site $site)
    {
        $this->reboot = $reboot;
        $this->site = $site;
        $this->init();
    }

    /**
     * Called after construction of the AddOn
     * @return void
     */
    protected function init()
    {

    }

    /**
     * Called on every request before rendering the page.
     * Return true, if you want to render the page or false if you do a redirect or deny access.
     */
    public function preRender(Request $request): bool
    {
        return true;
    }

    /**
     * Called after the page is rendered before displaying it. Use it to modify content after rendering.
     * Returns the modified content of the page.
     */
    public function postRender(Request $request, string $content): string
    {
        return $content;
    }
}