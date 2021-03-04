<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

namespace Shaack\Reboot;

class Block
{
    private $name;
    private $content;
    private $config;
    private $reboot;

    public function __construct($reboot, $name, $content = "", $config = array())
    {
        $this->name = $name;
        $this->content = $content;
        $this->config = $config;
        $this->reboot = $reboot;
    }

    public function render()
    {
        ob_start();
        /** @noinspection PhpIncludeInspection */
        include $this->reboot->baseDir . '/themes/' . $this->reboot->config['theme'] . '/blocks/' . $this->name . ".php";
        $contents = ob_get_contents();
        ob_end_clean();
        return $contents;
    }

    public function config($name)
    {
        return @$this->config[$name];
    }

    public function value($name)
    {
        return @$this->config['values'][$name];
    }

    public function content()
    {
        if($this->content) {
            return $this->reboot->parsedown->parse($this->content);
        } else {
            return "";
        }
    }
}