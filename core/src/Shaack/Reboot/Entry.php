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
 *
 * ```php
 * <h3><?= $seminar->get("title") ?></h3>
 * <time><?= $seminar->date("date", "d.m.Y") ?></time>
 * <?= $seminar->html("body") ?>
 * ```
 */
class Entry
{
    private static ?\Parsedown $parsedown = null;

    private string $slug;
    private string $fsPath;
    private array $values;
    private array $errors;

    /**
     * @param array $values the normalized fields, `body` included
     * @param array $errors the validation errors of the values, field name => message
     */
    public function __construct(string $slug, string $fsPath, array $values, array $errors = [])
    {
        $this->slug = $slug;
        $this->fsPath = $fsPath;
        $this->values = $values;
        $this->errors = $errors;
    }

    /**
     * Read an entry from its file and check it against the blueprint.
     */
    public static function fromFile(string $fsPath, Blueprint $blueprint): Entry
    {
        [$frontmatter, $body] = Page::splitFrontmatter(trim((string)file_get_contents($fsPath)));
        $body = trim($body);
        $values = $frontmatter;
        if ($body !== "" || !array_key_exists("body", $values)) {
            $values["body"] = $body;
        }
        $values = $blueprint->normalize($values);
        return new Entry(basename($fsPath, ".md"), $fsPath, $values, $blueprint->validate($values));
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

    /**
     * A field, ready to be printed into HTML. Strings are escaped, other values
     * like numbers, booleans or lists come back unchanged.
     * @param bool $escaped false returns a string as written, for URLs, JSON or HTML on purpose
     * @return mixed
     */
    public function get(string $field, $default = null, bool $escaped = true)
    {
        $value = $this->values[$field] ?? $default;
        if ($escaped && is_string($value)) {
            return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        }
        return $value;
    }

    /**
     * A Markdown field rendered to HTML, `body` for example.
     */
    public function html(string $field): string
    {
        $markdown = $this->values[$field] ?? "";
        if (!is_string($markdown) || $markdown === "") {
            return "";
        }
        if (self::$parsedown === null) {
            self::$parsedown = new \Parsedown();
        }
        return self::$parsedown->text($markdown);
    }

    /**
     * A date field in another format, `d.m.Y` for example, or "" if it is no date.
     */
    public function date(string $field, string $format): string
    {
        $value = $this->values[$field] ?? null;
        $date = is_string($value) ? \DateTime::createFromFormat("!Y-m-d", $value) : false;
        return $date ? htmlspecialchars($date->format($format), ENT_QUOTES, 'UTF-8') : "";
    }

    /**
     * All fields as read, not escaped.
     */
    public function getValues(): array
    {
        return $this->values;
    }

    public function isValid(): bool
    {
        return !$this->errors;
    }

    /**
     * @return array field name => message
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
        return !empty($this->values["draft"]);
    }
}
