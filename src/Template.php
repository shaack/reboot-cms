<?php
namespace Shaack\Reboot;

/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */
class Template
{
    /** @var Page */
    private $article;
    /** @var Reboot */
    private $reboot;

    /**
     * Page constructor.
     * @param Reboot $reboot
     * @param Page $article
     */
    public function __construct($reboot, $article)
    {
        $this->article = $article;
        $this->reboot = $reboot;
    }

    /**
     * @return string
     */
    public function render()
    {
        // render template
        ob_start();
        /** @noinspection PhpIncludeInspection */
        include $this->reboot->baseDir . '/themes/' . $this->reboot->config['theme'] . '/template.php';
        $contents = ob_get_contents();
        ob_end_clean();
        return $contents;
    }
}
