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
    private array $config; // Local configuration, defined in `/local/config.yml`, overlaid by `/local/config.local.yml`
    private array $configOverrides = []; // top level keys that `/local/config.local.yml` overrides

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
        $this->config = self::loadLocalConfig($this->baseFsPath, $this->configOverrides);
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
        // Weiterleitungen dürfen nicht gecacht werden. mod_expires gibt sonst auch
        // einer 302-Antwort ein max-age mit, und der Browser folgt dann der alten
        // Weiterleitung, ohne den Server zu fragen, etwa nach einem Sprachwechsel.
        header("Cache-Control: no-store, no-cache, must-revalidate");
        header("Expires: 0");
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

    /**
     * The top level keys of the configuration that `/local/config.local.yml`
     * overrides, empty if that file does not exist. For the health page.
     * @return string[]
     */
    public function getConfigOverrides(): array
    {
        return $this->configOverrides;
    }

    /**
     * Reads `/local/config.yml` and lays `/local/config.local.yml` over it, if
     * that file exists. The second file is meant for one machine only, for
     * example a developer's Mailpit, and is ignored by Git, so the shipped
     * `config.yml` stays untouched and an installation runs out of the box.
     *
     * @param string[] $overrides receives the top level keys the local file overrides
     */
    public static function loadLocalConfig(string $baseFsPath, ?array &$overrides = null): array
    {
        $config = Yaml::parseFile($baseFsPath . '/local/config.yml');
        $config = is_array($config) ? $config : [];
        $overrides = [];
        $localFile = $baseFsPath . '/local/config.local.yml';
        if (is_file($localFile)) {
            $local = Yaml::parseFile($localFile);
            if (is_array($local)) {
                $overrides = array_keys($local);
                $config = self::mergeConfig($config, $local);
            }
        }
        return $config;
    }

    /**
     * Lays `$override` over `$base`. Associative arrays are merged key by key,
     * so `mail: { host: ... }` in the local file keeps the other mail settings.
     * Lists such as `editor.tools` are replaced as a whole.
     */
    public static function mergeConfig(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            $isAssoc = is_array($value) && $value !== [] && array_keys($value) !== range(0, count($value) - 1);
            if ($isAssoc && isset($base[$key]) && is_array($base[$key])) {
                $base[$key] = self::mergeConfig($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }
        return $base;
    }

    /**
     * Sends mail as configured in the `mail` section of `/local/config.yml`,
     * see Mailer. Check `isConfigured()` before sending if mail is optional.
     */
    public function getMailer(): Mailer
    {
        return new Mailer($this->config['mail'] ?? []);
    }

}