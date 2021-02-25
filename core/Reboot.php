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
require 'admin/AdminSession.php';

class Reboot
{
    public $baseDir;
    public $baseUrl;
    public $website;
    public $config;
    public $requestUri;
    public $route;
    public $parsedown;
    public $admin;
    private $adminSession;

    /**
     * Reboot constructor.
     * @param string $uri
     */
    public function __construct($uri)
    {
        $this->baseDir = dirname(__DIR__);
        $this->requestUri = strtok($uri, '?');
        $this->route = rtrim($this->requestUri, "/");
        $this->config = Yaml::parseFile($this->baseDir . '/local/config.yml');
        $this->baseUrl = rtrim(str_replace("index.php", "", $_SERVER['PHP_SELF']), "/");
        new Logger($this->config['logging']);
        log("---");
        log("request: " . $this->requestUri);
        // log("baseUrl: " . $this->baseUrl);
        if (strpos($this->route, $this->config['adminPath']) === 0) {
            $this->admin = true;
            $this->baseDir = $this->baseDir . "/core/admin";
            $this->route = ltrim($this->route, $this->config['adminPath']);
            $this->adminSession = new AdminSession($this);
        }
        $this->parsedown = new Parsedown();
        $this->website = Yaml::parseFile($this->baseDir . '/content/website.yml');
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
        if ($this->admin) {
            return $this->baseUrl . "/core/admin/themes/" . $this->website["theme"];
        } else {
            return $this->baseUrl . "themes/" . $this->website["theme"];
        }
    }
}