<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

namespace Shaack\Reboot;

class Theme
{
    private $reboot;
    private $name;

    public function __construct(Reboot $reboot, string $name)
    {
        $this->reboot = $reboot;
        $this->name = $name;
    }

    /**
     * Used to render assets out of `/themes/THEME_NAME/assets`
     * @param string $route
     */
    public function renderAsset(string $route)
    {
        $routeInAssets = str_replace("/theme/assets", "", $route);
        header('Content-type: text/css');
        /** @noinspection PhpIncludeInspection */
        include $this->reboot->getBaseDir() . '/themes/' . $this->reboot->getConfig()['theme'] . '/assets' . $routeInAssets;
        exit();
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }
}