<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot
 * License: MIT, see file 'LICENSE'
 */

class Page
{
    private $template;
    private $article;

    /**
     * Page constructor.
     * @param \Shaack\Reboot\Template $template
     * @param \Shaack\Reboot\Article $article
     */
    public function __construct($template, $article)
    {
        $this->template = $template;
        $this->article = $article;
    }

    /**
     * @param \Shaack\Reboot\Reboot $reboot
     */
    public function render($reboot)
    {

    }
}
