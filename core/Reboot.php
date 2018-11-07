<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot
 * License: MIT, see file 'LICENSE'
 */

namespace Shaack\Reboot;

use Symfony\Component\Yaml\Yaml;

require __DIR__ . '/../vendor/autoload.php';
require 'Article.php';

class Reboot
{
    public $baseDir;
    public $config;
    public $route;
    public $parsedown;

    public function __construct($uri)
    {
        $this->baseDir = dirname(__DIR__);
        $this->config = Yaml::parseFile($this->baseDir . '/local/config.yml', Yaml::PARSE_OBJECT_FOR_MAP);
        $this->route = rtrim($uri, "/");
        $this->parsedown = new \Parsedown();
        if (!$this->route || is_dir($this->baseDir . '/local/articles' . $this->route)) {
            $this->route = $this->route . "/index";
        }
        $this->log("route: " . $this->route);
    }

    public function log($message)
    {
        if ($this->config->debug) {
            error_log($message);
        }
    }

    public function render()
    {
        $page = new Article($this);
        $page->render($this->route);
    }
}