<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot
 * License: MIT, see file 'LICENSE'
 */

namespace Shaack\Reboot;

use Symfony\Component\Yaml\Yaml;

require __DIR__ . '/../vendor/autoload.php';
require 'Page.php';

class Reboot
{
    private $config;
    private $route;

    public function __construct()
    {
        $this->config = Yaml::parseFile(__DIR__ . '/../local/config.yml');
        $this->route = rtrim($_SERVER['REQUEST_URI'], "/");
        $page = new Page($this);
        $page->render();
    }
}