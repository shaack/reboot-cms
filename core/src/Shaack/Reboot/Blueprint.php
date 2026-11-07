<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

namespace Shaack\Reboot;

use Shaack\Logger;
use Shaack\Utils\HttpUtils;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Describes fields: those of the entries of a collection in
 * `site/blueprints/<name>.yml`, those of a block in `site/blocks/<name>/<name>.yml`
 * and those of a structured page.
 *
 * ```yaml
 * title: Seminars
 * sort: date
 * fields:
 *   title: {type: text, required: true}
 *   date: {type: date, required: true}
 *   price: {type: number, label: "Price (€)", min: 0}
 *   level: {type: select, options: [Beginner, Advanced]}
 *   body: {type: markdown, label: Description}
 * columns: [date, title, price]
 * ```
 *
 * A block blueprint with `markdown: true` marks a block type that also works in
 * Markdown pages.
 *
 * Three types hold more than one value: `list` repeats its own `fields`,
 * `blocks` is a list of blocks, each with the fields of its block type, and
 * `entry` names an entry of the collection in `collection`.
 *
 * In a collection, a field named `body` is the Markdown below the frontmatter
 * of an entry, all other fields live in the frontmatter.
 */
class Blueprint
{
    const TYPES = ["text", "textarea", "markdown", "number", "date", "boolean", "select", "image", "url", "list", "entry", "blocks"];

    private string $name;
    private string $title;
    private ?Site $site;
    private array $fields = [];
    private ?string $sortField = null;
    private string $sortOrder = "asc";
    private array $columns = [];
    private bool $markdown;

    /**
     * @param ?Site $site needed by `blocks` fields, which load the blueprints of the block types
     */
    public function __construct(string $name, array $definition, ?Site $site = null)
    {
        $this->name = $name;
        $this->site = $site;
        $this->title = (string)($definition["title"] ?? ucfirst($name));
        $this->markdown = !empty($definition["markdown"]);
        foreach ($definition["fields"] ?? [] as $fieldName => $props) {
            $fieldName = (string)$fieldName;
            $props = is_array($props) ? $props : [];
            $type = $props["type"] ?? "text";
            if (!in_array($type, self::TYPES, true)) {
                Logger::error("Blueprint '" . $name . "': field '" . $fieldName . "' has the unknown type '" . $type . "', it is treated as text");
                $type = "text";
            }
            $props["type"] = $type;
            $props["label"] = (string)($props["label"] ?? ucfirst(str_replace("-", " ", $fieldName)));
            if ($type === "list") {
                $props["blueprint"] = new Blueprint($name . "." . $fieldName, ["fields" => $props["fields"] ?? []], $site);
            }
            $this->fields[$fieldName] = $props;
        }
        // the columns of the table in the admin, by default the first four short fields
        $columns = array_values(array_filter((array)($definition["columns"] ?? []), fn($column) => isset($this->fields[$column])));
        if (!$columns) {
            $columns = array_slice(array_keys(array_filter($this->fields,
                fn($props) => !in_array($props["type"], ["textarea", "markdown", "list", "blocks"], true))), 0, 4);
        }
        $this->columns = $columns;
        // "date" or "date desc"
        if (!empty($definition["sort"])) {
            $sort = preg_split('/\s+/', trim((string)$definition["sort"]));
            $this->sortField = $sort[0];
            $this->sortOrder = strtolower($sort[1] ?? "") === "desc" ? "desc" : "asc";
        }
    }

    /**
     * The blueprint `site/blueprints/<name>.yml`, or null if there is none or it is not valid YAML.
     */
    public static function load(Site $site, string $name): ?Blueprint
    {
        return self::loadFile($site, $name, self::folder($site) . "/" . HttpUtils::sanitizeFileName($name) . ".yml");
    }

    /**
     * The blueprint of a block type, `site/blocks/<name>/<name>.yml` in the folder of
     * its template. A block type with one is a form block, offered in structured pages.
     */
    public static function forBlock(Site $site, string $name): ?Blueprint
    {
        $name = HttpUtils::sanitizeFileName($name);
        return self::loadFile($site, $name, $site->getFsPath() . "/blocks/" . $name . "/" . $name . ".yml");
    }

