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

    /**
     * Article constructor.
     * @param Reboot $reboot
     */
    public function __construct($reboot)
    {
        $this->reboot = $reboot;
    }

    /**
     * @param string $route
     * @return string
     */
    public function render($route = null)
    {
        if (!$route) {
            $route = $this->reboot->route;
        }
        $articlePrefix = $this->reboot->baseDir . '/local/articles' . $route;
        if (file_exists($articlePrefix . ".md")) {
            return $this->renderMarkdown($articlePrefix . ".md");
        } else if (file_exists($articlePrefix . ".php")) {
            return $this->renderPHP($articlePrefix . ".php");
        } else {
            // not found
            $this->reboot->log("article not found (404)");
            http_response_code(404);
            if (file_exists($this->reboot->baseDir . '/local/articles/404.md') ||
                file_exists($this->reboot->baseDir . '/local/articles/404.php')) {
                return $this->render("/404");
            } else {
                return "<h1>404</h1><p>Page not found.</p>";
            }
        }
    }

    /**
     * @param $articlePath
     * @return string
     */
    public function renderMarkdown($articlePath)
    {
        $this->reboot->log("article: " . $articlePath);
        $rawContent = file_get_contents($articlePath);
        $html = $this->reboot->parsedown->parse($rawContent);
        if ($this->reboot->config->markdown_wrap) {
            return str_replace("|", $html, $this->reboot->config->markdown_wrap);
        } else {
            return $html;
        }
    }

    /**
     * @param $articlePath
     * @return string
     */
    public function renderPHP($articlePath)
    {
        $this->reboot->log("article: " . $articlePath);
        ob_start();
        /** @noinspection PhpIncludeInspection */
        include $articlePath;
        $contents = ob_get_contents();
        ob_end_clean();
        return $contents;
    }

}