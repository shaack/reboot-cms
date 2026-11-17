<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

namespace Shaack\Reboot\AddOns\Mcp;

use Shaack\Reboot\Blueprint;
use Shaack\Reboot\Page;
use Shaack\Reboot\Site;
use Shaack\Reboot\YamlWriter;
use Shaack\Utils\FileSystemUtils;
use Symfony\Component\Yaml\Yaml;

/**
 * Finds missing and outdated translations and writes them. The agent
 * translates, this class knows what to translate and where it goes.
 *
 * A Markdown page has a file per language, `about.de.md` next to `about.md`.
 * A structured page keeps all languages in one file, its text fields hold a
 * value per language, `heading: {en: Welcome, de: Willkommen}`.
 *
 * When a translation is written, the md5 of its source goes into
 * `local/mcp/translations.json`. If the source changes later, the
 * translation counts as outdated, and the agent gets the previous version of
 * the source from the history to see what changed.
 *
 * Status of a translation: `missing`, `outdated`, `current`, or `unknown`
 * for one that exists but was not written through this endpoint.
 */
class Translations
{
    private Site $site;
    private Pages $pages;
    private string $file;

    public function __construct(Site $site, Pages $pages, string $baseFsPath)
    {
        if (!$site->isMultilingual()) {
            throw new \InvalidArgumentException("The site has one language, there is nothing to translate");
        }
        $this->site = $site;
        $this->pages = $pages;
        $this->file = rtrim($baseFsPath, '/') . '/local/mcp/translations.json';
    }

