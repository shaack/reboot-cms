<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot
 * License: MIT, see file 'LICENSE'
 */

namespace Shaack\Reboot;

use Page;
use Symfony\Component\Yaml\Yaml;

require __DIR__ . '/../vendor/autoload.php';
require 'Page.php';
require 'Article.php';
require 'Template.php';

class Reboot
{
    public $baseDir;
    public $config;
    public $route;
    public $parsedown;

    /**
     * Reboot constructor.
     * @param string $uri
     */
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

    /**
     * @param string $message
     */
    public function log($message)
    {
        if ($this->config->debug) {
            error_log($message);
        }
    }

    public function render()
    {
        $article = new Article($this);
        $template = new Template($this);
        $page = new Page($this, $template, $article);
        return $page->render();
    }
}