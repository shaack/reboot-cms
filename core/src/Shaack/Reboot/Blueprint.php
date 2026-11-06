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
 * Describes the fields of the entries of a collection, from
 * `site/blueprints/<name>.yml`:
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
 * A field named `body` is the Markdown below the frontmatter of an entry, all
 * other fields live in the frontmatter.
 */
class Blueprint
{
    const TYPES = ["text", "textarea", "markdown", "number", "date", "boolean", "select", "image", "url"];

    private string $name;
    private string $title;
    private array $fields = [];
    private ?string $sortField = null;
    private string $sortOrder = "asc";
    private array $columns = [];

    public function __construct(string $name, array $definition)
    {
        $this->name = $name;
        $this->title = (string)($definition["title"] ?? ucfirst($name));
        foreach ($definition["fields"] ?? [] as $fieldName => $props) {
            $props = is_array($props) ? $props : [];
            $type = $props["type"] ?? "text";
            if (!in_array($type, self::TYPES, true)) {
                Logger::error("Blueprint '" . $name . "': field '" . $fieldName . "' has the unknown type '" . $type . "', it is treated as text");
                $type = "text";
            }
            $props["type"] = $type;
            $props["label"] = (string)($props["label"] ?? ucfirst((string)$fieldName));
            $this->fields[(string)$fieldName] = $props;
        }
        // the columns of the table in the admin, by default the first four short fields
        $columns = array_values(array_filter((array)($definition["columns"] ?? []), fn($column) => isset($this->fields[$column])));
        if (!$columns) {
            $columns = array_slice(array_keys(array_filter($this->fields,
                fn($props) => !in_array($props["type"], ["textarea", "markdown"], true))), 0, 4);
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
        $file = self::folder($site) . "/" . HttpUtils::sanitizeFileName($name) . ".yml";
        if (!is_file($file)) {
            return null;
        }
        try {
            $definition = Yaml::parseFile($file);
        } catch (ParseException $e) {
            Logger::error("Blueprint '" . $name . "' is no valid YAML: " . $e->getMessage());
            return null;
        }
        return new Blueprint($name, is_array($definition) ? $definition : []);
    }

    /**
     * All blueprints of the site, keyed and sorted by name.
     * @return Blueprint[]
     */
    public static function all(Site $site): array
    {
        $blueprints = [];
        foreach (glob(self::folder($site) . "/*.yml") ?: [] as $file) {
            $name = basename($file, ".yml");
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

    public function getName(): string
    {
        return $this->name;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @return array name => props, every props has `type` and `label`
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
     * Bring the values read from a file into the form the field types promise.
     * YAML reads an unquoted date as a timestamp, a date field gets it back as `Y-m-d`.
     * Missing values take the `default` of their field.
     */
    public function normalize(array $values): array
    {
        foreach ($this->fields as $name => $props) {
            if ((!array_key_exists($name, $values) || $values[$name] === null) && array_key_exists("default", $props)) {
                $values[$name] = $props["default"];
            }
            if ($props["type"] === "date" && is_int($values[$name] ?? null)) {
                $values[$name] = gmdate("Y-m-d", $values[$name]);
            }
        }
        return $values;
    }

    /**
     * Check normalized values against the fields.
     * @return array field name => message, empty if the values are valid
     */
    public function validate(array $values): array
    {
        $errors = [];
        foreach ($this->fields as $name => $props) {
            $value = $values[$name] ?? null;
            $label = $props["label"];
            if ($value === null || $value === "" || $value === []) {
                if (!empty($props["required"])) {
                    $errors[$name] = $label . " is required";
                }
                continue;
            }
            switch ($props["type"]) {
                case "number":
                    if (!is_int($value) && !is_float($value)) {
                        $errors[$name] = $label . " must be a number";
                    } else if (isset($props["min"]) && $value < $props["min"]) {
                        $errors[$name] = $label . " must be at least " . $props["min"];
                    } else if (isset($props["max"]) && $value > $props["max"]) {
                        $errors[$name] = $label . " must be at most " . $props["max"];
                    }
                    break;
                case "date":
                    $date = is_string($value) ? \DateTime::createFromFormat("!Y-m-d", $value) : false;
                    if (!$date || $date->format("Y-m-d") !== $value) {
                        $errors[$name] = $label . " must be a date like 2027-03-12";
                    }
                    break;
                case "boolean":
                    if (!is_bool($value)) {
                        $errors[$name] = $label . " must be true or false";
                    }
                    break;
                case "select":
                    $options = array_map("strval", (array)($props["options"] ?? []));
                    if (!is_scalar($value) || !in_array((string)$value, $options, true)) {
                        $errors[$name] = $label . " must be one of: " . implode(", ", $options);
                    }
                    break;
                default:
                    if (!is_scalar($value)) {
                        $errors[$name] = $label . " must be text";
                    }
            }
        }
        return $errors;
    }
}
