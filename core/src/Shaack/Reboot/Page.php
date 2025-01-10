<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

namespace Shaack\Reboot;

use Shaack\Logger;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class Page
{
    private $reboot;
    private $site;
    private $config = []; // If the page contains frontmatter, it will be stored here after rendering

    /**
     * @param Reboot $reboot
     * @param Site $site
     */
    public function __construct(Reboot $reboot, Site $site)
    {
        $this->reboot = $reboot;
        $this->site = $site;
    }

    /**
     * @param string|request $pathOrRequest
     * @return string
     */
    public function render(string|request $pathOrRequest): string
    {
        $path = $pathOrRequest;
        $request = null;
        if ($pathOrRequest instanceof Request) {
            $path = $pathOrRequest->getPath();
            $request = $pathOrRequest;
        }
        $requestedFsPath = $this->site->getFsPath() . '/pages' . $path;

        if (is_dir($requestedFsPath)) {
            $requestedFsPath .= "/index";
        }
        $pathInfo = pathinfo($path);
        if (!array_key_exists("extension", $pathInfo)) {
            if (file_exists($requestedFsPath . ".md")) {
                Logger::info("Markdown page: " . $path);
                return $this->renderMarkdown($requestedFsPath . ".md");
            } else if (file_exists($requestedFsPath . ".php")) {
                Logger::info("PHP page: " . $path);
                return $this->renderPHP($requestedFsPath . ".php", $request);
            } else {
                return $this->render404($path);
            }
        }
        return $this->render404($path);
    }

    public function render404($path)
    {
        Logger::info("[404] response for path: " . $path);
        http_response_code(404);
        if (file_exists($this->site->getFsPath() . '/pages/404.md') ||
            file_exists($this->site->getFsPath() . '/pages/404.php')) {
            return $this->render("/404"); // put a 404 file in /pages to create your own
        }
        return "";
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * @param string $pagePath
     * @return string
     */
    private function renderMarkdown(string $pagePath): string
    {
        $content = trim(file_get_contents($pagePath));
        if (!$content) {
            return "";
        }
        // parse frontmatter
        $this->config = [];
        $offset = strpos($content, "---");
        if ($offset == 0) {
            $offset += 3;
            $end = strpos($content, "---", $offset);
            if ($end !== false) {
                $frontmatter = substr($content, $offset, $end - $offset);
                $this->config = Yaml::parse($frontmatter);
                $content = substr($content, $end + 3);
            }
        }
        // remove everything before the first block
        $offset = strpos($content, "<!--");
        $blocks = array();
        if ($offset !== false) {
            // find blocks
            do {
                preg_match('/<!--(.*)-->(.*)(<!--|$)/sU', $content, $matches, 0, $offset);
                if ($matches) {
                    $offset += strlen($matches[0]) - 4;
                    try {
                        $blockProps = Yaml::parse(trim($matches[1]));
                        $blockContent = trim($matches[2]);
                        $blockName = null;
                        if (is_string($blockProps)) {
                            $blockName = $blockProps;
                            $blockProps = [];
                        } else {
                            $blockName = array_keys($blockProps)[0];
                            $blockProps = $blockProps[$blockName];
                        }
                        Logger::debug("found block: " . $blockName);
                        $block = new Block($this->site, $blockName, $blockContent, $blockProps);
                        $blocks[] = $block;
                    } catch (ParseException $e) {
                        Logger::error("Error: could not parse block config: " . trim($matches[1]));
                    }
                }
            } while ($matches);
        }

        if (!count($blocks)) {
            // interpret whole pages as flat markdown file
            Logger::info("No blocks found, rendering as flat markdown file");
            $block = new Block($this->site, "text", $content);
            $blocks[] = $block;
        } else {
            $blockNames = [];
            foreach ($blocks as $block) {
                $blockNames[] = $block->getName();
            }
            Logger::info("Blocks: " . join(", ", $blockNames));
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
     * @param $request
     * @return string
     */
    private function renderPHP($articlePath, $request): string
    {
        return renderPHPPage($this->reboot, $this->site, $this, $request, $articlePath);
    }

}

/** @noinspection PhpUnusedParameterInspection */
function renderPHPPage(Reboot $reboot, Site $site, Page $page, Request $request, string $path)
{
    ob_start();
    include $path;
    $contents = ob_get_contents();
    ob_end_clean();
    return $contents;
}

