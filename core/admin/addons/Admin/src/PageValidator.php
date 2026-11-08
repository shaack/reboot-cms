<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

namespace Shaack\Reboot\AddOns\Admin;

use Shaack\Reboot\Block;
use Shaack\Reboot\Page;
use Shaack\Reboot\Site;

/**
 * Checks the Markdown of a page while it is edited, before it is saved.
 * Every block is rendered on its own, so each problem knows the editor line
 * of its block and the editor can jump there.
 */
class PageValidator
{
    /**
     * @return array of ['line' => int, 'block' => ?string, 'message' => string],
     *     the line counts from 0 like the lines of the editor
     */
    public static function validate(Site $site, string $source): array
    {
        $problems = [];
        $content = trim($source);
        if ($content === "") {
            return $problems;
        }
        [, $body] = Page::splitFrontmatter($content);
        // the line in the source where $body starts, as in Page::renderMarkdown()
        $bodyLine = substr_count($source, "\n", 0, strlen($source) - strlen(ltrim($source)))
            + substr_count($content, "\n", 0, strlen($content) - strlen($body));
        $blocks = Page::parseBlocks($body);
        if (!$blocks) {
            return $problems;
        }
        $firstOffset = $blocks[0][3];
        if (trim(substr($body, 0, $firstOffset)) !== "") {
            $problems[] = ['line' => $bodyLine + substr_count($body, "\n", 0, strspn($body, "\n\r\t ")), 'block' => null,
                'message' => "The text before the first block is not shown on the page."];
        }
        foreach ($blocks as [$name, $props, $blockContent, $offset]) {
            if (Page::isDisabled($props)) {
                continue;
            }
            $line = $bodyLine + substr_count($body, "\n", 0, $offset);
            if (Block::templateFile($site, $name) === null) {
                $problems[] = ['line' => $line, 'block' => $name,
                    'message' => "There is no block '" . $name . "'. A typo? The page shows an error there."];
                continue;
            }
            $block = new Block($site, $name, $blockContent, $props);
            ob_start();
            try {
                $block->render(null);
            } finally {
                ob_end_clean();
            }
            foreach ($block->getValidationErrors() as $error) {
                // "Block 'intro': missing required 'Link'", the editor shows the block apart
                $problems[] = ['line' => $line, 'block' => $name,
                    'message' => preg_replace("/^Block '[^']*': /", "", $error)];
            }
        }
        return $problems;
    }
}