    /**
     * The blueprint of the structured pages, `site/blueprints/page.yml`, or by
     * default a title, a description and the blocks.
     */
    public static function forPage(Site $site): Blueprint
    {
        return self::load($site, "page") ?? new Blueprint("page", ["title" => "Page", "fields" => [
            "title" => ["type" => "text", "required" => true, "help" => "The title of the page in the browser tab and in search results"],
            "description" => ["type" => "textarea", "help" => "A sentence for search engines"],
            "blocks" => ["type" => "blocks"],
        ]], $site);
    }

    /**
     * The block types with a blueprint and a template, keyed and sorted by name.
     * @return Blueprint[]
     */
    public static function blockTypes(Site $site): array
    {
        $types = [];
        foreach (glob($site->getFsPath() . "/blocks/*/*.yml") ?: [] as $file) {
            $name = basename($file, ".yml");
            if (basename(dirname($file)) !== $name || !is_file(dirname($file) . "/" . $name . ".php")) {
                continue;
            }
            $blueprint = self::forBlock($site, $name);
            if ($blueprint) {
                $types[$name] = $blueprint;
            }
        }
        ksort($types);
        return $types;
    }

    /**
     * All blueprints of collections, keyed and sorted by name. `page.yml` describes
     * the structured pages and is no collection.
     * @return Blueprint[]
     */
    public static function all(Site $site): array
    {
        $blueprints = [];
        foreach (glob(self::folder($site) . "/*.yml") ?: [] as $file) {
            $name = basename($file, ".yml");
            if ($name === "page") {
                continue;
            }
            $blueprint = self::load($site, $name);
            if ($blueprint) {
                $blueprints[$name] = $blueprint;
            }
        }
        ksort($blueprints);
        return $blueprints;
    }

    public static function folder(Site $site): string
    {
        return $site->getFsPath() . "/blueprints";
    }

