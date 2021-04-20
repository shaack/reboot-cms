<?php

namespace Shaack\Reboot;

use Shaack\Utils\Logger;

class Request
{
    private $path; // the requestPath, relative to the $baseWebPath
    private $paramsGet; // http get query params as array
    private $paramsPost; // http post params

    public function __construct($baseWebPath, $requestUri, $post)
    {
        $parsed = parse_url($requestUri);
        $this->path = rtrim($parsed["path"], "/");
        if (substr($this->path, 0, strlen($baseWebPath)) == $baseWebPath) {
            $this->path = substr($this->path, strlen($baseWebPath));
        }
        if (array_key_exists("query", $parsed)) {
            parse_str($parsed["query"], $this->paramsGet);
        } else {
            $this->paramsGet = [];
        }
        $this->paramsPost = array_merge($this->paramsGet, @$post);
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
    public function getParam($name, $method = null)
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

    /**
     * The route in `/web` or `/sites/pages`
     * @return string
     */
    public function getRoute(): string
    {
        return $this->route;
    }
}