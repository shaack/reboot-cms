<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

namespace Shaack\Reboot;

use Shaack\Utils\Logger;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class Page
{
    /** @var Reboot */
    private $reboot;

    /**
     * Page constructor.
     * @param Reboot $reboot
     */
    public function __construct(Reboot $reboot)
    {
        $this->reboot = $reboot;
    }

    /**
     * @param string|null $route
     * @return string
     */
    public function render(string $route = null): string
    {
        if (!$route) {
            $route = $this->reboot->getRoute();
        }
        $articlePrefix = $this->reboot->getContentDir() . '/pages' . $route;
        if (file_exists($articlePrefix . ".md")) {
            return $this->renderMarkdown($articlePrefix . ".md");
        } else if (file_exists($articlePrefix . ".php")) {
            return $this->renderPHP($articlePrefix . ".php");
        } else {
            // not found
            Logger::error("article not found (404)");
            http_response_code(404);
            if (file_exists($this->reboot->getContentDir() . '/pages/404.md') ||
                file_exists($this->reboot->getContentDir() . '/pages/404.php')) {
                return $this->render("/404"); // put a 404 file in /pages to create your own
            } else {
                return "<div class='container'><h1>404</h1><p>Page not found.</p></div>";
            }
        }
    }

    /**
     * @param string $pagePath
     * @return string
     */
    private function renderMarkdown(string $pagePath): string
    {
        Logger::info("Markdown Page: " . $pagePath);
        $content = file_get_contents($pagePath);

        // encode code blocks
        $content = preg_replace_callback('/```(.*?)```/s', function($matches) {
            return "```" . base64_encode($matches[1]) . "```";
        }, $content);
        $content = preg_replace_callback('/`(.*?)`/s', function($matches) {
            return "`" . base64_encode($matches[1]) . "`";
        }, $content);

        // find blocks
        $offset = 0;
        $blocks = array();
        do {
            preg_match('/<!--(.*)-->(.*)(<!--|$)/sU', $content, $matches, 0, $offset);
            if ($matches) {
                $offset += strlen($matches[0]) - 4;
                try {
                    $blockConfig = Yaml::parse(trim($matches[1]));
                    $blockContent = trim($matches[2]);
                    $blockName = null;
                    if(is_string($blockConfig)) {
                        $blockName = $blockConfig;
                        $blockConfig = [];
                    } else {
                        $blockName = array_keys($blockConfig)[0];
                        $blockConfig = $blockConfig[$blockName];
                    }
                    Logger::info("found block: " . $blockName);
                    // unescape code blocks
                    $blockContent = preg_replace_callback('/```(.*?)```/s', function($matches) {
                        return "```" . base64_decode($matches[1]) . "```";
                    }, $blockContent);
                    $blockContent = preg_replace_callback('/`(.*?)`/s', function($matches) {
                        return "`" . base64_decode($matches[1]) . "`";
                    }, $blockContent);
                    $block = new Block($this->reboot, $this, $blockName, $blockContent, $blockConfig);
                    $blocks[] = $block;
                } catch (ParseException $e) {
                    Logger::error("Error: could not parse block config: " . trim($matches[1]));
                }
            }
        } while ($matches);

        if (!count($blocks)) {
            // interpret whole pages as flat markdown file
            $block = new Block($this->reboot, $this, "text", $content);
            $blocks[] = $block;
        }

        // render blocks
        $html = "";
        foreach ($blocks as $block) {
            $html .= $block->render();
        }

        return $html;
    }

    /**
     * @param $articlePath
     * @return string
     */
    private function renderPHP($articlePath): string
    {
        Logger::info("PHP Page: " . $articlePath);
        return renderPHPPage($this->reboot, $this, $articlePath);
    }

}

/** @noinspection PhpUnusedParameterInspection */
function renderPHPPage(Reboot $reboot, Page $page, string $path) {
    ob_start();
    /** @noinspection PhpIncludeInspection */
    include $path;
    $contents = ob_get_contents();
    ob_end_clean();
    return $contents;
}