    private static function loadFile(Site $site, string $name, string $file): ?Blueprint
    {
        if (!is_file($file)) {
            return null;
        }
        try {
            $definition = Yaml::parseFile($file);
        } catch (ParseException $e) {
            Logger::error("Blueprint '" . $name . "' is no valid YAML: " . $e->getMessage());
            return null;
        }
        return new Blueprint($name, is_array($definition) ? $definition : [], $site);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * Whether a block type with this blueprint also works in Markdown pages,
     * `markdown: true`. A block type with a blueprint is otherwise for
     * structured pages only, the Markdown editor does not offer it.
     */
    public function isForMarkdown(): bool
    {
        return $this->markdown;
    }

    public function getSite(): ?Site
    {
        return $this->site;
    }

    /**
     * @return array name => props, every props has `type` and `label`, a `list` also its `blueprint`
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    /**
     * The fields the admin shows as columns of its table, `columns: [date, title]` in the blueprint.
     * @return string[]
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    public function getSortField(): ?string
    {
        return $this->sortField;
    }

    public function getSortOrder(): string
    {
        return $this->sortOrder;
    }

    /**
     * The blueprint of a block type for a `blocks` field, null if the type has none.
     */
    public function blockBlueprint(string $type): ?Blueprint
    {
        return $this->site ? self::forBlock($this->site, $type) : null;
    }

    /**
     * Bring the values read from a file into the form the field types promise.
     * YAML reads an unquoted date as a timestamp, a date field gets it back as `Y-m-d`.
     * Missing values take the `default` of their field. Lists and blocks are
     * normalized item by item.
     */
    public function normalize(array $values): array
    {
        foreach ($this->fields as $name => $props) {
            if ((!array_key_exists($name, $values) || $values[$name] === null) && array_key_exists("default", $props)) {
                $values[$name] = $props["default"];
            }
            $value = $values[$name] ?? null;
            if ($props["type"] === "date" && is_int($value)) {
                $values[$name] = gmdate("Y-m-d", $value);
            } else if ($props["type"] === "list" && is_array($value)) {
                $values[$name] = array_map(fn($item) => is_array($item) ? $props["blueprint"]->normalize($item) : $item, array_values($value));
            } else if ($props["type"] === "blocks" && is_array($value)) {
                $values[$name] = array_map(function ($block) {
                    $blueprint = is_array($block) && is_string($block["type"] ?? null) ? $this->blockBlueprint($block["type"]) : null;
                    return $blueprint ? $blueprint->normalize($block) : $block;
                }, array_values($value));
            }
        }
        return $values;
    }

    /**
     * Check normalized values against the fields.
     * @param string $path the place of these values in an outer value, like `cards.2.`, for the keys
     * @return array field path => message, empty if the values are valid. A value in a list or a
     *     block has the path `field.index.subfield`.
     */
    public function validate(array $values, string $path = ""): array
    {
        $errors = [];
        foreach ($this->fields as $name => $props) {
            $value = $values[$name] ?? null;
            $label = $props["label"];
            $key = $path . $name;
            if ($value === null || $value === "" || $value === []) {
                if (!empty($props["required"])) {
                    $errors[$key] = $label . " is required";
                }
                continue;
            }
            switch ($props["type"]) {
                case "number":
                    if (!is_int($value) && !is_float($value)) {
                        $errors[$key] = $label . " must be a number";
                    } else if (isset($props["min"]) && $value < $props["min"]) {
                        $errors[$key] = $label . " must be at least " . $props["min"];
                    } else if (isset($props["max"]) && $value > $props["max"]) {
                        $errors[$key] = $label . " must be at most " . $props["max"];
                    }
                    break;
                case "date":
                    $date = is_string($value) ? \DateTime::createFromFormat("!Y-m-d", $value) : false;
                    if (!$date || $date->format("Y-m-d") !== $value) {
                        $errors[$key] = $label . " must be a date like 2027-03-12";
                    }
                    break;
                case "boolean":
                    if (!is_bool($value)) {
                        $errors[$key] = $label . " must be true or false";
                    }
                    break;
                case "select":
                    $options = array_map("strval", (array)($props["options"] ?? []));
                    if (!is_scalar($value) || !in_array((string)$value, $options, true)) {
                        $errors[$key] = $label . " must be one of: " . implode(", ", $options);
                    }
                    break;
                case "list":
                    if (!is_array($value) || !array_is_list($value)) {
                        $errors[$key] = $label . " must be a list";
                        break;
                    }
                    if (isset($props["min"]) && count($value) < $props["min"]) {
                        $errors[$key] = $label . " needs at least " . $props["min"] . " items";
                    } else if (isset($props["max"]) && count($value) > $props["max"]) {
                        $errors[$key] = $label . " takes at most " . $props["max"] . " items";
                    }
                    foreach ($value as $i => $item) {
                        if (!is_array($item)) {
                            $errors[$key . "." . $i] = $label . " " . ($i + 1) . " has no fields";
                            continue;
                        }
                        foreach ($props["blueprint"]->validate($item, $key . "." . $i . ".") as $itemKey => $message) {
                            $errors[$itemKey] = $label . " " . ($i + 1) . ": " . $message;
                        }
                    }
                    break;
                case "blocks":
                    if (!is_array($value) || !array_is_list($value)) {
                        $errors[$key] = $label . " must be a list of blocks";
                        break;
                    }
                    foreach ($value as $i => $block) {
                        $type = is_array($block) ? ($block["type"] ?? null) : null;
                        if (!is_string($type) || $type === "") {
                            $errors[$key . "." . $i] = "Block " . ($i + 1) . " has no type";
                            continue;
                        }
                        if (!empty($block["disabled"])) {
                            continue;
                        }
                        $blueprint = $this->blockBlueprint($type);
                        if (!$blueprint) {
                            $errors[$key . "." . $i] = "Block " . ($i + 1) . ": there is no block type '" . $type . "' with a blueprint, site/blocks/" . $type . "/" . $type . ".yml";
                            continue;
                        }
                        foreach ($blueprint->validate($block, $key . "." . $i . ".") as $blockKey => $message) {
                            $errors[$blockKey] = $blueprint->getTitle() . ": " . $message;
                        }
                    }
                    break;
                case "entry":
                    if (!is_string($value)) {
                        $errors[$key] = $label . " must name an entry";
                    }
                    break;
                default:
                    if (!is_scalar($value)) {
                        $errors[$key] = $label . " must be text";
                    }
            }
        }
        return $errors;
    }
}
