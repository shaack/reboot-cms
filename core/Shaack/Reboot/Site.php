<?php


namespace Shaack\Reboot;


use Shaack\Utils\Logger;
use Symfony\Component\Yaml\Yaml;

class Site
{
    protected $reboot;
    protected $name;
    protected $fsPath; // The path to the `sites` folder, relative to $baseFsPath
    protected $webPath; // The web path $domain . $baseUrl . $webPath
    protected $config; // Global values for all pages in a sites folder (`/sites/config.yml`)

    /**
     * AdminSite constructor.
     * @param $reboot Reboot
     * @param string $siteName
     * @param string $siteWebPath
     */
    public function __construct(Reboot $reboot, string $siteName, string $siteWebPath)
    {
        $this->reboot = $reboot;
        $this->name = $siteName;
        $this->fsPath = $this->reboot->getBaseFsPath() . "/sites/" . $siteName;
        $this->webPath = $this->reboot->getBaseWebPath() . $siteWebPath;
        $this->config = Yaml::parseFile($this->fsPath . '/config.yml');
        Logger::debug("site->name: " . $siteName);
        Logger::debug("site->webPath: " . $this->webPath);
        Logger::debug("site->fsPath: " . $this->fsPath);
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

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
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