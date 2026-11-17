<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

namespace Shaack\Reboot\AddOns\Mcp;

use Shaack\Reboot\Blueprint;
use Shaack\Reboot\Block;
use Shaack\Reboot\Site;
use Shaack\Utils\FileSystemUtils;
use Symfony\Component\Yaml\Yaml;

/**
 * The tools of the MCP endpoint. A tool is a name, a description for the
 * agent, a JSON schema of its arguments, the access it needs and a handler
 * that returns an array, which goes to the agent as JSON.
 *
 * Pages are addressed by their file in `site/pages/`, `/blog/first-post.md`
 * or `/about.de.md`. PHP pages are code and stay out of reach, so do
 * templates, configuration and everything in `local/`.
 *
 * Other AddOns add tools with a method `getMcpTools()` that returns them in
 * the same form, see `definitions()`. Their handler gets the arguments and
 * the token.
 */
class Tools
{
    private Site $site;
    private string $baseFsPath;
    private string $baseWebPath;
    private array $token;
    private ?array $tools = null;

    /**
     * @param array $token the entry of the token that makes the request, see Tokens
     */
    public function __construct(Site $site, string $baseFsPath, string $baseWebPath, array $token)
    {
        $this->site = $site;
        $this->baseFsPath = rtrim($baseFsPath, '/');
        $this->baseWebPath = rtrim($baseWebPath, '/');
        $this->token = $token;
    }

    /**
     * The tools the token may use, in the form of `tools/list`.
     */
    public function definitions(): array
    {
        $result = [];
        foreach ($this->tools() as $name => $tool) {
            if (!$this->mayUse($tool)) {
                continue;
            }
            $result[] = [
                'name' => $name,
                'description' => $tool['description'],
                'inputSchema' => $tool['inputSchema'],
                'annotations' => ['readOnlyHint' => ($tool['access'] ?? 'read') === 'read'],
            ];
        }
        return $result;
    }

    /**
     * Runs a tool. Wrong arguments and refused access throw an
     * \InvalidArgumentException, whose message goes to the agent.
     */
    public function call(string $name, array $arguments): array
    {
        $tool = $this->tools()[$name] ?? null;
        if ($tool === null) {
            throw new \InvalidArgumentException("Unknown tool '$name'");
        }
        if (!$this->mayUse($tool)) {
            throw new \InvalidArgumentException("The token has no " . $tool['access'] . " access");
        }
        return ($tool['handler'])($arguments, $this->token);
    }

    /**
     * The guide for agents, `site/agents.md`, null if the site has none.
     */
    public function guide(): ?string
    {
        $file = $this->site->getFsPath() . '/agents.md';
        return is_file($file) ? (string)file_get_contents($file) : null;
    }

    private function mayUse(array $tool): bool
    {
        return ($tool['access'] ?? 'read') === 'read' || ($this->token['access'] ?? 'read') === 'write';
    }

