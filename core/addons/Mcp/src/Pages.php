<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

namespace Shaack\Reboot\AddOns\Mcp;

use Shaack\Reboot\AddOnLoader;
use Shaack\Reboot\AddOns\Admin\PageHistoryHelper;
use Shaack\Reboot\AddOns\Admin\PageValidator;
use Shaack\Reboot\Block;
use Shaack\Reboot\Blueprint;
use Shaack\Reboot\Site;
use Shaack\Utils\FileSystemUtils;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * The pages a token may reach, read and written like the admin does it.
 *
 * Every change needs the md5 the agent got when it read the page. If the
 * page changed in between, in the admin for example, the write is refused
 * and nothing is lost. Before a page changes, its old version goes into the
 * history of the admin, `local/history/pages/`, where it can be restored
 * under "… › History". Every change is logged in `local/mcp/changes.log`.
 */
class Pages
{
    private Site $site;
    private string $baseFsPath;
    private array $token;
    private int $historyMaxVersions;

    public function __construct(Site $site, string $baseFsPath, array $token, int $historyMaxVersions = 50)
    {
        $this->site = $site;
        $this->baseFsPath = rtrim($baseFsPath, '/');
        $this->token = $token;
        $this->historyMaxVersions = $historyMaxVersions;
        // the history and the checks of the admin, its classes are not loaded on the site
        AddOnLoader::registerAutoloader('Admin', $this->baseFsPath . '/core/admin/addons/Admin');
    }

    public function dir(): string
    {
        return (string)realpath($this->site->getFsPath() . '/pages');
    }

    /**
     * The file of a page the token may reach, as an absolute path.
     * @param bool $mustExist false for a page that is about to be created
     */
    public function file(string $path, bool $mustExist = true): string
    {
        if (!preg_match('#^(/[A-Za-z0-9_-][A-Za-z0-9._-]*)+\.(md|yml)$#', $path) || str_contains($path, '..')) {
            throw new \InvalidArgumentException("Invalid page path '$path', expected something like /blog/first-post.md");
        }
        if (!Tokens::allows($this->token['paths'] ?? [], $path)) {
            throw new \InvalidArgumentException("The token may not access '$path'");
        }
        $dir = $this->dir();
        $file = $dir . $path;
        if (is_file($file) || $mustExist) {
            $real = realpath($file);
            if (!FileSystemUtils::isInside($real, $dir) || !is_file($real)) {
                throw new \InvalidArgumentException("No page '$path'");
            }
            return $real;
        }
        // a new page: the nearest existing folder must lie inside site/pages
        $parent = dirname($file);
        while (!is_dir($parent)) {
            $parent = dirname($parent);
        }
        if (!FileSystemUtils::isInside(realpath($parent), $dir)) {
            throw new \InvalidArgumentException("Invalid page path '$path'");
        }
        return $file;
    }

