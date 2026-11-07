<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

namespace Shaack\Reboot\AddOns\Admin;

use Shaack\Reboot\Blueprint;
use Shaack\Reboot\YamlWriter;

/**
 * Turns the posted fields of a FieldForm into values and the values into what
 * a file keeps. Used for data entries and structured pages alike.
 */
class FieldInput
{
    /**
     * The strings of the form in the types of the fields. A value that does not
     * fit stays as it is, so the validation names the problem. An empty field is null.
     *
     * A block of a `blocks` field posts its type as `_type`, `_disabled` when it is
     * switched off and `_extra`, the JSON of values its blueprint does not know.
     */
    public static function fromInput(Blueprint $blueprint, array $input): array
    {
        $values = [];
        foreach ($blueprint->getFields() as $name => $props) {
            $value = $input[$name] ?? null;
            switch ($props["type"]) {
                case "boolean":
                    $values[$name] = (string)$value === "1";
                    break;
                case "list":
                    $values[$name] = [];
                    foreach (is_array($value) ? $value : [] as $row) {
                        if (is_array($row)) {
                            $values[$name][] = self::fromInput($props["blueprint"], $row);
                        }
                    }
                    break;
                case "blocks":
                    $values[$name] = [];
                    foreach (is_array($value) ? $value : [] as $row) {
                        $block = is_array($row) ? self::blockFromInput($blueprint, $row) : null;
                        if ($block !== null) {
                            $values[$name][] = $block;
                        }
                    }
                    break;
                default:
                    $values[$name] = self::scalar($props["type"], $value);
            }
        }
        return $values;
    }

    private static function blockFromInput(Blueprint $blueprint, array $row): ?array
    {
        $type = $row["_type"] ?? null;
        if (!is_string($type) || !preg_match('/^[\w\-]+$/', $type)) {
            return null;
        }
        $block = ["type" => $type];
        if ((string)($row["_disabled"] ?? "") === "1") {
            $block["disabled"] = true;
        }
        $blockBlueprint = $blueprint->blockBlueprint($type);
        if ($blockBlueprint) {
            $block += self::fromInput($blockBlueprint, $row);
        }
        $extra = json_decode((string)($row["_extra"] ?? ""), true);
        foreach (is_array($extra) ? $extra : [] as $key => $value) {
            if (!array_key_exists($key, $block) && !in_array($key, ["type", "disabled"], true)) {
                $block[$key] = $value;
            }
        }
        return $block;
    }

    private static function scalar(string $type, $value)
    {
        if (!is_string($value)) {
            return null;
        }
        $value = str_replace("\r\n", "\n", $value);
        $value = in_array($type, ["textarea", "markdown"], true) ? rtrim($value) : trim($value);
        if ($value === "") {
            return null;
        }
        if ($type === "number" && is_numeric($value)) {
            return str_contains($value, ".") || stripos($value, "e") !== false ? (float)$value : (int)$value;
        }
        return $value;
    }

    /**
     * The values of a file for the form: as written, without defaults, only a date
     * YAML read as a timestamp is turned back into `Y-m-d`, also in lists and blocks.
     */
    public static function forForm(Blueprint $blueprint, array $values): array
    {
        foreach ($blueprint->getFields() as $name => $props) {
            $value = $values[$name] ?? null;
            if ($props["type"] === "date" && is_int($value)) {
                $values[$name] = gmdate("Y-m-d", $value);
            } else if ($props["type"] === "list" && is_array($value)) {
                $values[$name] = array_map(fn($item) => is_array($item) ? self::forForm($props["blueprint"], $item) : $item, $value);
            } else if ($props["type"] === "blocks" && is_array($value)) {
                $values[$name] = array_map(function ($block) use ($blueprint) {
                    $blockBlueprint = is_array($block) && is_string($block["type"] ?? null) ? $blueprint->blockBlueprint($block["type"]) : null;
                    return $blockBlueprint ? self::forForm($blockBlueprint, $block) : $block;
                }, $value);
            }
        }
        return $values;
    }

    /**
     * The values as a file keeps them: in the order of the blueprint, values it
     * does not know at the end, without empty fields, so their default applies,
     * without a switch that is at its default, without empty list items. A date
     * is written without quotes.
     */
    public static function clean(Blueprint $blueprint, array $values): array
    {
        $clean = [];
        $fields = $blueprint->getFields();
        foreach ($fields as $name => $props) {
            $value = $values[$name] ?? null;
            if ($value === null || $value === "" || $value === []) {
                continue;
            }
            switch ($props["type"]) {
                case "boolean":
                    if ($value === (bool)($props["default"] ?? false)) {
                        continue 2;
                    }
                    break;
                case "date":
                    if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                        $value = YamlWriter::plain($value);
                    }
                    break;
                case "list":
                    if (is_array($value)) {
                        $value = array_values(array_filter(array_map(
                            fn($item) => is_array($item) ? self::clean($props["blueprint"], $item) : $item, $value),
                            fn($item) => $item !== []));
                        if (!$value) {
                            continue 2;
                        }
                    }
                    break;
                case "blocks":
                    if (is_array($value)) {
                        $value = array_map(fn($block) => self::cleanBlock($blueprint, $block), $value);
                    }
                    break;
            }
            $clean[$name] = $value;
        }
        foreach ($values as $name => $value) {
            if (!array_key_exists($name, $fields) && $value !== null) {
                $clean[$name] = $value;
            }
        }
        return $clean;
    }

    private static function cleanBlock(Blueprint $blueprint, $block)
    {
        if (!is_array($block) || !is_string($block["type"] ?? null)) {
            return $block;
        }
        $blockBlueprint = $blueprint->blockBlueprint($block["type"]);
        $head = ["type" => $block["type"]];
        if (!empty($block["disabled"])) {
            $head["disabled"] = true;
        }
        unset($block["type"], $block["disabled"]);
        return $head + ($blockBlueprint ? self::clean($blockBlueprint, $block) : $block);
    }
}
