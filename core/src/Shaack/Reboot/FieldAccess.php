<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

namespace Shaack\Reboot;

/**
 * Reads the fields of an entry, a form block or an item of a list in a template.
 * The class sets `$fieldValues` and `$fieldBlueprint` and names its site. An
 * entry holds the values of every language and shows those of one, see
 * fieldValues().
 *
 * ```php
 * <h2><?= $block->get("heading") ?></h2>
 * <?= $block->html("text") ?>
 * <?php foreach ($block->items("cards") as $card) { ?>
 *     <img src="<?= $card->get("image") ?>" alt="<?= $card->get("caption") ?>">
 * <?php } ?>
 * <?php if ($seminar = $block->entry("seminar")) { ?>
 *     <?= $seminar->get("title") ?>
 * <?php } ?>
 * ```
 */
trait FieldAccess
{
    private static ?\Parsedown $fieldParsedown = null;
    protected array $fieldValues = [];
    protected ?Blueprint $fieldBlueprint = null;

    /**
     * The site, an `entry` field finds its collection there.
     */
    abstract protected function fieldSite(): ?Site;

    /**
     * The values the accessors read, in one language.
     */
    protected function fieldValues(): array
    {
        return $this->fieldValues;
    }

    /**
     * A field, ready to be printed into HTML. Strings are escaped, other values
     * like numbers, booleans or lists come back unchanged.
     * @param bool $escaped false returns a string as written, for URLs, JSON or HTML on purpose
     * @return mixed
     */
    public function get(string $field, $default = null, bool $escaped = true)
    {
        $value = $this->fieldValues()[$field] ?? $default;
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
        $markdown = $this->fieldValues()[$field] ?? "";
        if (!is_string($markdown) || $markdown === "") {
            return "";
        }
        if (self::$fieldParsedown === null) {
            self::$fieldParsedown = new \Parsedown();
        }
        return self::$fieldParsedown->text($markdown);
    }

    /**
     * A date field in another format, `d.m.Y` for example, or "" if it is no date.
     */
    public function date(string $field, string $format): string
    {
        $value = $this->fieldValues()[$field] ?? null;
        $date = is_string($value) ? \DateTime::createFromFormat("!Y-m-d", $value) : false;
        return $date ? htmlspecialchars($date->format($format), ENT_QUOTES, 'UTF-8') : "";
    }

    /**
     * The items of a `list` field, each with the same accessors.
     * @return Item[]
     */
    public function items(string $field): array
    {
        $value = $this->fieldValues()[$field] ?? [];
        $blueprint = $this->fieldBlueprint?->getFields()[$field]["blueprint"] ?? null;
        $items = [];
        foreach (is_array($value) ? $value : [] as $item) {
            if (is_array($item)) {
                $items[] = new Item($item, $blueprint, $this->fieldSite());
            }
        }
        return $items;
    }

    /**
     * The entry an `entry` field names, from the collection of the field.
     * Null if the field is empty, or the entry is gone, a draft or not valid.
     */
    public function entry(string $field): ?Entry
    {
        $slug = $this->fieldValues()[$field] ?? null;
        $collection = $this->fieldBlueprint?->getFields()[$field]["collection"] ?? null;
        $site = $this->fieldSite();
        if (!is_string($slug) || !is_string($collection) || !$site) {
            return null;
        }
        return $site->collection($collection)->find($slug);
    }

    /**
     * All fields as read, not escaped, in the language the accessors show.
     */
    public function getValues(): array
    {
        return $this->fieldValues();
    }

    public function getBlueprint(): ?Blueprint
    {
        return $this->fieldBlueprint;
    }
}
