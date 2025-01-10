<?php

namespace Shaack\Reboot;

use Shaack\Logger;

class Request
{
    private string $path; // the requestPath, relative to the $baseWebPath
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
        Logger::debug("request->path: " . $this->path);
    }

    /**
     * @return string the requestPath, relative to the domain
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * @param $name
     * @param string|null $method set to "get" or "post" to return only that methods params
     * @return mixed|null
     */
    public function getParam(string $name, string $method = null)
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