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
    private array $xpathFields = []; // collected xpath calls with props, deduplicated for example generation
    private array $allFields = []; // all field() calls in order, for structured editor
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
     * New preferred API with explicit parameters for the structured editor.
     * @param string $expression XPath expression (with part() support)
     * @param string|null $label Human-readable field label
     * @param bool $required Whether the field is required
     * @param string $type Field type: text, textarea, md-editor, media, media-list, link, link-list
     * @param array $props Additional props (min, max, etc.)
     * @return \DOMNode|\DOMNodeList
     */
    public function field(string $expression, ?string $label = null, bool $required = false, string $type = "text", array $props = []): \DOMNode|\DOMNodeList
    {
        $mergedProps = $props;
        if ($label !== null) {
            $mergedProps['description'] = $label;
        }
        if ($required) {
            $mergedProps['required'] = true;
        }
        $mergedProps['type'] = $type;
        return $this->xpath($expression, $mergedProps);
    }

    /**
     * Queries a value or a part in the markdown to use it in the block template.
     * @deprecated Use field() instead for new block templates.
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
                $max = $props['max'] ?? null;
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
                if ($max !== null && $count > $max) {
                    $error = "Block '{$this->name}': '$description' expected at most $max, found $count";
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
     * Collect an xpath field for example generation and structured editor, deduplicating by base expression.
     */
    private function collectField(string $expression, array $props): void
    {
        // Store every field call for the structured editor
        $this->allFields[] = ['expression' => $expression, 'props' => $props];
        // Strip attribute selectors and /text() to get the base element expression
        $baseExpression = preg_replace('/\/@[\w-]+$/', '', $expression);
        $baseExpression = preg_replace('/\/text\(\)$/', '', $baseExpression);
        // Only store the first occurrence per base expression (for example generation)
        if (!isset($this->xpathFields[$baseExpression])) {
            $this->xpathFields[$baseExpression] = $props;
        }
    }

    /**
     * Get collected field definitions for the structured editor.
     * @return array Array of fields with keys: xpath, label, required, type, props
     */
    public function getFields(): array
    {
        $fields = [];
        foreach ($this->xpathFields as $expression => $props) {
            $fields[] = [
                'xpath' => $expression,
                'label' => $props['description'] ?? $expression,
                'required' => $props['required'] ?? false,
                'type' => $props['type'] ?? 'text',
                'props' => array_diff_key($props, array_flip(['description', 'required', 'type'])),
            ];
        }
        return $fields;
    }

    /**
     * Get collected field definitions with current values for the structured editor.
     * Must be called after render() so fields are collected.
     * @return array Array of fields with keys: xpath, label, required, type, props, value
     */
    public function getFieldsWithValues(): array
    {
        $fields = [];
        $rawParts = $this->getRawParts();
        foreach ($this->allFields as $entry) {
            $expression = $entry['expression'];
            $props = $entry['props'];
            $type = $props['type'] ?? 'text';
            $value = $this->extractFieldValue($expression, $type, $rawParts);
            $fields[] = [
                'xpath' => $expression,
                'label' => $props['description'] ?? $expression,
                'required' => $props['required'] ?? false,
                'type' => $type,
                'props' => array_diff_key($props, array_flip(['description', 'required', 'type'])),
                'value' => $value,
            ];
        }
        return $fields;
    }

    /**
     * Split the raw markdown content into parts by '---' separator.
     * @return array indexed by part number (1-based)
     */
    private function getRawParts(): array
    {
        $parts = [1 => $this->content];
        // Split by --- that is a block separator (not in code blocks)
        $segments = preg_split('/^\s*---\s*$/m', $this->content);
        if (count($segments) > 1) {
            $parts = [];
            foreach ($segments as $i => $segment) {
                $parts[$i + 1] = trim($segment);
            }
        }
        return $parts;
    }

    /**
     * Extract the current value for a field from the DOM or raw markdown.
     */
    private function extractFieldValue(string $expression, string $type, array $rawParts): mixed
    {
        // For md-editor fields that select all elements in a part, return raw markdown
        if ($type === 'md-editor') {
            $partNumber = 1;
            if (preg_match('/part\((\d)\)/', $expression, $m)) {
                $partNumber = (int)$m[1];
            }
            return $rawParts[$partNumber] ?? '';
        }

        // For list types, return array of values
        if ($type === 'media-list') {
            $result = $this->queryXpath($expression);
            if ($result instanceof \DOMNodeList) {
                $values = [];
                foreach ($result as $node) {
                    $values[] = [
                        'src' => $node->getAttribute('src'),
                        'alt' => $node->getAttribute('alt'),
                    ];
                }
                return $values;
            }
            return [];
        }

        // For single-value fields, query the DOM
        $result = $this->queryXpath($expression);
        if ($result instanceof \DOMNodeList) {
            if ($result->length === 0) return '';
            $result = $result->item(0);
        }
        if ($result instanceof \DOMAttr) {
            return $result->value;
        }
        if ($result instanceof \DOMNode) {
            return $result->textContent;
        }
        return '';
    }

    /**
     * Run an xpath query without side effects (no validation, no field collection).
     */
    private function queryXpath(string $expression): \DOMNodeList|false
    {
        $resolved = preg_replace_callback("/part\((\d)\)/", function ($matches) {
            $partNumber = $matches[1] - 1;
            return "count(preceding::hr)=$partNumber and not(self::hr)";
        }, $expression);
        $resolved = "/html/body" . $resolved;
        return $this->xpath->query($resolved);
    }

    /**
     * Generate markdown from field values.
     * @param array $values Associative array of xpath expression => value
     * @return string Generated markdown
     */
    public static function generateMarkdownFromValues(array $fields): string
    {
        // Group fields by part number
        $parts = [];
        $maxPart = 1;
        foreach ($fields as $field) {
            $partNumber = 1;
            if (preg_match('/part\((\d)\)/', $field['xpath'], $m)) {
                $partNumber = (int)$m[1];
            }
            $maxPart = max($maxPart, $partNumber);
            if (!isset($parts[$partNumber])) {
                $parts[$partNumber] = [];
            }
            $parts[$partNumber][] = $field;
        }

        $sections = [];
        for ($p = 1; $p <= $maxPart; $p++) {
            if (!isset($parts[$p])) {
                $sections[] = '';
                continue;
            }
            $partFields = $parts[$p];
            // Check if this part has an md-editor field — use its value directly as raw markdown
            foreach ($partFields as $field) {
                if ($field['type'] === 'md-editor') {
                    $sections[] = $field['value'];
                    continue 2;
                }
            }
            // Otherwise build markdown from individual fields
            $lines = [];
            $pendingLink = null; // buffer for combining link href + text
            foreach ($partFields as $field) {
                $value = $field['value'] ?? '';
                $xpath = $field['xpath'];
                $type = $field['type'];
                $clean = preg_replace('/\[part\(\d\)\]/', '', $xpath);
                $clean = ltrim($clean, '/');

                if ($type === 'media') {
                    $alt = '';
                    // Look for an alt field for the same element
                    foreach ($partFields as $other) {
                        if ($other !== $field && preg_match('/img.*\/@alt/', $other['xpath'])) {
                            $alt = $other['value'] ?? '';
                        }
                    }
                    if (preg_match('/^(\/\/)?li\/img/', $clean)) {
                        $lines[] = "- ![$alt]($value)";
                    } else {
                        $lines[] = "![$alt]($value)";
                    }
                } else if ($type === 'media-list') {
                    $items = is_array($value) ? $value : [];
                    foreach ($items as $item) {
                        $src = $item['src'] ?? '';
                        $alt = $item['alt'] ?? '';
                        if (preg_match('/^(\/\/)?li\/img/', $clean)) {
                            $lines[] = "- ![$alt]($src)";
                        } else {
                            $lines[] = "![$alt]($src)";
                        }
                    }
                } else if ($type === 'link') {
                    // Buffer link — combine with next text field for the same element
                    $pendingLink = $value;
                } else {
                    // text or textarea
                    if ($pendingLink !== null) {
                        $lines[] = "[$value]($pendingLink)";
                        $pendingLink = null;
                    } else {
                        $line = self::valueToMarkdown($clean, $value);
                        $lines[] = $line;
                    }
                }
            }
            // Flush pending link without text
            if ($pendingLink !== null) {
                $lines[] = "[$pendingLink]($pendingLink)";
            }
            $sections[] = implode("\n\n", $lines);
        }

        return implode("\n\n---\n\n", $sections);
    }

    /**
     * Convert a value to markdown based on the xpath element type.
     */
    private static function valueToMarkdown(string $cleanXpath, string $value): string
    {
        if (preg_match('/^(\/\/)?h1/', $cleanXpath)) return "# $value";
        if (preg_match('/^(\/\/)?h2/', $cleanXpath)) return "## $value";
        if (preg_match('/^(\/\/)?h3/', $cleanXpath)) return "### $value";
        if (preg_match('/^(\/\/)?h4/', $cleanXpath)) return "#### $value";
        return $value;
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
                $firstField = true;
                foreach ($parts[$p] as $field) {
                    if (!$firstField) {
                        $lines[] = '';
                    }
                    $firstField = false;
                    $description = $field['props']['description'] ?? 'content';
                    $min = $field['props']['min'] ?? 1;
                    $line = $this->xpathToMarkdown($field['expression'], $description);
                    $repeatCount = max(1, $min);
                    for ($i = 0; $i < $repeatCount; $i++) {
                        $lines[] = $line;
                    }
                }
            } else {
                $lines[] = 'Lorem ipsum dolor sit amet, consectetur adipiscing.';
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
