<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

namespace Shaack\Reboot;

/**
 * A page in a list of the pages of a folder, see Site::pages(). Its fields are
 * the frontmatter of a Markdown page or the values of a structured page next
 * to its blocks, read with the accessors of an entry:
 *
 * ```php
 * <a href="<?= $post->getUrl() ?>"><?= $post->get("title") ?></a>
 * <time><?= $post->date("date", "F j, Y") ?></time>
 * ```
 *
 * On a multilingual site the fields are those of the language of the page
 * being rendered. The Markdown page of a language, `post.de.md`, has the
 * fields of its own frontmatter, the others come from the page of the default
 * language, so a date is written once.
 */
class PageEntry
{
    use FieldAccess;

    private string $slug;
    private string $path;
    private Site $site;
    /** @var array language => values */
    private array $languageValues;
    private array $localized = []; // the values by language, see valuesIn()

    /**
     * @param string $path the address without language prefix, `/blog/first-post`
     * @param array $values the fields of the page of the default language
     * @param array $languageValues language => the fields of the Markdown page of that language
     */
    public function __construct(Site $site, string $path, array $values, array $languageValues = [])
    {
        $this->site = $site;
        $this->path = $path;
        $this->slug = basename($path);
        $this->fieldValues = $values;
        $this->languageValues = $languageValues;
    }

    protected function fieldSite(): ?Site
    {
        return $this->site;
    }

    protected function fieldValues(): array
    {
        return $this->valuesIn($this->site->getLanguage());
    }

    /**
     * The fields in a language, those of its own page over those of the
     * default language.
     */
    public function valuesIn(string $language): array
    {
        return $this->localized[$language] ??= array_merge(
            $this->site->localize($this->fieldValues, $language),
            $this->languageValues[$language] ?? []
        );
    }

    /**
     * The fields in the default language, for queries.
     */
    public function getDefaultValues(): array
    {
        return $this->valuesIn($this->site->getDefaultLanguage());
    }

    /**
     * The file name without language and extension.
     */
    public function getSlug(): string
    {
        return $this->slug;
    }

    /**
     * The address of the page in the language of the page being rendered,
     * relative like the links of a page, `blog/first-post` or `de/blog/first-post`.
     */
    public function getUrl(): string
    {
        return ltrim($this->site->languagePath($this->path, $this->site->getLanguage()), "/");
    }

    /**
     * Whether the page has a Markdown page of its own in a language.
     */
    public function hasLanguage(string $language): bool
    {
        return $language === $this->site->getDefaultLanguage() || isset($this->languageValues[$language]);
    }
}
