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
    protected array $collections = []; // collections read in this request, by name

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
     * The entries of `site/data/<name>/` without drafts and invalid entries,
     * sorted as `site/blueprints/<name>.yml` says. Read once per request.
     */
    public function collection(string $name): Collection
    {
        return $this->collections[$name] ??= Collection::load($this, $name);
    }

    /**
     * The languages of the site, `languages: [en, de]` in `site/config.yml`,
     * empty for a site in one language.
     * @return string[]
     */
    public function getLanguages(): array
    {
        return array_values(array_filter(array_map("strval", (array)($this->config["languages"] ?? [])),
            fn($language) => preg_match('/^[a-z]{2,3}(-[A-Za-z0-9]{2,8})?$/', $language) === 1));
    }

    /**
     * The language of the paths without a language prefix, `defaultLanguage` in
     * `site/config.yml`, else the first of `languages`, else "en".
     */
    public function getDefaultLanguage(): string
    {
        return (string)($this->config["defaultLanguage"] ?? ($this->getLanguages()[0] ?? "en"));
    }

    /**
     * Whether the site has more than one language.
     */
    public function isMultilingual(): bool
    {
        return count($this->getLanguages()) > 1;
    }

    /**
     * The language of a path and the path without its language prefix,
     * `/de/contact` gives `["de", "/contact", true]`, `/contact` gives
     * `["en", "/contact", false]` on a site with the default language "en".
     * @return array [string $language, string $path, bool $prefixed]
     */
    public function splitLanguage(string $path): array
    {
        $segments = explode("/", ltrim($path, "/"), 2);
        if ($segments[0] !== "" && in_array($segments[0], $this->getLanguages(), true)) {
            return [$segments[0], "/" . ($segments[1] ?? ""), true];
        }
        return [$this->getDefaultLanguage(), $path, false];
    }

    /**
     * The path of a page in a language, `/contact` in "de" is `/de/contact`.
     * The default language has no prefix.
     */
    public function languagePath(string $path, string $language): string
    {
        if ($language === $this->getDefaultLanguage() || !in_array($language, $this->getLanguages(), true)) {
            return $path;
        }
        return "/" . $language . ($path === "/" ? "/" : $path);
    }

    /**
     * The address of the page of a request in every language of the site, for
     * a language switcher and `hreflang` links:
     *
     * ```php
     * <?php foreach ($site->languageUrls($request) as $language => $url) { ?>
     *     <link rel="alternate" hreflang="<?= $language ?>" href="<?= $url ?>">
     * <?php } ?>
     * ```
     * @return array language => web path
     */
    public function languageUrls(Request $request): array
    {
        $urls = [];
        foreach ($this->getLanguages() as $language) {
            $urls[$language] = $this->webPath . $this->languagePath($request->getPathWithoutLanguage(), $language);
        }
        return $urls;
    }

    /**
     * The address and language of a page file in `site/pages/`, `/about.de.md`
     * is `["/about", "de"]`, `/blog/index.yml` is `["/blog/", <default>]`. The
     * address is without the language prefix, see languagePath().
     * @return array [string $path, string $language]
     */
    public function pageFileAddress(string $file): array
    {
        $name = preg_replace('/\.(md|yml|php)$/', '', $file);
        $language = $this->getDefaultLanguage();
        if (preg_match('/^(.*)\.([^.\/]+)$/', $name, $matches) && in_array($matches[2], $this->getLanguages(), true)) {
            $name = $matches[1];
            $language = $matches[2];
        }
        return [preg_replace('/\/index$/', '/', $name), $language];
    }

    /**
     * The values of a structured page in one language. A value with a key per
     * language, `{en: Welcome, de: Willkommen}`, becomes the one of the language,
     * else the one of the default language, else the first. Everything else
     * holds for all languages. Lists and maps are resolved value by value.
     */
    public function localize($value, string $language)
    {
        if (!is_array($value)) {
            return $value;
        }
        if ($this->isLanguageMap($value)) {
            return $value[$language] ?? $value[$this->getDefaultLanguage()] ?? reset($value);
        }
        foreach ($value as $key => $item) {
            $value[$key] = $this->localize($item, $language);
        }
        return $value;
    }

    /**
     * Whether a value is one value per language, `{en: Welcome, de: Willkommen}`:
     * a map whose keys are all languages of the site and whose values are no maps.
     */
    public function isLanguageMap($value): bool
    {
        if (!is_array($value) || $value === [] || array_is_list($value) || !$this->isMultilingual()) {
            return false;
        }
        foreach ($value as $key => $item) {
            if (!in_array((string)$key, $this->getLanguages(), true) || is_array($item)) {
                return false;
            }
        }
        return true;
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