<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

namespace Shaack\Reboot;

/**
 * The queries of a list of entries, the entries of a collection (Collection,
 * Entry) or the pages of a folder (PageList, PageEntry). Every query returns a
 * new list. An entry has `getSlug()` and `getDefaultValues()`, the queries read
 * the default language, so a list sorts and filters the same in every language.
 */
abstract class EntryList implements \IteratorAggregate, \Countable
{
    /** @var Entry[]|PageEntry[] */
    protected array $entries = [];

    /**
     * A list of the same kind with these entries.
     */
    abstract protected function withEntries(array $entries): static;

    /**
     * The entries whose field compares to the value. Operators: `=`, `!=`, `<`,
     * `<=`, `>`, `>=`, `contains` (text or list) and `in` (value is a list).
     * Dates compare as text, `"today"` stands for the current date.
     */
    public function where(string $field, string $operator, $value): static
    {
        if ($value === "today") {
            $value = date("Y-m-d");
        }
        return $this->filter(function ($entry) use ($field, $operator, $value) {
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
     * @param callable $callback fn($entry): bool
     */
    public function filter(callable $callback): static
    {
        return $this->withEntries(array_filter($this->entries, $callback));
    }

    /**
     * Sort by a field, `"slug"` sorts by the file name. Entries without the field go last.
     */
    public function sortBy(string $field, string $order = "asc"): static
    {
        $entries = $this->entries;
        $direction = strtolower($order) === "desc" ? -1 : 1;
        usort($entries, function ($a, $b) use ($field, $direction) {
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
        return $this->withEntries($entries);
    }

    public function limit(int $count, int $offset = 0): static
    {
        return $this->withEntries(array_slice($this->entries, $offset, $count));
    }

    public function first()
    {
        return $this->entries[0] ?? null;
    }

    /**
     * The entry with this name, the file name without its extension.
     */
    public function find(string $slug)
    {
        foreach ($this->entries as $entry) {
            if ($entry->getSlug() === $slug) {
                return $entry;
            }
        }
        return null;
    }

    /**
     * @return Entry[]|PageEntry[]
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

    private static function value($entry, string $field)
    {
        // the default language, so the entries sort and filter the same in every language
        return $field === "slug" ? $entry->getSlug() : $entry->getDefaultValues()[$field] ?? null;
    }
}
