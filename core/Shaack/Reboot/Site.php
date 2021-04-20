<?php


namespace Shaack\Reboot;


use Shaack\Utils\Logger;
use Symfony\Component\Yaml\Yaml;

class Site
{
    private $reboot;
    private $fsPath; // The path to the `sites` folder, relative to $baseFsPath
    private $webPath; // The web path $domain . $baseUrl . $webPath
    private $config; // Global values for all pages in a sites folder (`/sites/config.yml`)

    /**
     * AdminSite constructor.
     * @param $reboot Reboot
     * @param string $siteName
     * @param string $siteWebPath
     */
    public function __construct(Reboot $reboot, string $siteName, string $siteWebPath)
    {
        $this->reboot = $reboot;
        $this->fsPath = $this->reboot->getBaseFsPath() . "/sites/" . $siteName;
        $this->webPath = $this->reboot->getBaseWebPath() . $siteWebPath;
        $this->config = Yaml::parseFile($this->fsPath . '/config.yml');
        Logger::debug("site->webPath: " . $this->webPath);
    }

    /**
     * @param $request Request
     * @return string
     */
    public function render(Request $request): string
    {
        $page = new Page($this->reboot, $this);
        return renderTemplate($this, $page, $request);
    }

    /**
     * @return string
     */
    public function getFsPath(): string
    {
        return $this->fsPath;
    }

    /**
     * @return string
     */
    public function getWebPath(): string
    {
        return $this->webPath;
    }

    /**
     * @return mixed
     */
    public function getConfig()
    {
        return $this->config;
    }

}

/** @noinspection PhpUnusedParameterInspection */
function renderTemplate(Site $site, Page $page, Request $request)
{
    ob_start();
    /** @noinspection PhpIncludeInspection */
    include $site->getFsPath() . '/template.php';
    $contents = ob_get_contents();
    ob_end_clean();
    return $contents;
}