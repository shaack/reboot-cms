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
        $articlePrefix = $this->reboot->baseDir . '/local/articles' . $route;
        $this->reboot->log("prefix: " . $articlePrefix);
        if (file_exists($articlePrefix . ".md")) {
            // is markdown
            $rawContent = file_get_contents($articlePrefix . ".md");
            return $this->reboot->parsedown->parse($rawContent);
        } else if (file_exists($articlePrefix . ".php")) {
            // is php
            ob_start();
            /** @noinspection PhpIncludeInspection */
            include $articlePrefix . ".php";
            $contents = ob_get_contents();
            ob_end_clean();
            return $contents;
        } else {
            // not found
            return $this->render("/404");
        }
    }
}