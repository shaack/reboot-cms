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
 * or `/about.de.md`, see Pages. PHP pages are code and stay out of reach, so
 * do templates, configuration and everything in `local/`. Tools that change
 * something need a token with write access.
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
    private Pages $pages;
    private string $uploadUrl;
    private ?array $tools = null;

    /**
     * @param array $token the entry of the token that makes the request, see Tokens
     * @param string $uploadUrl the absolute address the upload addresses start with,
     *        `https://example.com/mcp/upload/`, see Uploads
     */
    public function __construct(Site $site, string $baseFsPath, string $baseWebPath, array $token, int $historyMaxVersions = 50,
                                string $uploadUrl = '')
    {
        $this->site = $site;
        $this->baseFsPath = rtrim($baseFsPath, '/');
        $this->baseWebPath = rtrim($baseWebPath, '/');
        $this->token = $token;
        $this->pages = new Pages($site, $baseFsPath, $token, $historyMaxVersions);
        $this->uploadUrl = $uploadUrl;
    }

    /**
     * The tools the token may use, in the form of `tools/list`.
     */
    public function definitions(): array
    {
        $result = [];
        foreach ($this->tools() as $name => $tool) {
            if (!$this->mayUse($tool, $name)) {
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
        if (!$this->mayUse($tool, $name)) {
            throw new \InvalidArgumentException("The token may not use the tool '$name'");
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

    private function mayUse(array $tool, ?string $name = null): bool
    {
        $only = $this->token['tools'] ?? [];
        if ($name !== null && $only && !in_array($name, $only, true)) {
            return false;
        }
        return ($tool['access'] ?? 'read') === 'read' || ($this->token['access'] ?? 'read') === 'write';
    }

    /**
     * The names of all tools, for the admin, whatever a token may use.
     * @return string[]
     */
    public function names(): array
    {
        return array_keys($this->tools());
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
                'handler' => fn(array $args) => $this->pages->read((string)($args['path'] ?? '')),
            ],
            'write_page' => [
                'description' => 'Creates or replaces a page with its complete source. To change a page pass the md5 from read_page, the write is refused if the page changed in between. To create a page leave md5 out. The previous version goes into the history of the admin. Problems the page would show, a block type that does not exist for example, come back as problems.',
                'inputSchema' => ['type' => 'object', 'properties' => [
                    'path' => ['type' => 'string', 'description' => 'The file in site/pages, for example /blog/new-post.md'],
                    'content' => ['type' => 'string', 'description' => 'The complete source of the page'],
                    'md5' => ['type' => 'string', 'description' => 'The md5 from read_page, empty for a new page'],
                ], 'required' => ['path', 'content']],
                'access' => 'write',
                'handler' => fn(array $args) => $this->pages->write((string)($args['path'] ?? ''), (string)($args['content'] ?? ''),
                    ($args['md5'] ?? '') === '' ? null : (string)$args['md5']),
            ],
            'delete_page' => [
                'description' => 'Deletes a page. Needs the md5 from read_page. The page stays in the history of the admin.',
                'inputSchema' => ['type' => 'object', 'properties' => [
                    'path' => ['type' => 'string'],
                    'md5' => ['type' => 'string', 'description' => 'The md5 from read_page'],
                ], 'required' => ['path', 'md5']],
                'access' => 'write',
                'handler' => fn(array $args) => $this->pages->delete((string)($args['path'] ?? ''), (string)($args['md5'] ?? '')),
            ],
            'upload_media' => [
                'description' => 'Stores a small file in web/media, an image or a download for a page, as base64. For a larger file or several files use create_upload_url, base64 costs a lot of context. Only for tokens with the path /. An existing file is only replaced with the md5 of its current content.',
                'inputSchema' => ['type' => 'object', 'properties' => [
                    'path' => ['type' => 'string', 'description' => 'The file below web/media, for example /blog/2026/team.jpg'],
                    'base64' => ['type' => 'string', 'description' => 'The content of the file, base64 encoded'],
                    'md5' => ['type' => 'string', 'description' => 'The md5 of the file to replace, empty for a new file'],
                ], 'required' => ['path', 'base64']],
                'access' => 'write',
                'handler' => fn(array $args) => $this->uploadMedia((string)($args['path'] ?? ''), (string)($args['base64'] ?? ''), (string)($args['md5'] ?? '')),
            ],
            'create_upload_url' => [
                'description' => 'Returns an address to send a file for web/media to with a plain HTTP PUT, for example curl -T team.jpg <url>. The file does not pass through the conversation, better than upload_media for images and larger files. The address works once, for 15 minutes, and only for this path, up to 20 MB. Only for tokens with the path /. An existing file is only replaced with the md5 of its current content.',
                'inputSchema' => ['type' => 'object', 'properties' => [
                    'path' => ['type' => 'string', 'description' => 'The file below web/media, for example /blog/2026/team.jpg'],
                    'md5' => ['type' => 'string', 'description' => 'The md5 of the file to replace, empty for a new file'],
                ], 'required' => ['path']],
                'access' => 'write',
                'handler' => fn(array $args) => $this->createUploadUrl((string)($args['path'] ?? ''), (string)($args['md5'] ?? '')),
            ],
            'list_translations' => [
                'description' => 'Lists the translations that are missing, outdated because their source changed, or of unknown state, for the pages this token may read. A Markdown page has a file per language, a structured page holds a value per language in its text fields.',
                'inputSchema' => ['type' => 'object', 'properties' => [
                    'language' => ['type' => 'string', 'description' => 'Only this language, for example de'],
                    'all' => ['type' => 'boolean', 'description' => 'Also list the current translations'],
                ]],
                'access' => 'read',
                'handler' => fn(array $args) => $this->translations()->overview(isset($args['language']) ? (string)$args['language'] : null, !empty($args['all'])),
            ],
            'read_translation' => [
                'description' => 'Source and translation of a page side by side. For a Markdown page the whole texts, with the previous source if the translation is outdated. For a structured page the text fields with a pointer each. A plain value without languages holds for all languages and counts as missing, write it unchanged if it needs no translation. Status unknown means the translation exists but was not written through this server, write it unchanged to confirm it.',
                'inputSchema' => ['type' => 'object', 'properties' => [
                    'path' => ['type' => 'string', 'description' => 'The page in the default language, for example /about.md or /index.yml'],
                    'language' => ['type' => 'string', 'description' => 'The language of the translation, for example de'],
                ], 'required' => ['path', 'language']],
                'access' => 'read',
                'handler' => fn(array $args) => $this->translations()->read((string)($args['path'] ?? ''), (string)($args['language'] ?? '')),
            ],
            'write_translation' => [
                'description' => 'Writes a translation. For a Markdown page pass the complete translated source as content, with sourceMd5 and targetMd5 (empty for a new translation) from read_translation. For a structured page pass fields, an object of pointer => translated text, with md5 from read_translation. Only the language of the translation changes.',
                'inputSchema' => ['type' => 'object', 'properties' => [
                    'path' => ['type' => 'string', 'description' => 'The page in the default language'],
                    'language' => ['type' => 'string'],
                    'content' => ['type' => 'string', 'description' => 'Markdown page: the translated source'],
                    'sourceMd5' => ['type' => 'string', 'description' => 'Markdown page: sourceMd5 from read_translation'],
                    'targetMd5' => ['type' => 'string', 'description' => 'Markdown page: targetMd5 from read_translation, empty for a new translation'],
                    'fields' => ['type' => 'object', 'additionalProperties' => ['type' => 'string'], 'description' => 'Structured page: pointer => translated text'],
                    'md5' => ['type' => 'string', 'description' => 'Structured page: md5 from read_translation'],
                ], 'required' => ['path', 'language']],
                'access' => 'write',
                'handler' => fn(array $args) => $this->writeTranslation($args),
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

    private function uploadMedia(string $path, string $base64, string $md5): array
    {
        Media::check($this->token, $path);
        $content = base64_decode($base64, true);
        if ($content === false || $content === '') {
            throw new \InvalidArgumentException("The content is no valid base64");
        }
        return $this->media()->store($this->token, $path, $content, $md5);
    }

    private function createUploadUrl(string $path, string $md5): array
    {
        Media::check($this->token, $path);
        if ($this->uploadUrl === '') {
            throw new \RuntimeException("This endpoint offers no upload addresses");
        }
        // refused now already, not only after the file was sent
        $this->media()->checkReplace($path, $md5);
        $upload = (new Uploads($this->baseFsPath))->create($path, $md5, (string)($this->token['id'] ?? ''));
        $url = $this->uploadUrl . $upload['id'];
        return [
            'url' => $url,
            'method' => 'PUT',
            'path' => $path,
            'expiresAt' => gmdate('c', $upload['expiresAt']),
            'maxBytes' => Media::MAX_BYTES,
            'example' => 'curl -sS -T <file> ' . $url,
        ];
    }

    private function media(): Media
    {
        return new Media($this->baseFsPath, $this->baseWebPath);
    }

    private function translations(): Translations
    {
        return new Translations($this->site, $this->pages, $this->baseFsPath);
    }

    private function writeTranslation(array $args): array
    {
        $path = (string)($args['path'] ?? '');
        $language = (string)($args['language'] ?? '');
        if (is_array($args['fields'] ?? null)) {
            return $this->translations()->writeFields($path, $language, $args['fields'], (string)($args['md5'] ?? ''));
        }
        if (!is_string($args['content'] ?? null)) {
            throw new \InvalidArgumentException("Pass content for a Markdown page or fields for a structured page");
        }
        $targetMd5 = (string)($args['targetMd5'] ?? '');
        return $this->translations()->writeMarkdown($path, $language, $args['content'], $targetMd5 === '' ? null : $targetMd5,
            (string)($args['sourceMd5'] ?? ''));
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
                'tools' => $this->token['tools'] ?? [],
            ],
            'navigation' => $config['nav'] ?? null,
            'guide' => $this->guide(),
        ];
    }

    private function listPages(string $folder): array
    {
        $dir = $this->pages->dir();
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
