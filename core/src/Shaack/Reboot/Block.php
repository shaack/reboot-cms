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
    private array $validationErrors = [];
    private array $xpathFields = []; // collected xpath calls with props, for example generation
    private int $maxPart = 1; // highest part number seen across all xpath calls

    private static $parsedown;
    private static array $allValidationErrors = [];
    private static array $allExamples = [];

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
    public function render(?Request $request): string
    {
        Logger::debug("Rendering Block " . $this->name);
        // Logger::debug($this->xpath->document->saveHTML());
        return renderBlock($this->site, $this, $request);
    }

    /**
     * @return array
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * @return array
     */
    public function getValidationErrors(): array
    {
        return $this->validationErrors;
    }

    /**
     * Get all validation errors collected across all blocks since last reset.
     */
    public static function getAllValidationErrors(): array
    {
        return self::$allValidationErrors;
    }

    /**
     * Reset the global validation error collector and examples.
     */
    public static function resetAllValidationErrors(): void
    {
        self::$allValidationErrors = [];
        self::$allExamples = [];
    }

    /**
     * Get all block examples collected during rendering (block name => markdown example).
     */
    public static function getAllExamples(): array
    {
        return self::$allExamples;
    }

    /**
     * Store this block's generated example in the static collection.
     */
    public function collectExample(): void
    {
        $example = $this->generateExample();
        if ($example) {
            self::$allExamples[$this->name] = $example;
        }
    }

    /**
     * Queries a value or a part in the markdown to use it in the block template.
     * Optional props for validation: 'required' (bool), 'min' (int), 'description' (string)
     * @param string $expression
     * @param array $props
     * @return \DOMNode|\DOMNodeList
     */
    public function xpath(string $expression, array $props = []): \DOMNode|\DOMNodeList
    {
        Logger::debug("query: " . $expression);
        $originalExpression = $expression;
        // Track highest part number for example generation
        if (preg_match('/part\((\d)\)/', $expression, $partMatch)) {
            $this->maxPart = max($this->maxPart, (int)$partMatch[1]);
        }
        // replace part(n), https://stackoverflow.com/questions/10859703/xpath-select-all-elements-between-two-specific-elements
        $expression = preg_replace_callback("/part\((\d)\)/", function ($matches) {
            $partNumber = $matches[1] - 1;
            return "count(preceding::hr)=$partNumber and not(self::hr)";
        }, $expression);
        $expression = "/html/body" . $expression;
        $nodeOrNodeList = $this->xpath->query($expression);
        if ($nodeOrNodeList instanceof \DOMNodeList) {
            Logger::debug("\DOMNodeList found with " . $nodeOrNodeList->length . " entries.");
            // Track and validate if props are set
            if (!empty($props)) {
                $this->collectField($originalExpression, $props);
                $count = $nodeOrNodeList->length;
                $description = $props['description'] ?? $originalExpression;
                $isRequired = $props['required'] ?? false;
                $min = $props['min'] ?? null;
                if ($isRequired && $count === 0) {
                    $error = "Block '{$this->name}': missing required '$description'";
                    $this->validationErrors[] = $error;
                    Logger::error($error);
                    self::$allValidationErrors[] = $error;
                } else if ($min !== null && $count < $min) {
                    $error = "Block '{$this->name}': '$description' expected at least $min, found $count";
                    $this->validationErrors[] = $error;
                    Logger::error($error);
                    self::$allValidationErrors[] = $error;
                }
            }
            if ($nodeOrNodeList->length === 1) {
                $nodeOrNodeList = $nodeOrNodeList->item(0);
            }
        }
        return $nodeOrNodeList;
    }

    /**
     * Collect an xpath field for example generation, deduplicating by base expression.
     */
    private function collectField(string $expression, array $props): void
    {
        // Strip attribute selectors and /text() to get the base element expression
        $baseExpression = preg_replace('/\/@[\w-]+$/', '', $expression);
        $baseExpression = preg_replace('/\/text\(\)$/', '', $baseExpression);
        // Only store the first occurrence per base expression
        if (!isset($this->xpathFields[$baseExpression])) {
            $this->xpathFields[$baseExpression] = $props;
        }
    }

    /**
     * Generate a markdown example from the collected xpath fields.
     * @return string
     */
    public function generateExample(): string
    {
        // Group fields by part number
        $parts = [];
        foreach ($this->xpathFields as $expression => $props) {
            $partNumber = 1;
            if (preg_match('/part\((\d)\)/', $expression, $m)) {
                $partNumber = (int)$m[1];
            }
            if (!isset($parts[$partNumber])) {
                $parts[$partNumber] = [];
            }
            $parts[$partNumber][] = [
                'expression' => $expression,
                'props' => $props
            ];
        }

        $lines = [];
        for ($p = 1; $p <= $this->maxPart; $p++) {
            if ($p > 1) {
                $lines[] = '';
                $lines[] = '---';
                $lines[] = '';
            }
            if (isset($parts[$p])) {
                foreach ($parts[$p] as $field) {
                    $description = $field['props']['description'] ?? 'content';
                    $min = $field['props']['min'] ?? 1;
                    $line = $this->xpathToMarkdown($field['expression'], $description);
                    $repeatCount = max(1, $min);
                    for ($i = 0; $i < $repeatCount; $i++) {
                        $lines[] = $line;
                    }
                    $lines[] = '';
                }
            } else {
                $lines[] = '...';
                $lines[] = '';
            }
        }

        // Remove trailing empty lines
        while (!empty($lines) && $lines[count($lines) - 1] === '') {
            array_pop($lines);
        }

        return implode("\n", $lines);
    }

    /**
     * Map an xpath expression to a markdown snippet.
     */
    private function xpathToMarkdown(string $expression, string $description): string
    {
        // Strip part() predicates for element detection
        $clean = preg_replace('/\[part\(\d\)\]/', '', $expression);
        // Strip leading slashes
        $clean = ltrim($clean, '/');

        // Detect element type from the expression
        if (preg_match('/^(\/\/)?li\/img/', $clean)) {
            return "- ![$description](image-url)";
        }
        if (preg_match('/^(\/\/)?img/', $clean)) {
            return "![$description](image-url)";
        }
        if (preg_match('/^(\/\/)?a/', $clean)) {
            return "[$description](url)";
        }
        if (preg_match('/^(\/\/)?h1/', $clean)) {
            return "# $description";
        }
        if (preg_match('/^(\/\/)?h2/', $clean)) {
            return "## $description";
        }
        if (preg_match('/^(\/\/)?h3/', $clean)) {
            return "### $description";
        }
        if (preg_match('/^(\/\/)?h4/', $clean)) {
            return "#### $description";
        }
        if (preg_match('/^(\/\/)?p/', $clean)) {
            return $description;
        }
        // Wildcard or unknown — just use description
        return $description;
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

function renderBlock(Site $site, Block $block, ?Request $request): string
{
    $blockName = HttpUtils::sanitizeFileName($block->getName());
    ob_start();
    $blockFilePath = $site->getFsPath() . '/blocks/' . $blockName . ".php";
    if (!file_exists($blockFilePath)) {
        Logger::error("Block not found at: " . $blockFilePath);
        return "<div class='w-100 p-3 border-1 border-top border-bottom text-danger text-center'>Block not found: \"" . htmlspecialchars($block->getName(), ENT_QUOTES, 'UTF-8') . "\"</div>";
    } else {
        include $blockFilePath;
        $contents = ob_get_contents();
        ob_end_clean();
        // Collect generated example
        $block->collectExample();
        // Show validation errors in debug mode (logLevel 0)
        if (Logger::getLevel() === 0 && !empty($block->getValidationErrors())) {
            $errorHtml = '<div class="w-100 p-3 border border-warning bg-warning-subtle text-dark" style="font-size: 0.85rem;">';
            $errorHtml .= '<strong>Block &quot;' . htmlspecialchars($block->getName(), ENT_QUOTES, 'UTF-8') . '&quot; schema validation:</strong><ul class="mb-0">';
            foreach ($block->getValidationErrors() as $error) {
                $errorHtml .= '<li>' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</li>';
            }
            $errorHtml .= '</ul></div>';
            $contents = $errorHtml . $contents;
        }
        return $contents;
    }
}
