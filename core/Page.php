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
    private $reboot;

    /**
     * Page constructor.
     * @param \Shaack\Reboot\Reboot $reboot
     * @param \Shaack\Reboot\Template $template
     * @param \Shaack\Reboot\Article $article
     */
    public function __construct($reboot, $template, $article)
    {
        $this->reboot = $reboot;
        $this->template = $template;
        $this->article = $article;
    }

    /**
     * @param \Shaack\Reboot\Reboot $reboot
     * @return string
     */
    public function render()
    {
        return $this->article->render($this->reboot->route);
    }
}
