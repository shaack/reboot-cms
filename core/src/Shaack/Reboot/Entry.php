<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

namespace Shaack\Reboot;

/**
 * One entry of a collection, a Markdown file in `site/data/<collection>/`.
 * The fields are its frontmatter, the Markdown below it is the field `body`.
 * See FieldAccess for the accessors.
 *
 * On a multilingual site the text fields hold a value per language as in a
 * structured page, `title: {en: Basics, de: Grundlagen}`. The Markdown below
 * the frontmatter is the body in the default language, the frontmatter may
 * hold it in the other languages, `body: {de: "..."}`. The accessors show the
 * language of the page being rendered, see Site::getLanguage(), a language
 * without a value shows the default one. Queries and the validation read the
 * default language, so an entry sorts and filters the same in every language.
 *
 * ```php
 * <h3><?= $seminar->get("title") ?></h3>
 * <time><?= $seminar->date("date", "d.m.Y") ?></time>
 * <?= $seminar->html("body") ?>
 * ```
 */
class Entry
{
    use FieldAccess;

    private string $slug;
    private string $fsPath;
    private array $errors;
    private ?Site $site;
    private array $localized = []; // the values by language, see fieldValues()

    /**
     * @param array $values the normalized fields, `body` included, with a value per language in the translated ones
     * @param array $errors the validation errors of the values, field path => message
     */
    public function __construct(string $slug, string $fsPath, array $values, array $errors = [], ?Blueprint $blueprint = null, ?Site $site = null)
    {
        $this->slug = $slug;
        $this->fsPath = $fsPath;
        $this->fieldValues = $values;
        $this->errors = $errors;
        $this->fieldBlueprint = $blueprint;
        $this->site = $site;
    }

    /**
     * Read an entry from its file and check it against the blueprint.
     */
    public static function fromFile(string $fsPath, Blueprint $blueprint, ?Site $site = null): Entry
    {
        $site = $site ?? $blueprint->getSite();
        [$frontmatter, $body] = Page::splitFrontmatter(trim((string)file_get_contents($fsPath)));
        $values = self::withBody($frontmatter, trim($body), $site?->getDefaultLanguage());
        $values = $blueprint->normalize($values);
        $defaultValues = $site ? $site->localize($values, $site->getDefaultLanguage()) : $values;
        return new Entry(basename($fsPath, ".md"), $fsPath, $values, $blueprint->validate($defaultValues), $blueprint, $site);
    }

    /**
     * The frontmatter with the Markdown below it as `body`. The Markdown is the
     * default language when the frontmatter holds the body in other languages.
     */
    public static function withBody(array $frontmatter, string $body, ?string $defaultLanguage): array
    {
        $values = $frontmatter;
        if (is_array($values["body"] ?? null) && $defaultLanguage !== null) {
            if ($body !== "") {
                $values["body"] = [$defaultLanguage => $body] + $values["body"];
            }
        } else if ($body !== "" || !array_key_exists("body", $values)) {
            $values["body"] = $body;
        }
        return $values;
    }

    protected function fieldSite(): ?Site
    {
        return $this->site;
    }

    /**
     * The values in the language of the page being rendered.
     */
    protected function fieldValues(): array
    {
        return $this->site ? $this->valuesIn($this->site->getLanguage()) : $this->fieldValues;
    }

    /**
     * The values in a language, a field without a value in it has the one of
     * the default language.
     */
    public function valuesIn(string $language): array
    {
        return $this->localized[$language] ??= $this->site ? $this->site->localize($this->fieldValues, $language) : $this->fieldValues;
    }

    /**
     * The values in the default language, for queries.
     */
    public function getDefaultValues(): array
    {
        return $this->site ? $this->valuesIn($this->site->getDefaultLanguage()) : $this->fieldValues;
    }

    /**
     * The file name without `.md`, unique in its collection.
     */
    public function getSlug(): string
    {
        return $this->slug;
    }

    /**
     * The address of the entry as a page, `blog/<slug>`, if its blueprint has
     * `url: blog`, otherwise null. Relative, like the links of a page.
     */
    public function getUrl(): ?string
    {
        $url = $this->fieldBlueprint?->getUrl();
        return $url !== null ? $url . "/" . $this->slug : null;
    }

    public function getFsPath(): string
    {
        return $this->fsPath;
    }

    public function isValid(): bool
    {
        return !$this->errors;
    }

    /**
     * @return array field path => message
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * An entry with `draft: true` is kept off the website.
     */
    public function isDraft(): bool
    {
        return !empty($this->fieldValues()["draft"]);
    }
}
