<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

namespace Shaack\Reboot;

use Shaack\Utils\Logger;
use Symfony\Component\Yaml\Yaml;

class Reboot
{
    private $baseDir; // The CMS root in file system
    private $baseUrl; // the base URL, requests got to `/web` and sub folders
    private $requestUri; // the request uri
    private $route; // the route in `/web` or `/content/pages`
    private $globals; // global values, defined in `/content/globals.yml`
    private $config; // local configuration, defined in `/local/config.yml`
    private $theme; // the  current theme

    /**
     * Reboot constructor
     * @param string $uri
     * @param string $baseDir
     */
    public function __construct(string $uri, string $baseDir)
    {
        $this->requestUri = strtok($uri, '?');
        $this->baseDir = $baseDir;
        $this->baseUrl = rtrim(str_replace("index.php", "", $_SERVER['PHP_SELF']), "/");
        $this->route = rtrim($this->requestUri, "/");
        $this->config = Yaml::parseFile($this->baseDir . '/local/config.yml');
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
            /** @noinspection PhpIncludeInspection */
            include($this->baseDir . $this->route);
            exit();
        }
        Logger::setActive($this->config['logging']);
        Logger::log("---");
        Logger::log("request: " . $this->requestUri);
        $this->globals = Yaml::parseFile($this->baseDir . '/content/globals.yml');
        if (!$this->route || is_dir($this->baseDir . '/content/pages' . $this->route)) {
            $this->route = $this->route . "/index";
        }
        Logger::log("route: " . $this->route);
        echo($this->render());
    }

    /**
     * @return string
     */
    private function render(): string
    {
        $page = new Page($this);
        $template = new Template($this, $page);
        return $template->render();
    }

    /**
     * @param $url
     */
    public function redirect($url)
    {
        Logger::log("=> redirect: " . $url);
        header("Location: " . $url);
        exit;
    }

    /**
     * @return string
     */
    public function getBaseDir(): string
    {
        return $this->baseDir;
    }

    /**
     * @return string
     */
    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * @return string
     */
    public function getRequestUri(): string
    {
        return $this->requestUri;
    }

    /**
     * @return string
     */
    public function getRoute(): string
    {
        return $this->route;
    }

    /**
     * @return mixed
     */
    public function getGlobals()
    {
        return $this->globals;
    }

    /**
     * @return mixed
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * @return Theme
     */
    public function getTheme(): Theme
    {
        return $this->theme;
    }

}