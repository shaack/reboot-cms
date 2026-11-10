<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

namespace Shaack\Reboot;

use Shaack\Logger;
use Shaack\Utils\HttpUtils;

/**
 * The entries of `site/data/<name>/`, described by `site/blueprints/<name>.yml`.
 * Every query returns a new collection, so one collection can be queried in
 * several ways on the same page.
 *
 * ```php
 * $next = $site->collection("seminars")
 *     ->where("date", ">=", "today")
 *     ->sortBy("date")
 *     ->limit(3);
 * foreach ($next as $seminar) { ... }
 * ```
 */
class Collection implements \IteratorAggregate, \Countable
{
    private ?Blueprint $blueprint;
    private string $name;
    /** @var Entry[] */
    private array $entries;

    /**
     * @param Entry[] $entries
     */
    public function __construct(string $name, ?Blueprint $blueprint, array $entries = [])
    {
        $this->name = $name;
        $this->blueprint = $blueprint;
        $this->entries = array_values($entries);
    }

    /**
     * Read all entries of a collection, sorted as the blueprint says.
     * Without a blueprint the collection is empty and an error is logged.
     * @param bool $all true keeps drafts and entries with errors, for the admin;
     *     false leaves them out and logs the errors, for the website
     */
    public static function load(Site $site, string $name, bool $all = false): Collection
    {
        $name = HttpUtils::sanitizeFileName($name);
        $blueprint = Blueprint::load($site, $name);
        if (!$blueprint) {
            Logger::error("There is no blueprint for the collection '" . $name . "', add site/blueprints/" . $name . ".yml");
            return new Collection($name, null);
        }
        $entries = [];
        foreach (glob(self::folder($site, $name) . "/*.yml") ?: [] as $file) {
            $entry = Entry::fromFile($file, $blueprint);
            if (!$all) {
                if ($entry->isDraft()) {
                    continue;
                }
                if (!$entry->isValid()) {
                    Logger::error("Entry '" . $name . "/" . $entry->getSlug() . "' is left out: " . implode(", ", $entry->getErrors()));
                    continue;
                }
            }
            $entries[] = $entry;
        }
        $collection = new Collection($name, $blueprint, $entries);
        if ($blueprint->getSortField()) {
            return $collection->sortBy($blueprint->getSortField(), $blueprint->getSortOrder());
        }
        return $collection->sortBy("slug");
    }

    public static function folder(Site $site, string $name): string
    {
        return $site->getFsPath() . "/data/" . HttpUtils::sanitizeFileName($name);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getBlueprint(): ?Blueprint
    {
        return $this->blueprint;
    }

    /**
     * The entries whose field compares to the value. Operators: `=`, `!=`, `<`,
     * `<=`, `>`, `>=`, `contains` (text or list) and `in` (value is a list).
     * Dates compare as text, `"today"` stands for the current date.
     */
    public function where(string $field, string $operator, $value): Collection
    {
        if ($value === "today") {
            $value = date("Y-m-d");
        }
        return $this->filter(function (Entry $entry) use ($field, $operator, $value) {
            $actual = self::value($entry, $field);
            switch ($operator) {
                case "=":
                case "==":
                    return $actual == $value;
                case "!=":
                    return $actual != $value;
                case "<":
                    return $actual !== null && $actual < $value;
                case "<=":
                    return $actual !== null && $actual <= $value;
                case ">":
                    return $actual !== null && $actual > $value;
                case ">=":
                    return $actual !== null && $actual >= $value;
                case "contains":
                    if (is_array($actual)) {
                        return in_array($value, $actual);
                    }
                    return is_string($actual) && mb_stripos($actual, (string)$value) !== false;
                case "in":
                    return in_array($actual, (array)$value);
            }
            throw new \InvalidArgumentException("Unknown operator '" . $operator . "'");
        });
    }

    /**
     * The entries for which the callback returns true.
     * @param callable $callback fn(Entry $entry): bool
     */
    public function filter(callable $callback): Collection
    {
        return new Collection($this->name, $this->blueprint, array_filter($this->entries, $callback));
    }

    /**
     * Sort by a field, `"slug"` sorts by the file name. Entries without the field go last.
     */
    public function sortBy(string $field, string $order = "asc"): Collection
    {
        $entries = $this->entries;
        $direction = strtolower($order) === "desc" ? -1 : 1;
        usort($entries, function (Entry $a, Entry $b) use ($field, $direction) {
            $valueA = self::value($a, $field);
            $valueB = self::value($b, $field);
            if ($valueA === null || $valueB === null) {
                $result = ($valueA === null) <=> ($valueB === null);
            } else {
                $result = (is_string($valueA) && is_string($valueB) ? strnatcasecmp($valueA, $valueB) : $valueA <=> $valueB) * $direction;
            }
            // the slug keeps the order of equal values stable
            return $result ?: strcmp($a->getSlug(), $b->getSlug());
        });
        return new Collection($this->name, $this->blueprint, $entries);
    }

    public function limit(int $count, int $offset = 0): Collection
    {
        return new Collection($this->name, $this->blueprint, array_slice($this->entries, $offset, $count));
    }

    public function first(): ?Entry
    {
        return $this->entries[0] ?? null;
    }

    /**
     * The entry with this file name, without `.yml`.
     */
    public function find(string $slug): ?Entry
    {
        foreach ($this->entries as $entry) {
            if ($entry->getSlug() === $slug) {
                return $entry;
            }
        }
        return null;
    }

    /**
     * @return Entry[]
     */
    public function toArray(): array
    {
        return $this->entries;
    }

    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->entries);
    }

    public function count(): int
    {
        return count($this->entries);
    }

    private static function value(Entry $entry, string $field)
    {
        // the default language, so the entries sort and filter the same in every language
        return $field === "slug" ? $entry->getSlug() : $entry->getDefaultValues()[$field] ?? null;
    }
}
