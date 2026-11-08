<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

namespace Shaack\Reboot\AddOns\Admin;

use Shaack\Reboot\Block;
use Shaack\Reboot\Blueprint;
use Shaack\Reboot\Page;
use Shaack\Reboot\Site;

/**
 * Turns a Markdown page into a structured page. A block becomes a form block if
 * a block type says in its blueprint how to read it, otherwise it stays a
 * Markdown block of the structured page, `{type: intro, markdown: "..."}`, and
 * looks the same. Nothing of the page is lost.
 *
 * ```yaml
 * # site/blocks/intro/intro.yml
 * convert:
 *   from: intro                         # the Markdown block it takes over
 *   fields:
 *     heading: "/h1[part(1)]/text()"    # XPath, as in the Markdown template, its text
 *     text: {part: 2}                   # the Markdown of part 2, part 0 is the whole block
 *     cards:                            # a list, an item per node or per part
 *       each: "//li/img"                # or `each: part`
 *       fields:
 *         image: "@src"                 # relative to the node, or a spec for the part
 *         caption: "@alt"
 * ```
 *
 * `{part: 1, strip-heading: true}` leaves out a heading line at the start of the part.
 */
class PageConverter
{
    private Site $site;

    public function __construct(Site $site)
    {
        $this->site = $site;
    }

    /**
     * @return array ['values' => the structured page, 'report' => [['from' => markdown type,
     *     'to' => form type or null], ...], 'notes' => [string, ...]]
     */
    public function convert(string $source): array
    {
        [$frontmatter, $body] = Page::splitFrontmatter(trim($source));
        $page = Blueprint::forPage($this->site);
        $blocksField = "blocks";
        foreach ($page->getFields() as $name => $props) {
            if ($props["type"] === "blocks") {
                $blocksField = $name;
                break;
            }
        }
        // the frontmatter becomes the page fields, all of it is kept
        $values = [];
        foreach ($page->getFields() as $name => $props) {
            if ($name !== $blocksField && array_key_exists($name, $frontmatter)) {
                $values[$name] = $frontmatter[$name];
            }
        }
        $values[$blocksField] = [];
        $values += $frontmatter;

        $notes = [];
        $report = [];
        $parsed = Page::parseBlocks($body);
        if (!$parsed) {
            // a page without blocks is one text block
            $parsed = trim($body) !== "" ? [["text", [], trim($body), 0]] : [];
        } else if (trim(substr($body, 0, $parsed[0][3])) !== "") {
            $notes[] = "The text before the first block was not shown on the page and is left out.";
        }
        $targets = $this->targets();
        foreach ($parsed as [$type, $config, $content]) {
            $target = $targets[$type] ?? null;
            if ($target) {
                $values[$blocksField][] = $this->formBlock($target, $type, $config, $content);
                $report[] = ["from" => $type, "to" => $target->getName()];
            } else {
                $block = ["type" => $type];
                foreach ($config as $key => $value) {
                    $block[$key] = $value;
                }
                $block["markdown"] = trim($content);
                $values[$blocksField][] = $block;
                $report[] = ["from" => $type, "to" => null];
            }
        }
        return ["values" => $values, "report" => $report, "notes" => $notes];
    }

    /**
     * The block types that take over a Markdown block type, by its name.
     * @return Blueprint[]
     */
    private function targets(): array
    {
        $targets = [];
        foreach (Blueprint::blockTypes($this->site) as $blueprint) {
            $from = $blueprint->getConvert()["from"] ?? null;
            if (is_string($from) && !isset($targets[$from])) {
                $targets[$from] = $blueprint;
            }
        }
        return $targets;
    }

    private function formBlock(Blueprint $target, string $from, array $config, string $content): array
    {
        $block = ["type" => $target->getName()];
        if (Page::isDisabled($config)) {
            $block["disabled"] = true;
        }
        $markdownBlock = new Block($this->site, $from, $content, $config);
        $specs = (array)($target->getConvert()["fields"] ?? []);
        foreach ($target->getFields() as $name => $props) {
            if (array_key_exists($name, $specs)) {
                $value = $this->value($markdownBlock, $specs[$name], $props, null, $from);
                if ($value !== null && $value !== "" && $value !== []) {
                    $block[$name] = $value;
                }
            }
        }
        // the config of the Markdown block, `inverse: true` for example, stays with the block
        foreach ($config as $key => $value) {
            if ($key !== "disabled" && !array_key_exists($key, $block)) {
                $block[$key] = $value;
            }
        }
        return $block;
    }

    /**
     * The value of one field: an XPath gives the text of what it finds, `{part: n}`
     * the Markdown of a part, `{each: ..., fields: ...}` the items of a list.
     */
    private function value(Block $block, $spec, array $props, ?\DOMNode $context, string $from)
    {
        if (is_array($spec) && isset($spec["each"])) {
            return $this->items($block, $spec, $props, $from);
        }
        if (is_array($spec) && isset($spec["part"])) {
            $markdown = $block->rawPart((int)$spec["part"]);
            if (!empty($spec["strip-heading"])) {
                $markdown = preg_replace('/\A\s*#{1,6} [^\n]*\n?/', '', $markdown);
            }
            return trim($markdown);
        }
        if (!is_string($spec)) {
            return null;
        }
        $nodes = $block->nodes($spec, $context);
        if (!$nodes) {
            return null;
        }
        $text = trim($nodes[0] instanceof \DOMAttr ? $nodes[0]->value : $nodes[0]->textContent);
        if (($props["type"] ?? "") === "number" && is_numeric($text)) {
            return str_contains($text, ".") ? (float)$text : (int)$text;
        }
        return $text;
    }

    private function items(Block $block, array $spec, array $props, string $from): array
    {
        $itemBlueprint = $props["blueprint"] ?? null;
        $itemSpecs = (array)($spec["fields"] ?? []);
        $items = [];
        if ($spec["each"] === "part") {
            // every part is an item, read like a block of its own
            for ($part = 1; $part <= $block->partCount(); $part++) {
                $partBlock = new Block($this->site, $from, $block->rawPart($part));
                $items[] = $this->item($partBlock, $itemSpecs, $itemBlueprint, null, $from);
            }
        } else {
            foreach ($block->nodes((string)$spec["each"]) as $node) {
                $items[] = $this->item($block, $itemSpecs, $itemBlueprint, $node, $from);
            }
        }
        return array_values(array_filter($items));
    }

    private function item(Block $block, array $specs, ?Blueprint $blueprint, ?\DOMNode $context, string $from): array
    {
        $item = [];
        foreach ($specs as $name => $spec) {
            $props = $blueprint?->getFields()[$name] ?? [];
            $value = $this->value($block, $spec, $props, $context, $from);
            if ($value !== null && $value !== "") {
                $item[$name] = $value;
            }
        }
        return $item;
    }
}
