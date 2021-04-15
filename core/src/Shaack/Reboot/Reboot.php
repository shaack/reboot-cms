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
    private $request; // The current request, contains ["path"] and ["query"]
    private $route; // The route in `/content/pages` (…or `/web`)
    private $globals; // Global values, defined in `/content/globals.yml`
    private $config; // Local configuration, defined in `/local/config.yml`
    private $theme; // The  current theme
    private $adminSession;

    /**
     * Reboot constructor
     * @param string $uri
     * @param string $baseDir
     */
    public function __construct(string $uri, string $baseDir)
    {
        $this->request = new Request($uri);
        $this->baseDir = $baseDir;
        $this->config = Yaml::parseFile($this->baseDir . '/local/config.yml');
        Logger::setLevel($this->config['logLevel']);
        $this->baseUrl = rtrim(str_replace("index.php", "", $_SERVER['PHP_SELF']), "/");
        if (substr($this->baseUrl, 0, 4) == "/web") {
            $this->baseUrl = substr($this->baseUrl, 4);
        }
        $this->route = rtrim($this->request->getPath(), "/");
        $this->adminSession = new AdminSession($this);
        if (strpos($this->route, $this->config['adminPath']) === 0) {
            $this->contentDir = $this->baseDir . "/core/admin";
            $this->route = "/" . ltrim($this->request->getPath(), $this->config['adminPath']);
        } else {
            $this->contentDir = $this->baseDir . "/content";
        }
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
                Logger::error("b128 - Unknown content type for " . $pathInfo['extension']);
                exit();
            }
            /** @noinspection PhpIncludeInspection */
            include($this->baseDir . $this->route);
            exit();
        }
        Logger::info("---");
        Logger::info("path: " . $this->request->getPath());
        $this->globals = Yaml::parseFile($this->contentDir . '/globals.yml');
        if (!$this->route || is_dir($this->contentDir . '/pages' . $this->route)) {
            $this->route = $this->route . "/index";
        }
        Logger::info("route: " . $this->route);
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
        Logger::info("=> redirect: " . $url);
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
     * @return Request
     */
    public function getRequest(): Request
    {
        return $this->request;
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

    public function getAdminSession(): AdminSession
    {
        return $this->adminSession;
    }

}