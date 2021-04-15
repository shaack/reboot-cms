<?php
namespace Shaack\Reboot;

/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */
class Template
{
    private $page;
    private $reboot;

    /**
     * Template constructor.
     * @param Reboot $reboot
     * @param Page $page
     */
    public function __construct(Reboot $reboot, Page $page)
    {
        $this->page = $page;
        $this->reboot = $reboot;
    }

    /**
     * @return string
     */
    public function render(): string
    {
        return renderTemplate($this->reboot, $this->page);
    }
}

/** @noinspection PhpUnusedParameterInspection */
function renderTemplate(Reboot $reboot, Page $page) {
    ob_start();
    /** @noinspection PhpIncludeInspection */
    include $reboot->getBaseDir() . '/themes/' . $reboot->getTheme()->getName() . '/template.php';
    $contents = ob_get_contents();
    ob_end_clean();
    return $contents;
}
