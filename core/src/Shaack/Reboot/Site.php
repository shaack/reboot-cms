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
    protected array $missingAddOns = []; // names from `addons` that could not be loaded

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
        foreach ($this->config["addons"] ?? [] as $addOnName) {
            $addOn = AddOnLoader::load($this->reboot, $this, (string)$addOnName);
            if ($addOn !== null) {
                $this->addOns[$addOnName] = $addOn;
            } else {
                $this->missingAddOns[] = (string)$addOnName;
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

    /**
     * Names listed under `addons` in `site/config.yml` for which no AddOn was
     * found or loaded. The admin shows them as an error.
     * @return string[]
     */
    public function getMissingAddOns(): array
    {
        return $this->missingAddOns;
    }

    /**
     * All registered AddOns, keyed by name, in the order of `site/config.yml`.
     * @return AddOn[]
     */
    public function getAddOns(): array
    {
        return $this->addOns;
    }

    /**
     * The PHP file an AddOn registered for `$path` via `AddOn::getPages()`, or
     * null. The first AddOn in the order of `config.yml` wins.
     */
    public function getAddOnPage(string $path): ?string
    {
        foreach ($this->addOns as $addOn) {
            $file = $addOn->getPages()[$path] ?? null;
            if (is_string($file) && is_file($file)) {
                return $file;
            }
        }
        return null;
    }
}

/** @noinspection PhpUnusedParameterInspection */
function renderPage(Site $site, Page $page, Request $request): string
{
    ob_start();
    include $site->getFsPath() . '/template.php'; // The name is hardcoded for now. Maybe configurable in the future.
    $contents = ob_get_contents();
    ob_end_clean();
    return $contents;
}