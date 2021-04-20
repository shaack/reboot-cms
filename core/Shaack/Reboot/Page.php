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
    private $reboot;
    private $site;

    /**
     * Page constructor.
     * @param Reboot $reboot
     * @param Site $site
     */
    public function __construct(Reboot $reboot, Site $site)
    {
        $this->reboot = $reboot;
        $this->site = $site;
    }

    /**
     * @param string $path
     * @return string
     */
    public function render(string $path): string
    {
        $pagePrefix = $this->site->getFsPath() . '/pages' . $path;
        if(is_dir($pagePrefix)) {
            $pagePrefix .= "/index";
        }
        if (file_exists($pagePrefix . ".md")) {
            return $this->renderMarkdown($pagePrefix . ".md");
        } else if (file_exists($pagePrefix . ".php")) {
            return $this->renderPHP($pagePrefix . ".php");
        } else {
            // not found
            Logger::error("page not found (404): " . $pagePrefix);
            http_response_code(404);
            if (file_exists($this->site->getFsPath() . '/pages/404.md') ||
                file_exists($this->site->getFsPath() . '/pages/404.php')) {
                return $this->render("/404"); // put a 404 file in /pages to create your own
            }
            return "";
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
                    $blockProps = Yaml::parse(trim($matches[1]));
                    $blockContent = trim($matches[2]);
                    $blockName = null;
                    if(is_string($blockProps)) {
                        $blockName = $blockProps;
                        $blockProps = [];
                    } else {
                        $blockName = array_keys($blockProps)[0];
                        $blockProps = $blockProps[$blockName];
                    }
                    Logger::info("found block: " . $blockName);
                    // unescape code blocks
                    $blockContent = preg_replace_callback('/```(.*?)```/s', function($matches) {
                        return "```" . base64_decode($matches[1]) . "```";
                    }, $blockContent);
                    $blockContent = preg_replace_callback('/`(.*?)`/s', function($matches) {
                        return "`" . base64_decode($matches[1]) . "`";
                    }, $blockContent);
                    $block = new Block($this->site, $blockName, $blockContent, $blockProps);
                    $blocks[] = $block;
                } catch (ParseException $e) {
                    Logger::error("Error: could not parse block config: " . trim($matches[1]));
                }
            }
        } while ($matches);

        if (!count($blocks)) {
            // interpret whole pages as flat markdown file
            $block = new Block($this->site, "text", $content);
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
        return renderPHPPage($this->reboot, $this->site, $this, $articlePath);
    }

}

/** @noinspection PhpUnusedParameterInspection */
function renderPHPPage(Reboot $reboot, Site $site, Page $page, string $path) {
    ob_start();
    /** @noinspection PhpIncludeInspection */
    include $path;
    $contents = ob_get_contents();
    ob_end_clean();
    return $contents;
}

