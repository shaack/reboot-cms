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
    private $baseFsPath; // the base/root dir of this reboot-cms in the file system, full path
    private $baseWebPath; // the base url "https://" . [domain] . [baseWebPath}
    private $config; // Local configuration, defined in `/local/config.yml`

    /**
     * @param string $baseDir
     * @param string $siteName
     * @throws Exception
     */
    public function __construct(string $baseDir, string $siteName)
    {
        $this->baseFsPath = $baseDir;
        $this->config = Yaml::parseFile($this->baseFsPath . '/local/config.yml');
        Logger::setLevel($this->config['logLevel']);
        Logger::info("--- " . $_SERVER["REQUEST_URI"]);
        $this->baseWebPath = preg_replace('/(\/web)?(\/admin)?\/index\.php$/', '', $_SERVER['PHP_SELF']);
        Logger::debug("reboot->baseFsPath: " . $this->baseFsPath);
        Logger::debug("reboot->baseWebPath: " . $this->baseWebPath);
        if($siteName === "default") {
            $site = new Site($this, $siteName, "");
        } else {
            $extensionPath = $this->getBaseFsPath() . "/sites/" . $siteName . "/SiteExtension.php";
            if(file_exists($extensionPath)) {
                Logger::info("Found SiteExtension.php, loading");
                require $extensionPath;
                $site = new SiteExtension($this, $siteName, "/" . $siteName);
            } else {
                $site = new Site($this, $siteName, "/" . $siteName);
            }
        }
        $request = new Request($this->baseWebPath, $_SERVER["REQUEST_URI"], $_POST, $site);
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