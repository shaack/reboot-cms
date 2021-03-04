<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

namespace Shaack\Reboot;

use Parsedown;
use Symfony\Component\Yaml\Yaml;

class Reboot
{
    public $baseDir;
    public $baseUrl;
    public $globals;
    public $config;
    public $theme;
    public $requestUri;
    public $route;
    public $parsedown;

    /**
     * Reboot constructor.
     * @param string $uri
     */
    public function __construct(string $uri)
    {
        $this->baseDir = dirname(__DIR__);
        $this->requestUri = strtok($uri, '?');
        $this->route = rtrim($this->requestUri, "/");
        $this->config = Yaml::parseFile($this->baseDir . '/local/config.yml');
        $this->baseUrl = rtrim(str_replace("index.php", "", $_SERVER['PHP_SELF']), "/");
        $this->theme = new Theme($this, $this->config['theme']);
        if (strpos($this->route, "/theme/assets/") === 0) {
            $this->theme->renderAsset($this->route);
        }
        if (strpos($this->route, "/vendor/") === 0) {
            $pathInfo = pathinfo($this->route);
            if($pathInfo['extension'] === "css") {
                header('Content-type: text/css');
            } else if($pathInfo['extension'] === "js") {
                header('Content-type: application/javascript');
            }
            include($this->baseDir . $this->route);
            exit();
        }
        new Logger($this->config['logging']);
        log("---");
        log("request: " . $this->requestUri);
        $this->parsedown = new Parsedown();
        $this->globals = Yaml::parseFile($this->baseDir . '/content/globals.yml');
        if (!$this->route || is_dir($this->baseDir . '/content/pages' . $this->route)) {
            $this->route = $this->route . "/index";
        }
        log("route: " . $this->route);
    }

    /**
     * @return string
     */
    public function render()
    {
        $page = new Page($this);
        $template = new Template($this, $page);
        return $template->render();
    }

    /*
    public function themePath()
    {
        if ($this->adminInterface) {
            return $this->baseUrl . "/core/admin/themes/" . $this->config["theme"];
        } else {
            return $this->baseUrl . "themes/" . $this->config["theme"];
        }
    }
    */

    public function redirect($url)
    {
        log("=> redirect: " . $url);
        header("Location: " . $url);
        exit;
    }
}