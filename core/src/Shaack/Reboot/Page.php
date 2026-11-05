<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

namespace Shaack\Reboot;

use Shaack\Logger;
use Shaack\Utils\FileSystemUtils;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class Page
{
    private $reboot;
    private $site;
    private $config = []; // If the page contains frontmatter, it will be stored here after rendering
    private ?string $previewFsPath = null; // see setPreviewContent()
    private ?string $previewContent = null;

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
     * Render the Markdown file `$fsPath` with `$content` instead of what is on
     * disk. The admin's live preview uses it, so a draft never touches the
     * published file.
     */
    public function setPreviewContent(string $fsPath, string $content): void
    {
        $this->previewFsPath = realpath($fsPath) ?: $fsPath;
        $this->previewContent = $content;
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
            if (!$request->isValidPath()) {
                return $this->render404($path, $request);
            }
        }
        return $this->renderPath($path, $request) ?? $this->render404($path, $request);
    }

    /**
     * The rendered page for `$path`, or null if there is none.
     */
    private function renderPath(string $path, ?Request $request): ?string
    {
        $pagesDir = realpath($this->site->getFsPath() . '/pages');
        $requestedFsPath = $pagesDir . $path;

        if (is_dir($requestedFsPath)) {
            $requestedFsPath .= "/index";
        }

        // Resolve symlinks and '..' to prevent path traversal. The separator keeps
        // a sibling folder like `pages-old` out.
        $resolvedDir = realpath(dirname($requestedFsPath));
        $insidePagesDir = FileSystemUtils::isInside($resolvedDir, $pagesDir);
        $pathInfo = pathinfo($path);
        if ($insidePagesDir && !array_key_exists("extension", $pathInfo)) {
            if (file_exists($requestedFsPath . ".md")) {
                Logger::info("Markdown page: " . $path);
                return $this->renderMarkdown($requestedFsPath . ".md", $request);
            } else if (file_exists($requestedFsPath . ".php")) {
                Logger::info("PHP page: " . $path);
                return $this->renderPHP($requestedFsPath . ".php", $request);
            }
        }
        // Pages an AddOn brings along, see AddOn::getPages(). Matched by exact
        // path, so the request path never touches the file system here.
        $addOnPage = $this->site->getAddOnPage($path);
        if ($addOnPage !== null) {
            Logger::info("AddOn page: " . $path);
            return $this->renderPHP($addOnPage, $request);
        }
        return null;
    }

    public function render404($path, ?Request $request = null)
    {
        Logger::info("[404] response for path: " . $path);
        http_response_code(404);
        // put a 404.md or 404.php in /pages to create your own
        return $this->renderPath("/404", $request) ?? "";
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Parse a markdown file into blocks and collect field definitions with current values.
     * Renders each block to trigger field() calls, then returns the field metadata.
     * @param string $pageFsPath Absolute path to the .md file
     * @return array Array of blocks, each with 'name', 'config', and 'fields'
     */
    public function getBlockFields(string $pageFsPath): array
    {
        $content = trim(file_get_contents($pageFsPath));
        if (!$content) return [];
        [, $content] = self::splitFrontmatter($content);

        $blocks = [];
        foreach (self::parseBlocks($content) as [$blockName, $blockProps, $blockContent]) {
            try {
                $block = new Block($this->site, $blockName, $blockContent, $blockProps);
                // Render to collect field definitions
                $block->render(null);
                $blocks[] = [
                    'name' => $blockName,
                    'config' => $blockProps,
                    'fields' => $block->getFieldsWithValues(),
                ];
            } catch (\Throwable $e) {
                Logger::error("Error parsing block: " . $e->getMessage());
            }
        }
        return $blocks;
    }

    /**
     * Split a page into its frontmatter and the rest. Frontmatter is a YAML
     * mapping between a `---` on the first line and the next `---` line. If
     * that is no valid mapping, the page has no frontmatter and the `---`
     * are horizontal rules, so a page that starts with a rule loses nothing.
     * @return array [array $config, string $content]
     */
    public static function splitFrontmatter(string $content): array
    {
        if (!preg_match('/\A---[ \t]*\R(.*?)^---[ \t]*$/ms', $content, $matches)) {
            return [[], $content];
        }
        try {
            $config = Yaml::parse($matches[1]);
        } catch (ParseException $e) {
            Logger::error("Error: could not parse frontmatter: " . $e->getMessage());
            return [[], $content];
        }
        if ($config !== null && !is_array($config)) {
            return [[], $content];
        }
        return [$config ?? [], substr($content, strlen($matches[0]))];
    }

    /**
     * Find the blocks of a page. A block starts with an HTML comment that holds
     * its name, optionally with a config mapping (`<!-- hero -->`,
     * `<!-- hero: {dark: true} -->`), and runs up to the next comment. Any other
     * comment, like `<!-- TODO: check -->`, is no block and stays part of the
     * content of the block before it. Comments in fenced code are ignored.
     * @return array of [string $name, array $config, string $content]
     */
    public static function parseBlocks(string $content): array
    {
        // replace fenced code blocks with same-length placeholder to preserve offsets
        $contentForBlockSearch = preg_replace_callback('/```[\s\S]*?```/', function ($m) {
            return str_repeat(" ", strlen($m[0]));
        }, $content);
        $blocks = [];
        $offset = strpos($contentForBlockSearch, "<!--");
        if ($offset === false) {
            return $blocks;
        }
        while (preg_match('/<!--(.*)-->(.*)(<!--|$)/sU', $contentForBlockSearch, $matches, PREG_OFFSET_CAPTURE, $offset)) {
            $offset = $matches[3][1];
            $header = self::parseBlockHeader($matches[1][0]);
            // the content comes from the original, not from the search copy,
            // where fenced code blocks are blanked out
            if ($header !== null) {
                $blocks[] = [$header[0], $header[1], substr($content, $matches[2][1], strlen($matches[2][0]))];
            } else if ($blocks) {
                $blocks[count($blocks) - 1][2] .= substr($content, $matches[0][1], $offset - $matches[0][1]);
            }
            if ($offset >= strlen($contentForBlockSearch)) {
                break;
            }
        }
        foreach ($blocks as &$block) {
            $block[2] = trim($block[2]);
        }
        return $blocks;
    }

    /**
     * [name, config] if the comment text names a block, otherwise null.
     */
    private static function parseBlockHeader(string $comment): ?array
    {
        try {
            $props = Yaml::parse(trim($comment));
        } catch (ParseException $e) {
            return null;
        }
        if (is_string($props) && $props !== "") {
            return [$props, []];
        }
        if (is_array($props) && count($props) === 1) {
            $name = array_key_first($props);
            $config = $props[$name];
            if (is_string($name) && $name !== "" && ($config === null || is_array($config))) {
                return [$name, $config ?? []];
            }
        }
        return null;
    }

    /**
     * @param string $pagePath
     * @return string
     */
    private function renderMarkdown(string $pagePath, ?Request $request): string
    {
        $content = $this->previewContent !== null && (realpath($pagePath) ?: $pagePath) === $this->previewFsPath
            ? $this->previewContent
            : file_get_contents($pagePath);
        $content = trim($content);
        $this->config = [];
        if (!$content) {
            return "";
        }
        [$this->config, $content] = self::splitFrontmatter($content);

        $blocks = array();
        foreach (self::parseBlocks($content) as [$blockName, $blockProps, $blockContent]) {
            Logger::debug("found block: " . $blockName);
            $blocks[] = new Block($this->site, $blockName, $blockContent, $blockProps);
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
            $html .= $block->render($request);
        }

        return $html;
    }

    /**
     * @param $articlePath
     * @param $request
     * @return string
     */
    private function renderPHP($articlePath, ?Request $request): string
    {
        return renderPHPPage($this->reboot, $this->site, $this, $request, $articlePath);
    }

}

/** @noinspection PhpUnusedParameterInspection */
function renderPHPPage(Reboot $reboot, Site $site, Page $page, ?Request $request, string $path)
{
    ob_start();
    include $path;
    $contents = ob_get_contents();
    ob_end_clean();
    return $contents;
}
