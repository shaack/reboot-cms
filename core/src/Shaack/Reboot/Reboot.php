<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

namespace Shaack\Reboot;

use Exception;
use Shaack\Utils\Logger;
use Symfony\Component\Yaml\Yaml;

class Reboot
{
    private string $baseFsPath; // the base/root dir of this reboot-cms in the file system, full path
    private string $baseWebPath; // the base url "https://" . [domain] . [baseWebPath}
    private array $config; // Local configuration, defined in `/local/config.yml`

    /**
     * A path does NOT end with a "/". The root web path is ""
     * @param string $baseDir
     * @param string $sitePath
     * @param string $siteWebPath
     */
    public function __construct(string $baseDir, string $sitePath, string $siteWebPath = "")
    {
        $this->baseFsPath = $baseDir;
        $this->config = Yaml::parseFile($this->baseFsPath . '/local/config.yml');
        Logger::setLevel($this->config['logLevel']);
        Logger::info("--- " . $_SERVER["REQUEST_URI"]);
        $this->baseWebPath = preg_replace('/(\/web)?(\/admin)?\/index\.php$/', '', $_SERVER['PHP_SELF']);
        Logger::debug("reboot->baseFsPath: " . $this->baseFsPath);
        Logger::debug("reboot->baseWebPath: " . $this->baseWebPath);
        $site = new Site($this, $sitePath, $siteWebPath);
        $request = new Request($site, $this->baseWebPath, $_SERVER["REQUEST_URI"], $_POST);
        echo $site->render($request);
    }

    // Public API

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
    public function getBaseFsPath(): string
    {
        return $this->baseFsPath;
    }

    /**
     * The base URL, requests got to `/web` and sub folders
     * @return string
     */
    public function getBaseWebPath(): string
    {
        return $this->baseWebPath;
    }

    /**
     * Local configuration, defined in `/local/config.yml`
     * @return array
     */
    public function getConfig(): array
    {
        return $this->config;
    }

}