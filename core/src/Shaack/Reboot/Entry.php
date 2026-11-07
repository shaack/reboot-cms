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

    /**
     * @param array $values the normalized fields, `body` included
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
        [$frontmatter, $body] = Page::splitFrontmatter(trim((string)file_get_contents($fsPath)));
        $body = trim($body);
        $values = $frontmatter;
        if ($body !== "" || !array_key_exists("body", $values)) {
            $values["body"] = $body;
        }
        $values = $blueprint->normalize($values);
        return new Entry(basename($fsPath, ".md"), $fsPath, $values, $blueprint->validate($values), $blueprint, $site ?? $blueprint->getSite());
    }

    protected function fieldSite(): ?Site
    {
        return $this->site;
    }

    /**
     * The file name without `.md`, unique in its collection.
     */
    public function getSlug(): string
    {
        return $this->slug;
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
        return !empty($this->fieldValues["draft"]);
    }
}
