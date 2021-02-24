<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

class Page
{
    private $article;

    /**
     * Page constructor.
     * @param \Shaack\Reboot\Article $article
     */
    public function __construct($article)
    {
        $this->article = $article;
    }

    /**
     * @param string $template
     * @return string
     */
    public function render($template = "default")
    {
        global $reboot;
        // render template
        ob_start();
        /** @noinspection PhpIncludeInspection */
        include $reboot->baseDir . '/themes/' . $reboot->website['theme'] . '/templates/' . $template . ".php";
        $contents = ob_get_contents();
        ob_end_clean();
        return $contents;
    }
}
