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

    public function __construct($name, $content = "", $config = array())
    {
        $this->name = $name;
        $this->content = $content;
        $this->config = $config;
    }

    public function render()
    {
        global $reboot;
        // render template
        ob_start();
        /** @noinspection PhpIncludeInspection */
        include $reboot->baseDir . '/local/blocks/' . $this->name . ".php";
        $contents = ob_get_contents();
        ob_end_clean();
        return $contents;
    }

    public function value($name)
    {
        return $this->config['values'][$name];
    }

    public function content()
    {
        return $this->content;
    }
}