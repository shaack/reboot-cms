<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

namespace Shaack\Reboot;

use Shaack\Logger;
use Shaack\Utils\HttpUtils;

class Block
{
    private string $name;
    private string $content;
    private \DOMXPath $xpath;
    private array $config;
    private Site $site;

    private static $parsedown;

    /**
     * @param Site $site
     * @param string $name
     * @param string $content
     * @param array $config
     */
    public function __construct(Site $site, string $name, string $content = "", array $config = [])
    {
        if (!$this::$parsedown) {
            $this::$parsedown = new \Parsedown();
        }
        $this->site = $site;
        $this->name = $name;
        $this->content = $content;
        $this->config = $config;

        $html = $this::$parsedown->parse($this->content);
        $document = new \DOMDocument();
        if ($html) {
            $html = '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">' . $html;
            $document->loadHTML($html);
        }
        $this->xpath = new \DOMXPath($document);
    }

    /**
     * @return string
     */
    public function render(): string
    {
        Logger::debug("");
        Logger::debug("Rendering Block " . $this->name);
        Logger::debug($this->xpath->document->saveHTML());
        return renderBlock($this->site, $this);
    }

    /**
     * @return array
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Queries a value or a part in the markdown to use it in the block template
     * @param string $expression
     * @return \DOMNode|\DOMNodeList
     */
    public function xpath(string $expression): \DOMNode|\DOMNodeList
    {
        Logger::debug("query: " . $expression);
        // replace part(n), https://stackoverflow.com/questions/10859703/xpath-select-all-elements-between-two-specific-elements
        $expression = preg_replace_callback("/part\((\d)\)/", function ($matches) {
            $partNumber = $matches[1] - 1;
            return "count(preceding::hr)=$partNumber and not(self::hr)";
        }, $expression);
        $expression = "/html/body" . $expression;
        $nodeOrNodeList = $this->xpath->query($expression);
        if ($nodeOrNodeList instanceof \DOMNodeList) {
            Logger::debug("\DOMNodeList found with " . $nodeOrNodeList->length . " entries.");
            if ($nodeOrNodeList->length === 1) {
                $nodeOrNodeList = $nodeOrNodeList->item(0);
            }
        }
        return $nodeOrNodeList;
    }

    /**
     * return the html content of a node
     */
    function nodeHtml(\DOMNode|\DOMNodeList $nodeOrNodeList, $descent = true): string
    {
        $html = '';
        if (!$descent) {
            $children = $nodeOrNodeList->childNodes;
            foreach ($children as $child) {
                $tmp_doc = new \DOMDocument();
                $tmp_doc->appendChild($tmp_doc->importNode($child, true));
                $html .= $tmp_doc->saveHTML();
            }
            return $html;
        }
        if ($nodeOrNodeList instanceof \DOMText) {
            Logger::debug("nodeHtml is DOMText");
            $html .= $nodeOrNodeList->textContent;
        }
        if ($nodeOrNodeList instanceof \DOMNodeList) {
            Logger::debug("nodeHtml is DOMNodeList");
            foreach ($nodeOrNodeList as $node) {
                $html .= $this->xpath->document->saveHTML($node);
            }
        } else if ($nodeOrNodeList instanceof \DOMAttr) {
            Logger::debug("nodeHtml is DOMAttr");
            $html .= $nodeOrNodeList->textContent;
        } else if (@$nodeOrNodeList->childNodes) {
            Logger::debug("nodeHtml hasChildNodes");
            $children = $nodeOrNodeList->childNodes;
            foreach ($children as $child) {
                $html .= $this->nodeHtml($child);
            }
        } else {
            $tmp_doc = new \DOMDocument();
            $tmp_doc->appendChild($tmp_doc->importNode($nodeOrNodeList, true));
            $html .= $tmp_doc->saveHTML();
        }
        return $html;
    }

    /**
     * Return the whole page, parsed to HTML
     * @return string
     */
    public function content(): string
    {
        if ($this->content) {
            return $this::$parsedown->parse($this->content);
        } else {
            return "";
        }
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }
}

function renderBlock(Site $site, Block $block): string
{
    $blockName = HttpUtils::sanitizeFileName($block->getName());
    ob_start();
    $blockFilePath = $site->getFsPath() . '/blocks/' . $blockName . ".php";
    if (!file_exists($blockFilePath)) {
        Logger::error("Block not found at: " . $blockFilePath);
        return "<div class='w-100 p-3 border-1 border-top border-bottom text-danger text-center'>Block not found: \"" . $block->getName() . "\"</div>";
    } else {
        include $blockFilePath;
        $contents = ob_get_contents();
        ob_end_clean();
        return $contents;
    }
}
