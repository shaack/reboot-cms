<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

namespace Shaack\Reboot;

use Shaack\Utils\Logger;

class Block
{
    private $name;
    private $content;
    private $xpath;
    private $config;
    private $site;

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
        $document->loadHTML($html);
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
        return $this->xpath->query($expression);
        if ($result === false) {
            Logger::error("xquery error");
            $ret = "*xquery error*";
        } else {
            /*
            if ($result->length === 1) {
                $result = $result->item(0);
            }
            */
            /*
            if ($result instanceof \DOMText or $result instanceof \DOMAttr) {
                $ret = utf8_decode($result->nodeValue);
            } else if ($result instanceof \DOMElement) {
                $ret = utf8_decode($this->xpath->document->saveHTML($result));
            } else if ($result instanceof \DOMNodeList) {
                if ($result->length === 0) {
                    $ret = "no result for expression: " . $expression;
                } else {
                    foreach ($result as $node) {
                        $ret .= utf8_decode($this->xpath->document->saveHTML($node));
                    }
                }
            } else {
                Logger::error("(72a2) Unknown query result " . get_class($result));
            }
            */
            Logger::debug($expression . " => " . $this->nodeHtml($result));
            return $result;
        }
        return $result;
    }

    /**
     * return the html content of a node
     */
    function nodeHtml(\DOMNode|\DOMNodeList $nodeOrNodeList):string
    {
        $html = '';
        if ($nodeOrNodeList instanceof \DOMText or $nodeOrNodeList instanceof \DOMAttr) {
            $html .= utf8_decode($nodeOrNodeList->nodeValue);
        } else if (@$nodeOrNodeList->childNodes) {
            $children = $nodeOrNodeList->childNodes;
            foreach ($children as $child) {
                $html .= $this->nodeHtml($child);
            }
        } else if ($nodeOrNodeList instanceof \DOMNodeList) {
            foreach ($nodeOrNodeList as $node) {
                // $html .= utf8_decode($this->xpath->document->saveHTML($node));
                $html .= $this->nodeHtml($node);
            }
        } else {
            $tmp_doc = new DOMDocument();
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

function renderBlock(Site $site, Block $block)
{
    ob_start();
    $blockFilePath = $site->getFsPath() . '/blocks/' . $block->getName() . ".php";
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
