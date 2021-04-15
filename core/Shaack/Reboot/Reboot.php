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
    private $contentDir; // The path to the `content` folder
    private $baseUrl; // The base URL, requests got to `/web` and sub folders
    private $requestUri; // The current request uri
    private $route; // The route in `/content/pages` (…or `/web`)
    private $globals; // Global values, defined in `/content/globals.yml`
    private $config; // Local configuration, defined in `/local/config.yml`
    private $theme; // The  current theme

    /**
     * Reboot constructor
     * @param string $uri
     * @param string $baseDir
     */
    public function __construct(string $uri, string $baseDir)
    {
        $this->requestUri = strtok($uri, '?');
        $this->baseDir = $baseDir;
        $this->config = Yaml::parseFile($this->baseDir . '/local/config.yml');
        $this->baseUrl = rtrim(str_replace("index.php", "", $_SERVER['PHP_SELF']), "/");
        if (substr($this->baseUrl, 0, 4) == "/web") {
            $this->baseUrl = substr($this->baseUrl, 4);
        }
        if (strpos("" . $this->route, "" . $this->config['adminPath']) === 0) {
            $this->contentDir = $this->baseDir . "/core/admin";
        } else {
            $this->contentDir = $this->baseDir . "/content";
        }
        $this->route = rtrim($this->requestUri, "/");
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
            } else if($pathInfo['extension'] === "map") { // .css.map
                header('Content-type: application/json');
            } else {
                Logger::log("b128 - Unknown content type for " . $pathInfo['extension']);
                exit();
            }
            /** @noinspection PhpIncludeInspection */
            include($this->baseDir . $this->route);
            exit();
        }
        Logger::setActive($this->config['logging']);
        Logger::log("---");
        Logger::log("request: " . $this->requestUri);
        $this->globals = Yaml::parseFile($this->contentDir . '/globals.yml');
        if (!$this->route || is_dir($this->contentDir . '/pages' . $this->route)) {
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
     * The CMS root in file system
     * @return string
     */
    public function getBaseDir(): string
    {
        return $this->baseDir;
    }

    /**
     * The folder containing the website content
     * @return string
     */
    public function getContentDir(): string
    {
        return $this->contentDir;
    }

    /**
     * The base URL, requests got to `/web` and sub folders
     * @return string
     */
    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * The current request uri
     * @return string
     */
    public function getRequestUri(): string
    {
        return $this->requestUri;
    }

    /**
     * The route in `/web` or `/content/pages`
     * @return string
     */
    public function getRoute(): string
    {
        return $this->route;
    }

    /**
     * Global values, defined in `/content/globals.yml`
     * @return array
     */
    public function getGlobals(): array
    {
        return $this->globals;
    }

    /**
     * Local configuration, defined in `/local/config.yml`
     * @return array
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * The  current theme
     * @return Theme
     */
    public function getTheme(): Theme
    {
        return $this->theme;
    }

}