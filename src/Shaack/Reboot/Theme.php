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

    public function renderAsset(string $route)
    {
        $routeInAssets = str_replace("/theme/assets", "", $route);
        header('Content-type: text/css');
        include $this->reboot->baseDir . '/themes/' . $this->reboot->config['theme'] . '/assets' . $routeInAssets;
        exit();
    }

    public function getName(): string
    {
        return $this->name;
    }
}