<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot
 * License: MIT, see file 'LICENSE'
 */

namespace Shaack\Reboot;

class Article
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
        $articlePrefix = __DIR__ . '/../local/articles' . $route;
        // error_log("prefix: " . $prefix);
        $isMarkdown = file_exists($articlePrefix . ".md");
        $isPhp = file_exists($articlePrefix . ".php");
        if($isMarkdown) {
            $rawContent =  file_get_contents($articlePrefix . ".md");
            echo $this->reboot->parsedown->parse($rawContent);
        } else if($isPhp) {
            include $articlePrefix . ".php";
        } else {
            $this->render("/404");
        }
    }
}