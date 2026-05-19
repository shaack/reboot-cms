<?php

namespace Shaack\Reboot;

use Shaack\Logger;

class Request
{
    private string $path; // the requestPath, relative to the $baseWebPath
    private string $language; // language code from the path prefix, or the site's default language
    private bool $pathHasLanguagePrefix = false; // whether $path starts with a configured language segment
    private array $paramsGet = []; // http post params
    private array $paramsPost = []; // http post params

    public function __construct(Site $site, string $baseWebPath, string $requestUri, array $post)
    {
        $parsed = parse_url($requestUri);
        $this->path = rtrim($parsed["path"], "/");
        if (substr($this->path, 0, strlen($baseWebPath)) == $baseWebPath) {
            $this->path = substr($this->path, strlen($baseWebPath));
            if (!$this->path) {
                $this->path = "/";
            }
        }
        $this->paramsPost = $post;
        // remove site webPath from request path
        if ($site->getWebPath()) {
            if (substr($this->path, 0, strlen($site->getWebPath())) == $site->getWebPath()) {
                $this->path = substr($this->path, strlen($site->getWebPath()));
            }
        }
        /*
        if($site->getName() !== "default") {
            $siteRelPath = "/" . $site->getName();
            if (substr($this->path, 0, strlen($siteRelPath)) == $siteRelPath) {
                $this->path = substr($this->path, strlen($siteRelPath));
            }
        }
        */
        /*
        if (array_key_exists("query", $parsed)) {
            parse_str($parsed["query"], $this->paramsGet);
        } else {
            $this->paramsGet = [];
        }
        */
        if (array_key_exists("query", $parsed)) {
            parse_str($parsed["query"], $this->paramsGet);
        } else {
            $this->paramsGet = [];
        }
        // $this->paramsPost = array_merge($this->paramsGet, @$post);
        // detect the request language from the first path segment (for multilingual sites)
        $siteConfig = $site->getConfig();
        $languages = $siteConfig['languages'] ?? [];
        $this->language = $siteConfig['defaultLanguage'] ?? ($languages[0] ?? 'en');
        $firstSegment = explode('/', ltrim($this->path, '/'))[0];
        if ($firstSegment !== '' && in_array($firstSegment, $languages, true)) {
            $this->language = $firstSegment;
            $this->pathHasLanguagePrefix = true;
        }
        Logger::debug("request->path: " . $this->path);
        Logger::debug("request->language: " . $this->language);
    }

    /**
     * @return string the requestPath, relative to the domain
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * The language code for this request.
     *
     * On multilingual sites the language is taken from the first path segment
     * (e.g. "/de/contact" => "de"). If the path has no language prefix, the
     * site's default language is returned. Configure the available languages
     * with `languages` and `defaultLanguage` in `site/config.yml`.
     *
     * @return string e.g. "en" or "de"
     */
    public function getLanguage(): string
    {
        return $this->language;
    }

    /**
     * The request path with the language prefix removed.
     *
     * Useful for building language switcher links and `hreflang` tags.
     * For "/de/contact" this returns "/contact"; for a path without a
     * language prefix the path is returned unchanged.
     *
     * @return string
     */
    public function getPathWithoutLanguage(): string
    {
        if ($this->pathHasLanguagePrefix) {
            $path = substr($this->path, strlen($this->language) + 1);
            return $path === '' ? '/' : $path;
        }
        return $this->path;
    }

    /**
     * @param $name
     * @param string|null $method set to "get" or "post" to return only that methods params
     * @return mixed|null
     */
    public function getParam(string $name, ?string $method = null)
    {
        if ($method == null || strtolower($method) == "post") {
            if (array_key_exists($name, $this->paramsPost)) {
                return $this->paramsPost[$name];
            }
        }
        if ($method == null || strtolower($method) == "get") {
            if (array_key_exists($name, $this->paramsGet)) {
                return $this->paramsGet[$name];
            }
        }
        return null;
    }

    public function __toString()
    {
        return "[Request], path: " . $this->getPath();
    }


}