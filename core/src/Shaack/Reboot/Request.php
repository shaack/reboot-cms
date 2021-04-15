<?php

namespace Shaack\Reboot;

class Request
{
    private $path;
    private $queryParams;

    /**
     * @param $uri
     */
    public function __construct($uri)
    {
        $parsed = parse_url($uri);
        if (array_key_exists("query", $parsed)) {
            parse_str($parsed["query"], $this->queryParams);
        } else {
            $this->queryParams = [];
        }
        $this->path = $parsed["path"];
    }

    public function getPath()
    {
        return $this->path;
    }

    public function getParam($name)
    {
        if (array_key_exists($name, $this->queryParams)) {
            return $name;
        } else {
            return null;
        }
    }
}