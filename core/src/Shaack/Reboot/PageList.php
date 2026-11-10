<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

namespace Shaack\Reboot;

use Shaack\Logger;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * The pages of a folder in `site/pages/`, with the queries of a collection.
 * The posts of a blog, for example, as Markdown pages with a frontmatter:
 *
 * ```php
 * $posts = $site->pages("blog")->sortBy("date", "desc")->limit(5);
 * foreach ($posts as $post) { ... }
 * ```
 *
 * The list holds the Markdown and structured pages of the folder, not the
 * pages in its subfolders, not its `index` and no page with `draft: true`.
 * A page that is there in several languages is one entry, see PageEntry.
 */
class PageList extends EntryList
{
    /**
     * @param PageEntry[] $entries
     */
    public function __construct(array $entries = [])
    {
        $this->entries = array_values($entries);
    }

    /**
     * The pages of a folder, `blog` for `site/pages/blog/`, sorted by their file name.
     */
    public static function load(Site $site, string $folder): PageList
    {
        $folder = trim(str_replace("\\", "/", $folder), "/");
        if (preg_match('#(^|/)\.\.?(/|$)#', $folder)) {
            return new PageList();
        }
        $dir = $site->getFsPath() . "/pages" . ($folder !== "" ? "/" . $folder : "");
        // the files of a page by its address, the page of all languages first
        $pages = [];
        foreach (array_merge(glob($dir . "/*.md") ?: [], glob($dir . "/*.yml") ?: []) as $file) {
            [$path, $language] = $site->pageFileAddress(substr($file, strlen($site->getFsPath() . "/pages")));
            if (str_ends_with($path, "/")) {
                continue; // the index of the folder
            }
            $isVersion = str_ends_with($file, "." . $language . ".md");
            // a Markdown page wins over a structured page of the same name, as on the website
            if (!$isVersion && isset($pages[$path]["main"]) && str_ends_with($pages[$path]["main"], ".md")) {
                continue;
            }
            $pages[$path][$isVersion ? $language : "main"] = $file;
        }
        $entries = [];
        foreach ($pages as $path => $files) {
            if (!isset($files["main"])) {
                continue;
            }
            $values = self::values($files["main"]);
            if (!empty($values["draft"])) {
                continue;
            }
            $languageValues = [];
            foreach ($files as $language => $file) {
                if ($language !== "main") {
                    $languageValues[$language] = self::values($file);
                }
            }
            $entries[] = new PageEntry($site, $path, $values, $languageValues);
        }
        return (new PageList($entries))->sortBy("slug");
    }

    /**
     * The fields of a page file, the frontmatter of a Markdown page, the values
     * of a structured page without its blocks.
     */
    private static function values(string $file): array
    {
        $source = (string)file_get_contents($file);
        if (str_ends_with($file, ".md")) {
            return Page::splitFrontmatter(trim($source))[0];
        }
        try {
            $values = Page::datesAsText(Yaml::parse($source, Yaml::PARSE_DATETIME));
        } catch (ParseException $e) {
            Logger::error("Structured page " . $file . " is no valid YAML: " . $e->getMessage());
            return [];
        }
        if (!is_array($values)) {
            return [];
        }
        unset($values["blocks"]);
        return $values;
    }

    protected function withEntries(array $entries): static
    {
        return new PageList($entries);
    }
}
