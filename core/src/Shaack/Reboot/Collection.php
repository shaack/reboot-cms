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
 * several ways on the same page, see EntryList.
 *
 * ```php
 * $next = $site->collection("seminars")
 *     ->where("date", ">=", "today")
 *     ->sortBy("date")
 *     ->limit(3);
 * foreach ($next as $seminar) { ... }
 * ```
 */
class Collection extends EntryList
{
    private ?Blueprint $blueprint;
    private string $name;

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

    protected function withEntries(array $entries): static
    {
        return new Collection($this->name, $this->blueprint, $entries);
    }
}