    private function tools(): array
    {
        if ($this->tools !== null) {
            return $this->tools;
        }
        $none = ['type' => 'object', 'properties' => new \stdClass()];
        $this->tools = [
            'site_info' => [
                'description' => 'Overview of the site: languages, block types, collections, the paths this token may touch and the guide for agents in site/agents.md. Call it first.',
                'inputSchema' => $none,
                'access' => 'read',
                'handler' => fn() => $this->siteInfo(),
            ],
            'list_pages' => [
                'description' => 'Lists the content pages in site/pages, Markdown (.md) and structured pages (.yml), with address, language, title and md5. A page in another language is a file of its own, about.de.md next to about.md.',
                'inputSchema' => ['type' => 'object', 'properties' => [
                    'folder' => ['type' => 'string', 'description' => 'Only pages below this folder, for example /blog'],
                ]],
                'access' => 'read',
                'handler' => fn(array $args) => $this->listPages((string)($args['folder'] ?? '/')),
            ],
            'read_page' => [
                'description' => 'Returns the source of a page, Markdown with optional YAML front matter or the YAML of a structured page, and its md5.',
                'inputSchema' => ['type' => 'object', 'properties' => [
                    'path' => ['type' => 'string', 'description' => 'The file in site/pages, for example /blog/first-post.md or /index.yml'],
                ], 'required' => ['path']],
                'access' => 'read',
                'handler' => fn(array $args) => $this->readPage((string)($args['path'] ?? '')),
            ],
            'list_block_types' => [
                'description' => 'The block types of the site. Structured blocks have fields, a structured page lists its blocks with type and fields. Markdown blocks are named in a comment, <!-- name -->, in Markdown pages.',
                'inputSchema' => $none,
                'access' => 'read',
                'handler' => fn() => $this->listBlockTypes(),
            ],
            'list_media' => [
                'description' => 'Lists the files and folders in web/media, the images and downloads pages link to.',
                'inputSchema' => ['type' => 'object', 'properties' => [
                    'folder' => ['type' => 'string', 'description' => 'A folder below web/media, for example /products'],
                ]],
                'access' => 'read',
                'handler' => fn(array $args) => $this->listMedia((string)($args['folder'] ?? '/')),
            ],
        ];
        foreach ($this->site->getAddOns() as $addOn) {
            if (method_exists($addOn, 'getMcpTools')) {
                foreach ($addOn->getMcpTools() as $name => $tool) {
                    $this->tools[$name] ??= $tool;
                }
            }
        }
        return $this->tools;
    }

    private function siteInfo(): array
    {
        $config = $this->site->getConfig();
        return [
            'webPath' => $this->site->getWebPath() ?: '/',
            'languages' => $this->site->getLanguages(),
            'defaultLanguage' => $this->site->getDefaultLanguage(),
            'version' => $this->version(),
            'addons' => array_keys($this->site->getAddOns()),
            'blockTypes' => Block::names($this->site),
            'collections' => array_keys(Blueprint::all($this->site)),
            'token' => [
                'name' => $this->token['name'] ?? '',
                'access' => $this->token['access'] ?? 'read',
                'paths' => $this->token['paths'] ?? [],
            ],
            'navigation' => $config['nav'] ?? null,
            'guide' => $this->guide(),
        ];
    }

