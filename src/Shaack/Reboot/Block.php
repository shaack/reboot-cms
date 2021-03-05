<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

namespace Shaack\Reboot;

use DOMDocument;
use Shaack\Utils\Logger;

class Block
{
    private $name;
    private $content;
    private $xpath;
    private $config;
    private $reboot;

    private static $parsedown;

    public function __construct($reboot, $name, $content = "", $config = array())
    {
        if (!$this::$parsedown) {
            $this::$parsedown = new \Parsedown();
        }
        $this->name = $name;
        $this->content = $content;
        $this->config = $config;
        $this->reboot = $reboot;

        $html = $this::$parsedown->parse($this->content);
        $document = new \DOMDocument();
        $document->loadHTML($html);
        $this->xpath = new \DOMXPath($document);
    }

    public function render()
    {
        Logger::log("");
        Logger::log("Rendering Block " . $this->name);
        Logger::log($this->xpath->document->saveHTML());
        ob_start();
        /** @noinspection PhpIncludeInspection */
        include $this->reboot->baseDir . '/themes/' . $this->reboot->config['theme'] . '/blocks/' . $this->name . ".php";
        $contents = ob_get_contents();
        ob_end_clean();
        return $contents;
    }

    public function config($name)
    {
        return @$this->config[$name];
    }

    public function xpath($expression = null)
    {
        Logger::log("query: " . $expression);
        // replace part(n), https://stackoverflow.com/questions/10859703/xpath-select-all-elements-between-two-specific-elements
        $expression = preg_replace_callback("/part\((\d)\)/", function ($matches) {
            $partNumber = $matches[1] - 1;
            return "count(preceding::hr)=$partNumber and not(self::hr)";
        }, $expression);
        $expression = "/html/body" . $expression;
        $result = $this->xpath->query($expression);
        $ret = "";
        if ($result === false) {
            Logger::log("xquery error");
            $ret = "*xquery error*";
        } else {
            if ($result->length === 1) {
                $result = $result->item(0);
            }
            if ($result instanceof \DOMText or $result instanceof \DOMAttr) {
                $ret = $result->nodeValue;
            } else if ($result instanceof \DOMElement) {
                $ret = $this->xpath->document->saveXML($result);
            } else if ($result instanceof \DOMNodeList) {
                if ($result->length === 0) {
                    $ret = "<!-- no result -->";
                } else {
                    $temp_dom = new DOMDocument();
                    foreach ($result as $n) $temp_dom->appendChild($temp_dom->importNode($n, true));
                    $ret = $temp_dom->saveHTML();
                }
            } else {
                Logger::log("ERROR d06492af: Unknown query result " . get_class($result));
            }
            Logger::log($expression . " => " . $ret);
        }
        return $ret;
    }

    public function content(): string
    {
        if ($this->content) {
            return $this::$parsedown->parse($this->content);
        } else {
            return "";
        }
    }
}