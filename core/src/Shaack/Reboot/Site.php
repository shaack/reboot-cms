<?php


namespace Shaack\Reboot;

use Shaack\Logger;
use Shaack\Utils\HttpUtils;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class Site
{
    protected Reboot $reboot;
    protected string $fsPath; // The path to the `site` folder, relative to $baseFsPath
    protected string $webPath; // The web path $domain . $baseUrl . $webPath
    protected array $config = []; // Global values for all pages in a site folder (`/site/config.yml`)
    protected array $addOns = []; // Site addons

    /**
     * @param $reboot Reboot
     * @param string $sitePath
     * @param string $siteWebPath
     */
    public function __construct(Reboot $reboot, string $sitePath, string $siteWebPath)
    {
        $this->reboot = $reboot;
        $this->fsPath = $this->reboot->getBaseFsPath() . $sitePath;
        $this->webPath = $this->reboot->getBaseWebPath() . $siteWebPath;
        $file = $this->fsPath . '/config.yml';
        try {
            $this->config = Yaml::parseFile($file);
        } catch (ParseException $e) {
            error_log("parse exception " . $file);
        }
        // addOns
        if (@$this->config["addons"]) {
            foreach ($this->config["addons"] as $addOnName) {
                $addOnPath = $this->getFsPath() . "/addons/" . $addOnName . ".php";
                require $addOnPath;
                $className = "\Shaack\Reboot\\" . $addOnName;
                $this->addOns[$addOnName] = new $className($this->reboot, $this);
            }
        }
        Logger::debug("site->webPath: " . $this->webPath);
        Logger::debug("site->fsPath: " . $this->fsPath);
    }

    /**
     * @param $request Request
     * @return string
     */
    public function render(Request $request): string
    {
        /** @var AddOn $addOn */
        foreach ($this->addOns as $addOn) {
            if (!$addOn->preRender($request)) {
                return "";
            }
        }
        $page = new Page($this->reboot, $this);
        $content = renderPage($this, $page, $request);
        foreach ($this->addOns as $addOn) {
            $content = $addOn->postRender($request, $content);
        }
        return $content;
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

    public function getAddOn($addOnName)
    {
        return $this->addOns[$addOnName];
    }
}

/** @noinspection PhpUnusedParameterInspection */
function renderPage(Site $site, Page $page, Request $request): string
{
    $templateName = "template";
    if(@$page->getConfig()["template"]) {
        $templateName = $page->getConfig()["template"];
    }
    $templateName = HttpUtils::sanitizeFileName($templateName);
    ob_start();
    include $site->getFsPath() . '/' . $templateName . '.php';
    $contents = ob_get_contents();
    ob_end_clean();
    return $contents;
}