    private function listPages(string $folder): array
    {
        $dir = $this->pagesDir();
        $folder = '/' . trim($folder, '/');
        $start = realpath($dir . $folder);
        if (!FileSystemUtils::isInside($start, $dir) || !is_dir($start)) {
            throw new \InvalidArgumentException("No folder '$folder' in site/pages");
        }
        $pages = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveCallbackFilterIterator(
            new \RecursiveDirectoryIterator($start, \FilesystemIterator::SKIP_DOTS),
            fn(\SplFileInfo $file) => !str_starts_with($file->getFilename(), '.')
        ));
        /** @var \SplFileInfo $file */
        foreach ($files as $file) {
            if (!in_array($file->getExtension(), ['md', 'yml'], true)) {
                continue;
            }
            $path = str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), strlen($dir)));
            if (!Tokens::allows($this->token['paths'] ?? [], $path)) {
                continue;
            }
            [$address, $language] = $this->site->pageFileAddress($path);
            $pages[] = [
                'path' => $path,
                'url' => $this->site->getWebPath() . $this->site->languagePath($address, $language),
                'language' => $language,
                'title' => $this->title($file->getPathname()),
                'md5' => md5_file($file->getPathname()),
                'modified' => date('c', $file->getMTime()),
            ];
        }
        usort($pages, fn($a, $b) => strcmp($a['path'], $b['path']));
        return ['pages' => $pages];
    }

    private function readPage(string $path): array
    {
        $file = $this->pageFile($path);
        [$address, $language] = $this->site->pageFileAddress($path);
        $content = (string)file_get_contents($file);
        return [
            'path' => $path,
            'url' => $this->site->getWebPath() . $this->site->languagePath($address, $language),
            'language' => $language,
            'format' => str_ends_with($path, '.yml') ? 'structured' : 'markdown',
            'md5' => md5($content),
            'modified' => date('c', filemtime($file)),
            'content' => $content,
        ];
    }

    private function listBlockTypes(): array
    {
        $structured = [];
        foreach (Blueprint::blockTypes($this->site) as $name => $blueprint) {
            $structured[] = [
                'name' => $name,
                'title' => $blueprint->getTitle(),
                'alsoInMarkdown' => $blueprint->isForMarkdown(),
                'fields' => self::describeFields($blueprint->getFields()),
            ];
        }
        $markdown = array_values(array_filter(Block::names($this->site),
            fn($name) => is_file($this->site->getFsPath() . '/blocks/' . $name . '.php')));
        return [
            'structured' => $structured,
            'markdown' => $markdown,
            'translatableTypes' => $this->site->isMultilingual() ? Blueprint::TRANSLATABLE_TYPES : [],
        ];
    }

    private function listMedia(string $folder): array
    {
        $root = realpath($this->baseFsPath . '/web/media');
        $folder = '/' . trim($folder, '/');
        $dir = $root === false ? false : realpath($root . $folder);
        if (!FileSystemUtils::isInside($dir, $root) || !is_dir($dir)) {
            throw new \InvalidArgumentException("No folder '$folder' in web/media");
        }
        $items = [];
        foreach (scandir($dir) ?: [] as $name) {
            if (str_starts_with($name, '.')) {
                continue;
            }
            $full = $dir . '/' . $name;
            $relative = rtrim($folder, '/') . '/' . $name;
            $isDir = is_dir($full);
            $items[] = [
                'name' => $name,
                'path' => $relative,
                'isFolder' => $isDir,
                'url' => $this->baseWebPath . '/media' . $relative,
                'size' => $isDir ? null : filesize($full),
            ];
        }
        return ['folder' => $folder, 'items' => $items];
    }

    /**
     * The file of a page the token may read, as an absolute path.
     */
    private function pageFile(string $path): string
    {
        if (!preg_match('#^(/[A-Za-z0-9_-][A-Za-z0-9._-]*)+\.(md|yml)$#', $path) || str_contains($path, '..')) {
            throw new \InvalidArgumentException("Invalid page path '$path', expected something like /blog/first-post.md");
        }
        if (!Tokens::allows($this->token['paths'] ?? [], $path)) {
            throw new \InvalidArgumentException("The token may not access '$path'");
        }
        $dir = $this->pagesDir();
        $file = realpath($dir . $path);
        if (!FileSystemUtils::isInside($file, $dir) || !is_file($file)) {
            throw new \InvalidArgumentException("No page '$path'");
        }
        return $file;
    }

    private function pagesDir(): string
    {
        return (string)realpath($this->site->getFsPath() . '/pages');
    }

    private function title(string $file): ?string
    {
        try {
            if (str_ends_with($file, '.yml')) {
                $data = Yaml::parseFile($file);
            } else {
                $head = (string)file_get_contents($file, false, null, 0, 4096);
                if (!preg_match('/^---\R(.*?)\R---/s', $head, $matches)) {
                    return null;
                }
                $data = Yaml::parse($matches[1]);
            }
        } catch (\Exception $e) {
            return null;
        }
        $title = is_array($data) ? $this->site->localize($data['title'] ?? null, $this->site->getDefaultLanguage()) : null;
        return is_scalar($title) ? (string)$title : null;
    }

    /**
     * The fields of a blueprint as plain data, the blueprints of lists resolved to their fields.
     */
    private static function describeFields(array $fields): array
    {
        $result = [];
        foreach ($fields as $name => $props) {
            if (isset($props['blueprint']) && $props['blueprint'] instanceof Blueprint) {
                $props['fields'] = self::describeFields($props['blueprint']->getFields());
                unset($props['blueprint']);
            }
            $result[$name] = $props;
        }
        return $result;
    }

    private function version(): ?string
    {
        $data = json_decode((string)@file_get_contents($this->baseFsPath . '/composer.json'), true);
        return is_array($data) ? ($data['version'] ?? null) : null;
    }
}
