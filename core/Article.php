<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

namespace Shaack\Reboot;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

require "Block.php";

class Article
{
    /**
     * @param string $route
     * @return string
     */
    public function render($route = null)
    {
        global $reboot;
        if (!$route) {
            $route = $reboot->route;
        }
        $articlePrefix = $reboot->baseDir . '/local/articles' . $route;
        if (file_exists($articlePrefix . ".md")) {
            return $this->renderMarkdown($articlePrefix . ".md");
        } else if (file_exists($articlePrefix . ".php")) {
            return $this->renderPHP($articlePrefix . ".php");
        } else {
            // not found
            log("article not found (404)");
            http_response_code(404);
            if (file_exists($reboot->baseDir . '/local/articles/404.md') ||
                file_exists($reboot->baseDir . '/local/articles/404.php')) {
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
        global $reboot;
        log("article: " . $articlePath);
        $rawContent = file_get_contents($articlePath);

        // find blocks
        $offset = 0;
        $blocks = array();
        do {
            preg_match('/<!--(.*)-->(.*)(<!--|$)/sU', $rawContent, $matches, 0, $offset);
            if ($matches) {
                $offset += strlen($matches[0]) - 4;
                try {
                    $blockConfig = Yaml::parse(trim($matches[1]));
                    $blockContent = trim($matches[2]);
                    $blockName = $blockConfig['block'];
                    log("found block: " . $blockName);
                    // log("config: " . print_r($blockConfig, true));
                    // log("content: " . $blockContent);
                    $block = new Block($blockName, $blockContent, $blockConfig);
                    $blocks[] = $block;
                } catch (ParseException $e) {
                    log("could not parse block config: " . trim($matches[1]));
                }
            }
        } while ($matches);

        if(!count($blocks)) {
            // interpret whole content as flat file
            $block = new Block("markdown", $rawContent);
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
    public function renderPHP($articlePath)
    {
        log("article: " . $articlePath);
        ob_start();
        /** @noinspection PhpIncludeInspection */
        include $articlePath;
        $contents = ob_get_contents();
        ob_end_clean();
        return $contents;
    }

}