    /**
     * The translations of all pages the token may read, without the current ones.
     */
    public function overview(?string $language, bool $all = false): array
    {
        $languages = $this->targetLanguages($language);
        $dir = $this->pages->dir();
        $result = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveCallbackFilterIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            fn(\SplFileInfo $file) => !str_starts_with($file->getFilename(), '.')
        ));
        /** @var \SplFileInfo $file */
        foreach ($files as $file) {
            $path = str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), strlen($dir)));
            if (!in_array($file->getExtension(), ['md', 'yml'], true) || !$this->isSource($path)) {
                continue;
            }
            try {
                $this->pages->file($path);
            } catch (\InvalidArgumentException $e) {
                continue; // outside the paths of the token
            }
            foreach ($languages as $target) {
                $status = $file->getExtension() === 'md'
                    ? $this->markdownStatus($path, $target)
                    : $this->structuredStatus($path, $target);
                if ($all || $status['status'] !== 'current') {
                    $result[] = ['path' => $path, 'language' => $target] + $status;
                }
            }
        }
        usort($result, fn($a, $b) => [$a['path'], $a['language']] <=> [$b['path'], $b['language']]);
        return ['sourceLanguage' => $this->site->getDefaultLanguage(), 'translations' => $result];
    }

    /**
     * Source and translation side by side, for a Markdown page the whole
     * texts, for a structured page the text fields.
     */
    public function read(string $path, string $language): array
    {
        $this->assertSource($path, $language);
        $source = $this->pages->read($path);
        if (str_ends_with($path, '.md')) {
            $targetPath = $this->targetPath($path, $language);
            $target = is_file($this->pages->dir() . $targetPath) ? $this->pages->read($targetPath) : null;
            $status = $this->markdownStatus($path, $language);
            $result = [
                'path' => $path,
                'language' => $language,
                'status' => $status['status'],
                'source' => $source['content'],
                'sourceMd5' => $source['md5'],
                'targetPath' => $targetPath,
                'target' => $target['content'] ?? null,
                'targetMd5' => $target['md5'] ?? null,
            ];
            if ($status['status'] === 'outdated') {
                $result['previousSource'] = $this->pages->historyVersion($path, $this->recorded()[$targetPath]['source'] ?? '');
            }
            return $result;
        }
        $fields = [];
        $recorded = $this->recorded()[$path . '#' . $language]['fields'] ?? [];
        foreach ($this->fields($this->parse($source['content'])) as $pointer => $value) {
            [$text, $translation] = $this->texts($value, $language);
            if ($text === null) {
                continue;
            }
            $fields[] = [
                'pointer' => $pointer,
                'source' => $text,
                'target' => $translation,
                'status' => $this->fieldStatus($text, $translation, $recorded[$pointer] ?? null),
            ];
        }
        return [
            'path' => $path,
            'language' => $language,
            'md5' => $source['md5'],
            'fields' => $fields,
        ];
    }

    /**
     * Writes the translation of a Markdown page, the whole text in `content`,
     * with the `sourceMd5` of read(). `md5` is the one of the translation,
     * null if there is none yet.
     */
    public function writeMarkdown(string $path, string $language, string $content, ?string $md5, string $sourceMd5): array
    {
        $this->assertSource($path, $language);
        if (!str_ends_with($path, '.md')) {
            throw new \InvalidArgumentException("'$path' is a structured page, translate it with fields");
        }
        $this->assertSourceUnchanged($path, $sourceMd5);
        $targetPath = $this->targetPath($path, $language);
        $result = $this->pages->write($targetPath, $content, $md5);
        $this->record($targetPath, ['source' => $sourceMd5]);
        return $result;
    }

    /**
     * Writes translated text fields of a structured page, `pointer => text`,
     * with the `md5` of the page from read().
     */
    public function writeFields(string $path, string $language, array $translations, string $md5): array
    {
        $this->assertSource($path, $language);
        if (!str_ends_with($path, '.yml')) {
            throw new \InvalidArgumentException("'$path' is a Markdown page, translate it with content");
        }
        if (!$translations) {
            throw new \InvalidArgumentException("No fields to translate");
        }
        $page = $this->pages->read($path);
        if ($page['md5'] !== $md5) {
            throw new \InvalidArgumentException("Conflict: '$path' was changed since it was read. Read it again");
        }
        $data = $this->parse($page['content']);
        $fields = $this->fields($data);
        $default = $this->site->getDefaultLanguage();
        $recorded = [];
        foreach ($translations as $pointer => $text) {
            if (!isset($fields[$pointer]) || !is_string($text)) {
                throw new \InvalidArgumentException("'$pointer' is no text field of '$path'");
            }
            [$source] = $this->texts($fields[$pointer], $language);
            if ($source === null) {
                throw new \InvalidArgumentException("'$pointer' has no text in '$default' to translate");
            }
            $value = $fields[$pointer];
            $map = $this->site->isLanguageMap($value) ? $value : [$default => $value];
            $map[$language] = $text;
            self::set($data, $pointer, $map);
            $recorded[$pointer] = md5($source);
        }
        $result = $this->pages->write($path, YamlWriter::dump($data), $md5);
        $key = $path . '#' . $language;
        $this->record($key, ['fields' => array_merge($this->recorded()[$key]['fields'] ?? [], $recorded)]);
        return $result;
    }

    private function markdownStatus(string $path, string $language): array
    {
        $targetPath = $this->targetPath($path, $language);
        if (!is_file($this->pages->dir() . $targetPath)) {
            return ['status' => 'missing', 'targetPath' => $targetPath];
        }
        $recorded = $this->recorded()[$targetPath]['source'] ?? null;
        $status = $recorded === null ? 'unknown'
            : ($recorded === md5_file($this->pages->dir() . $path) ? 'current' : 'outdated');
        return ['status' => $status, 'targetPath' => $targetPath];
    }

    private function structuredStatus(string $path, string $language): array
    {
        $recorded = $this->recorded()[$path . '#' . $language]['fields'] ?? [];
        $counts = ['missing' => 0, 'outdated' => 0, 'unknown' => 0, 'current' => 0];
        try {
            $data = $this->parse((string)file_get_contents($this->pages->dir() . $path));
        } catch (\InvalidArgumentException $e) {
            return ['status' => 'unknown', 'fields' => $counts];
        }
        foreach ($this->fields($data) as $pointer => $value) {
            [$text, $translation] = $this->texts($value, $language);
            if ($text !== null) {
                $counts[$this->fieldStatus($text, $translation, $recorded[$pointer] ?? null)]++;
            }
        }
        $status = 'current';
        foreach (['missing', 'outdated', 'unknown'] as $candidate) {
            if ($counts[$candidate] > 0) {
                $status = $candidate;
                break;
            }
        }
        return ['status' => $status, 'fields' => $counts];
    }

    private function fieldStatus(string $text, ?string $translation, ?string $recorded): string
    {
        if ($translation === null || $translation === '') {
            return 'missing';
        }
        if ($recorded === null) {
            return 'unknown';
        }
        return $recorded === md5($text) ? 'current' : 'outdated';
    }

    /**
     * The text of the default language and the translation of a field value.
     * @return array [?string $source, ?string $translation]
     */
    private function texts(mixed $value, string $language): array
    {
        $default = $this->site->getDefaultLanguage();
        if ($this->site->isLanguageMap($value)) {
            $source = $value[$default] ?? null;
            $translation = $value[$language] ?? null;
        } else {
            $source = $value;
            $translation = null;
        }
        $source = is_scalar($source) && trim((string)$source) !== '' ? (string)$source : null;
        return [$source, is_scalar($translation) ? (string)$translation : null];
    }

    /**
     * The translatable text fields of a structured page, `pointer => value`.
     * A pointer is the path of keys, `title`, `blocks.2.heading`,
     * `blocks.3.items.0.caption` or `blocks.4.markdown`.
     */
    private function fields(array $data): array
    {
        $result = [];
        $this->collect(Blueprint::forPage($this->site), $data, '', $result);
        return $result;
    }

    private function collect(Blueprint $blueprint, array $values, string $prefix, array &$result): void
    {
        foreach ($blueprint->getFields() as $name => $props) {
            if (!array_key_exists($name, $values)) {
                continue;
            }
            $value = $values[$name];
            $pointer = $prefix . $name;
            if ($props['type'] === 'list' && is_array($value) && array_is_list($value)) {
                foreach ($value as $index => $item) {
                    if (is_array($item)) {
                        $this->collect($props['blueprint'], $item, $pointer . '.' . $index . '.', $result);
                    }
                }
            } elseif ($props['type'] === 'blocks' && is_array($value)) {
                foreach ($value as $index => $block) {
                    if (!is_array($block) || !is_string($block['type'] ?? null)) {
                        continue;
                    }
                    $blockPointer = $pointer . '.' . $index . '.';
                    if (Blueprint::isMarkdownBlock($block)) {
                        $result[$blockPointer . 'markdown'] = $block['markdown'];
                    } elseif ($blockBlueprint = Blueprint::forBlock($this->site, $block['type'])) {
                        $this->collect($blockBlueprint, $block, $blockPointer, $result);
                    }
                }
            } elseif (Blueprint::isTranslatable($props) && in_array($props['type'], ['text', 'textarea', 'markdown'], true)) {
                // images and links are translatable too, but there is nothing for an agent to translate
                $result[$pointer] = $value;
            }
        }
    }

    private static function set(array &$data, string $pointer, mixed $value): void
    {
        $node = &$data;
        foreach (explode('.', $pointer) as $key) {
            $node = &$node[ctype_digit($key) ? (int)$key : $key];
        }
        $node = $value;
    }

    private function parse(string $content): array
    {
        try {
            $data = Page::datesAsText(Yaml::parse($content));
        } catch (\Exception $e) {
            throw new \InvalidArgumentException("The page is no valid YAML: " . $e->getMessage());
        }
        return is_array($data) ? $data : [];
    }

    /**
     * Whether a page file is in the default language, `about.md` but not `about.de.md`.
     */
    private function isSource(string $path): bool
    {
        if (str_ends_with($path, '.yml')) {
            return true;
        }
        return $this->site->pageFileAddress($path)[1] === $this->site->getDefaultLanguage();
    }

    private function assertSource(string $path, string $language): void
    {
        $this->targetLanguages($language);
        if (!$this->isSource($path)) {
            throw new \InvalidArgumentException("'$path' is a translation, pass the page in " . $this->site->getDefaultLanguage());
        }
    }

    private function assertSourceUnchanged(string $path, string $sourceMd5): void
    {
        if ($this->pages->read($path)['md5'] !== $sourceMd5) {
            throw new \InvalidArgumentException("Conflict: the source '$path' was changed since it was read. Read the translation again");
        }
    }

    /**
     * @return string[] the languages to translate into, all but the default one or the one asked for
     */
    private function targetLanguages(?string $language): array
    {
        $languages = array_values(array_diff($this->site->getLanguages(), [$this->site->getDefaultLanguage()]));
        if ($language === null || $language === '') {
            return $languages;
        }
        if (!in_array($language, $languages, true)) {
            throw new \InvalidArgumentException("'$language' is no language to translate into, the site has " . implode(', ', $languages));
        }
        return [$language];
    }

    /**
     * `about.md` in "de" is `about.de.md`.
     */
    private function targetPath(string $path, string $language): string
    {
        return substr($path, 0, -3) . '.' . $language . '.md';
    }

    private function recorded(): array
    {
        if (!is_file($this->file)) {
            return [];
        }
        $data = json_decode((string)file_get_contents($this->file), true);
        return is_array($data) ? $data : [];
    }

    private function record(string $key, array $entry): void
    {
        if (!is_dir(dirname($this->file))) {
            @mkdir(dirname($this->file), 0700, true);
        }
        FileSystemUtils::updateJson($this->file, function (array &$data) use ($key, $entry) {
            $data[$key] = $entry;
            return true;
        });
    }
}
