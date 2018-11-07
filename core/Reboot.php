<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot
 * License: MIT, see file 'LICENSE'
 */

namespace Shaack\Reboot;

require __DIR__ . '/../vendor/autoload.php';
require 'Page.php';

class Reboot
{
    public $config;
    public $route;
    public $parsedown;

    public function __construct($uri)
    {
        $configJson = file_get_contents(__DIR__ . '/../local/config.json');
        $this->config = json_decode($configJson);
        $this->route = rtrim($uri, "/");
        $this->parsedown = new \Parsedown();
        if(!$this->route || is_dir(__DIR__ . '/../local/pages' . $this->route)) {
            $this->route = $this->route . "/index";
        }
    }

    public function render()
    {
        $page = new Page($this);
        $page->render($this->route);
    }
}