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
            } else if (file_exists($requestedFsPath . ".yml")) {
                Logger::info("Structured page: " . $path);
                return $this->renderStructured($requestedFsPath . ".yml", $request);
            } else if (file_exists($requestedFsPath . ".php")) {
                Logger::info("PHP page: " . $path);
                return $this->renderPHP($requestedFsPath . ".php", $request);
            }
        }
        $entryPage = $this->renderEntry($path, $request);
        if ($entryPage !== null) {
            return $entryPage;
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
     * A value of the frontmatter, ready to be printed into HTML. Strings are
     * escaped, other values like `hide-nav: true` or lists come back unchanged.
     * Available after `render()`.
     *
     * `<title><?= $page->meta('title', 'My Site') ?></title>`
     *
     * @param mixed $default used if the frontmatter has no such key, escaped like a value
     * @param bool $escaped false returns a string as written, for JSON, URLs or HTML on purpose
     * @return mixed
     */
    public function meta(string $key, $default = null, bool $escaped = true)
    {
        $value = $this->config[$key] ?? $default;
        if ($escaped && is_string($value)) {
            return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        }
        return $value;
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
        if (!preg_match('/\A---[ \t]*\R(.*?)^---[ \t]*\r?$/ms', $content, $matches)) {
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
     * @return array of [string $name, array $config, string $content, int $offset],
     *     the offset is the position of the block comment in `$content`
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
                $blocks[] = [$header[0], $header[1], substr($content, $matches[2][1], strlen($matches[2][0])), $matches[0][1]];
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
     * True if the config of a block switches it off, `<!-- hero: {disabled: true} -->`.
     */
    public static function isDisabled(array $blockConfig): bool
    {
        return ($blockConfig['disabled'] ?? false) === true;
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
        $isPreview = $this->previewContent !== null && (realpath($pagePath) ?: $pagePath) === $this->previewFsPath;
        $source = $isPreview ? $this->previewContent : file_get_contents($pagePath);
        $content = trim($source);
        $this->config = [];
        if (!$content) {
            return "";
        }
        [$this->config, $body] = self::splitFrontmatter($content);
        // the line in the source where $body starts, for the preview markers
        $bodyLine = substr_count($source, "\n", 0, strlen($source) - strlen(ltrim($source)))
            + substr_count($content, "\n", 0, strlen($content) - strlen($body));
        $content = $body;

        $blocks = array();
        $blockLines = array();
        $parsedBlocks = self::parseBlocks($content);
        foreach ($parsedBlocks as [$blockName, $blockProps, $blockContent, $blockOffset]) {
            Logger::debug("found block: " . $blockName);
            // switched off in the admin, the block stays in the page but is not shown
            if (self::isDisabled($blockProps)) {
                continue;
            }
            $blocks[] = new Block($this->site, $blockName, $blockContent, $blockProps);
            $blockLines[] = $bodyLine + substr_count($content, "\n", 0, $blockOffset);
        }

        if (!count($parsedBlocks)) {
            // interpret whole pages as flat markdown file
            Logger::info("No blocks found, rendering as flat markdown file");
            $block = new Block($this->site, "text", $content);
            $blocks[] = $block;
            $blockLines[] = $bodyLine;
        } else {
            $blockNames = [];
            foreach ($blocks as $block) {
                $blockNames[] = $block->getName();
            }
            Logger::info("Blocks: " . join(", ", $blockNames));
        }

        // render blocks
        $html = "";
        foreach ($blocks as $i => $block) {
            if ($isPreview) {
                // where the block starts in the editor, the admin scrolls the preview to the cursor with it
                $html .= "<!--reboot-preview-line:" . $blockLines[$i] . "-->";
            }
            $html .= $block->render($request);
        }
        if ($isPreview) {
            $html .= "<!--reboot-preview-line:" . ($bodyLine + substr_count($content, "\n") + 1) . ":end-->";
        }

        return $html;
    }

    /**
     * A structured page, a YAML file whose blocks hold fields instead of Markdown:
     *
     * ```yaml
     * title: About us
     * blocks:
     *   - type: intro
     *     heading: Welcome
     *   - type: features
     *     disabled: true
     * ```
     *
     * The values next to the blocks are the config of the page, like the frontmatter
     * of a Markdown page. A block with `markdown` instead of fields renders with the
     * Markdown template of its type, `{type: hero, markdown: "# Welcome"}`. Each block renders with its template from `site/blocks/`,
     * which reads the fields with `$block->get()`, its blueprint is `site/blocks/<type>.yml`.
     */
    private function renderStructured(string $pagePath, ?Request $request): string
    {
        $isPreview = $this->previewContent !== null && (realpath($pagePath) ?: $pagePath) === $this->previewFsPath;
        $source = $isPreview ? $this->previewContent : file_get_contents($pagePath);
        $this->config = [];
        try {
            $data = Yaml::parse($source);
        } catch (ParseException $e) {
            Logger::error("Structured page " . $pagePath . " is no valid YAML: " . $e->getMessage());
            return "";
        }
        if (!is_array($data)) {
            return "";
        }
        $blueprint = Blueprint::forPage($this->site);
        $data = $blueprint->normalize($data);
        foreach ($blueprint->validate($data) as $message) {
            Logger::error("Structured page " . $pagePath . ": " . $message);
        }
        $blocksField = "blocks";
        foreach ($blueprint->getFields() as $name => $props) {
            if ($props["type"] === "blocks") {
                $blocksField = $name;
                break;
            }
        }
        $blocks = is_array($data[$blocksField] ?? null) ? $data[$blocksField] : [];
        unset($data[$blocksField]);
        $this->config = $data;
        $html = "";
        $names = [];
        foreach (array_values($blocks) as $index => $values) {
            if (!is_array($values) || !is_string($values["type"] ?? null) || self::isDisabled($values)) {
                continue;
            }
            if ($isPreview) {
                // which panel of the form the block is, the admin scrolls the preview to the field being edited
                $html .= "<!--reboot-preview-block:" . $index . "-->";
            }
            if (Blueprint::isMarkdownBlock($values)) {
                // a Markdown block in a structured page, the other values are its config
                $config = $values;
                unset($config["type"], $config["markdown"]);
                $block = new Block($this->site, $values["type"], $values["markdown"], $config);
            } else {
                // the fields are the config of the block as well, so `inverse: true` works as in a Markdown page
                $block = new Block($this->site, $values["type"], "", $values);
                $block->setFields($values, Blueprint::forBlock($this->site, $values["type"]));
            }
            $html .= $block->render($request);
            $names[] = $values["type"];
        }
        if ($isPreview) {
            // a click in the preview below the last block belongs to no block
            $html .= "<!--reboot-preview-block:end-->";
        }
        Logger::info("Blocks: " . join(", ", $names));
        return $html;
    }

    /**
     * An entry of a collection as a page. A blueprint with `url: blog` makes every
     * entry of its collection a page at `/blog/<slug>`, drafts and invalid entries
     * excepted. The template `site/blueprints/<name>.php` renders it with `$entry`,
     * the scalar fields of the entry are the config of the page, `title` and
     * `description` for example.
     */
    private function renderEntry(string $path, ?Request $request): ?string
    {
        if (!preg_match('#^/(.+)/([\w\-]+)$#', $path, $matches)) {
            return null;
        }
        foreach (Blueprint::all($this->site) as $name => $blueprint) {
            if ($blueprint->getUrl() !== $matches[1]) {
                continue;
            }
            $entry = $this->site->collection($name)->find($matches[2]);
            if (!$entry) {
                return null;
            }
            $template = Blueprint::folder($this->site) . "/" . $name . ".php";
            if (!is_file($template)) {
                Logger::error("The entries of '" . $name . "' are pages, but there is no template site/blueprints/" . $name . ".php");
                return null;
            }
            Logger::info("Entry page: " . $path);
            $values = $entry->getValues();
            unset($values["body"]);
            $this->config = array_filter($values, fn($value) => is_scalar($value));
            return renderEntryPage($this->reboot, $this->site, $this, $request, $template, $entry);
        }
        return null;
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
function renderEntryPage(Reboot $reboot, Site $site, Page $page, ?Request $request, string $path, Entry $entry)
{
    ob_start();
    include $path;
    return ob_get_clean();
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
