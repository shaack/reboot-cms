<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot
 * License: MIT, see file 'LICENSE'
 */

namespace Shaack\Reboot;

class Page
{
    private $reboot;

    public function __construct($reboot)
    {
        /** @var Reboot $reboot */
        $this->reboot = $reboot;
    }

    public function render($route)
    {
        // error_log("route: " . $route);
        $prefix = __DIR__ . '/../local/pages' . $route;
        // error_log("prefix: " . $prefix);
        $isMarkdown = file_exists($prefix . ".md");
        $isPhp = file_exists($prefix . ".php");
        if($isMarkdown) {
            $rawContent =  file_get_contents($prefix . ".md");
            echo $this->reboot->parsedown->parse($rawContent);
        } else if($isPhp) {
            include $prefix . ".php";
        } else {
            $this->render("/404");
        }
    }
}