    public function read(string $path): array
    {
        $file = $this->file($path);
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

    /**
     * Creates or replaces a page. `$md5` is the one of read(), null to create
     * a page that must not exist yet. A structured page must be valid YAML.
     * Problems the page would show, a block that does not exist for example,
     * do not stop the write, they come back as `problems`.
     */
    public function write(string $path, string $content, ?string $md5): array
    {
        $content = str_replace("\r\n", "\n", $content);
        if (trim($content) === '') {
            throw new \InvalidArgumentException("The content is empty, use delete_page to remove a page");
        }
        $problems = str_ends_with($path, '.yml') ? $this->checkStructured($content) : $this->checkMarkdown($content);
        $file = $this->file($path, false);
        $created = false;
        FileSystemUtils::withLock($this->lockFile(), function () use ($file, $path, $content, $md5, &$created) {
            $exists = is_file($file);
            $current = $exists ? md5_file($file) : null;
            $this->assertUnchanged($path, $current, $md5);
            if ($exists && $current === md5($content)) {
                return;
            }
            if ($exists) {
                $this->snapshot($file);
            } elseif (!is_dir(dirname($file)) && !@mkdir(dirname($file), 0755, true) && !is_dir(dirname($file))) {
                throw new \RuntimeException("Could not create the folder of $path");
            }
            FileSystemUtils::writeAtomic($file, $content);
            $created = !$exists;
            $this->log($exists ? 'changed' : 'created', $path, $current, md5($content));
        });
        return ['path' => $path, 'md5' => md5($content), 'created' => $created, 'problems' => $problems];
    }

    public function delete(string $path, string $md5): array
    {
        $file = $this->file($path);
        FileSystemUtils::withLock($this->lockFile(), function () use ($file, $path, $md5) {
            $current = md5_file($file);
            $this->assertUnchanged($path, $current, $md5);
            $this->snapshot($file);
            if (!@unlink($file)) {
                throw new \RuntimeException("Could not delete $path");
            }
            $this->log('deleted', $path, $current, null);
        });
        return ['path' => $path, 'deleted' => true, 'history' => 'local/history/pages' . PageHistoryHelper::address($path)];
    }

    /**
     * The version of a page in the history whose content has the md5, null if there is none.
     */
    public function historyVersion(string $path, string $md5): ?string
    {
        $dir = $this->historyDir() . PageHistoryHelper::address($path);
        foreach (glob($dir . '/*.' . pathinfo($path, PATHINFO_EXTENSION)) ?: [] as $snapshot) {
            if (md5_file($snapshot) === $md5) {
                return (string)file_get_contents($snapshot);
            }
        }
        return null;
    }

    private function assertUnchanged(string $path, ?string $current, ?string $md5): void
    {
        if ($current === null && $md5 !== null && $md5 !== '') {
            throw new \InvalidArgumentException("Conflict: the page '$path' does not exist (any more)");
        }
        if ($current !== null && $md5 !== $current) {
            throw new \InvalidArgumentException($md5 === null || $md5 === ''
                ? "The page '$path' exists. Read it first and pass its md5 to replace it"
                : "Conflict: '$path' was changed since it was read. Read it again and apply the change to the current version");
        }
    }

    private function snapshot(string $file): void
    {
        $historyDir = $this->historyDir();
        if (!is_dir($historyDir)) {
            @mkdir($historyDir, 0755, true);
        }
        PageHistoryHelper::savePageSnapshot($file, $this->dir(), $historyDir, $this->historyMaxVersions);
    }

    /** One lock for all writes of the endpoint, in local/mcp/ */
    private function lockFile(): string
    {
        $dir = $this->baseFsPath . '/local/mcp';
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }
        return $dir . '/pages';
    }

    private function historyDir(): string
    {
        return $this->baseFsPath . '/local/history/pages';
    }

    private function log(string $action, string $path, ?string $before, ?string $after): void
    {
        $dir = $this->baseFsPath . '/local/mcp';
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }
        $line = json_encode([
            'time' => date('c'),
            'token' => $this->token['name'] ?? '',
            'action' => $action,
            'path' => $path,
            'before' => $before,
            'after' => $after,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        @file_put_contents($dir . '/changes.log', $line . "\n", FILE_APPEND | LOCK_EX);
    }

    /**
     * @return string[] the problems the admin's editor would show
     */
    private function checkMarkdown(string $content): array
    {
        return array_map(fn($problem) => 'Line ' . ($problem['line'] + 1) . ': ' . $problem['message'],
            PageValidator::validate($this->site, $content));
    }

    /**
     * @return string[] block types that do not exist
     */
    private function checkStructured(string $content): array
    {
        try {
            $data = Yaml::parse($content);
        } catch (ParseException $e) {
            throw new \InvalidArgumentException("No valid YAML: " . $e->getMessage());
        }
        if (!is_array($data) || array_is_list($data)) {
            throw new \InvalidArgumentException("A structured page is a YAML map with title, description and blocks");
        }
        $problems = [];
        $names = Block::names($this->site);
        foreach ((array)($data['blocks'] ?? []) as $index => $block) {
            $type = is_array($block) ? ($block['type'] ?? null) : null;
            if (!is_string($type)) {
                $problems[] = "Block $index has no type";
            } elseif (!in_array($type, $names, true)) {
                $problems[] = "Block $index: there is no block type '$type'";
            } elseif (!Blueprint::isMarkdownBlock($block) && Blueprint::forBlock($this->site, $type) === null) {
                $problems[] = "Block $index: '$type' has no fields, give it Markdown with markdown: \"…\"";
            }
        }
        return $problems;
    }
}
