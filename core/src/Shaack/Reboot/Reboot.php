<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

namespace Shaack\Reboot;

use Shaack\Logger;
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
        // Silence diagnostics before anything else runs. A missing or broken
        // `/local/config.yml` would otherwise print its message, including the server path,
        // before the configuration has been read.
        ini_set('display_errors', '0');
        ini_set('log_errors', '1');
        $this->baseFsPath = $baseDir;
        $this->config = Yaml::parseFile($this->baseFsPath . '/local/config.yml');
        Logger::setLevel($this->config['logLevel'] ?? 2);
        $this->configureErrorDisplay();
        Logger::debug("Reboot CMS");
        Logger::debug("you should set the loglevel to 2 (error) in production (/local/config.yml)");
        Logger::info("--- " . $_SERVER["REQUEST_URI"]);
        $this->baseWebPath = preg_replace('/(\/web)?(\/admin)?\/index\.php$/', '', $_SERVER['PHP_SELF']);
        Logger::debug("reboot->baseFsPath: " . $this->baseFsPath);
        Logger::debug("reboot->baseWebPath: " . $this->baseWebPath);
        $site = new Site($this, $sitePath, $siteWebPath);
        $request = new Request($site, $this->baseWebPath, $_SERVER["REQUEST_URI"], $_POST);
        echo $site->render($request);
    }

    /**
     * Decide whether PHP notices, warnings and fatals are printed into the response.
     *
     * A diagnostic message in the page body is never useful to a visitor, and it discloses
     * the absolute server path. Unless `displayErrors` says otherwise in `/local/config.yml`,
     * messages are printed only in debug mode (logLevel 0). They are always written to the
     * error log, which is where the Logger writes as well.
     */
    private function configureErrorDisplay(): void
    {
        $display = $this->config['displayErrors'] ?? (Logger::getLevel() === 0);
        ini_set('display_errors', $display ? '1' : '0');
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