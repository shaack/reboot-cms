<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

namespace Shaack\Reboot;

use Page;
use Parsedown;
use Symfony\Component\Yaml\Yaml;

require __DIR__ . '/../vendor/autoload.php';
require 'Page.php';
require 'Article.php';
require 'utils/Logger.php';

class Reboot
{
    public $baseDir;
    public $baseUrl;
    public $website;
    public $config;
    public $uri;
    public $route;
    public $parsedown;

    /**
     * Reboot constructor.
     * @param string $uri
     */
    public function __construct($uri)
    {
        $this->baseDir = dirname(__DIR__);
        $this->uri = strtok($uri, '?');
        $this->route = rtrim($this->uri, "/");
        $this->config = Yaml::parseFile($this->baseDir . '/local/config.yml');
        new Logger($this->config['logging']);
        log("---");
        log("request: " . $this->uri);
        $this->baseUrl = str_replace("index.php", "", $_SERVER['PHP_SELF']);
        $this->website = Yaml::parseFile($this->baseDir . '/content/website.yml');
        $this->parsedown = new Parsedown();
        if (!$this->route || is_dir($this->baseDir . '/content/articles' . $this->route)) {
            $this->route = $this->route . "/index";
        }
        log("route: " . $this->route);
    }

    /**
     * @return string
     */
    public function renderArticle()
    {
        $article = new Article($this);
        $page = new Page($this, $article);
        return $page->render();
    }

    public function themePath()
    {
        return $this->baseUrl . "themes/" . $this->website["theme"];
    }
}