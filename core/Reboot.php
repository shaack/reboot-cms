<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

namespace Shaack\Reboot;

use Page;
use Symfony\Component\Yaml\Yaml;

require __DIR__ . '/../vendor/autoload.php';
require 'Page.php';
require 'Article.php';

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
        $this->config = Yaml::parseFile($this->baseDir . '/local/config.yml');
        $this->log("---");
        $this->log("request: " . $uri);
        // $this->log(print_r($this->config, true));
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
        if ($this->config['debug']) {
            error_log($message);
        }
    }

    /**
     * @return string
     */
    public function render()
    {
        $article = new Article($this);
        $page = new Page($this, $article);
        return $page->render();
    }
}