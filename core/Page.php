<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot
 * License: MIT, see file 'LICENSE'
 */

namespace Shaack\Reboot;

class Page
{
    private $template;
    private $reboot;
    private $parsedown;
    private $slices;

    private $contents;
    private $parsed;

    public function __construct($reboot)
    {
        $this->reboot = $reboot;
        $this->parsedown = new \Parsedown();
        // create slices

    }

    public function render()
    {

    }

    private function parsePage($path) {
        $this->contents = file_get_contents(__DIR__ . '../local/pages/' . $path);
        $this->parsed = $this->parsedown($this->contents);
    }
}