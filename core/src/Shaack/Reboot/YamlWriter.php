<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

namespace Shaack\Reboot;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Writes YAML for files people read and edit by hand, data
 * entries and structured pages. Symfony's dumper quotes every text with a
 * space, this writer quotes only where YAML needs it, writes text with line
 * breaks as a `|` block and nested lists in block style:
 *
 * ```yaml
 * title: About us
 * blocks:
 *   - type: intro
 *     heading: Welcome
 *     text: |-
 *       We build **websites**.
 *       Since 2001.
 * ```
 */
class YamlWriter
{
    private const RESERVED_KEYS = ["y", "n", "yes", "no", "on", "off", "true", "false", "null", "~"];

    /**
     * A value written as it is, without quotes, a date like `2027-03-12` for
     * example, which YAML would otherwise read as a timestamp.
     */
    public static function plain(string $value): object
    {
        return new class($value) {
            public function __construct(public string $value)
            {
            }
        };
    }

    public static function dump(array $data): string
    {
        return $data === [] ? "{}\n" : implode("\n", self::lines($data, "")) . "\n";
    }

    private static function lines(array $data, string $indent): array
    {
        $lines = [];
        if (array_is_list($data)) {
            foreach ($data as $item) {
                if (is_array($item) && $item !== [] && array_is_list($item)) {
                    $lines[] = $indent . "-";
                    array_push($lines, ...self::lines($item, $indent . "  "));
                } else if (is_array($item) && $item !== []) {
                    $itemLines = self::lines($item, $indent . "  ");
                    // the first field of the item goes next to the dash
                    $itemLines[0] = $indent . "- " . substr($itemLines[0], strlen($indent) + 2);
                    array_push($lines, ...$itemLines);
                } else {
                    array_push($lines, ...self::scalarLines($indent . "-", $item, $indent . "  "));
                }
            }
            return $lines;
        }
        foreach ($data as $key => $value) {
            $prefix = $indent . self::key((string)$key) . ":";
            if (is_array($value) && $value !== []) {
                $lines[] = $prefix;
                // a list stays under its key, indented as most people write it
                array_push($lines, ...self::lines($value, $indent . "  "));
            } else {
                array_push($lines, ...self::scalarLines($prefix, $value, $indent . "  "));
            }
        }
        return $lines;
    }

    /**
     * `$prefix` followed by the value, a text with line breaks in several lines.
     */
    private static function scalarLines(string $prefix, $value, string $blockIndent): array
    {
        if (is_object($value) && property_exists($value, "value")) {
            return [$prefix . " " . $value->value];
        }
        if (is_string($value) && str_contains($value, "\n") && self::fitsLiteralBlock($value)) {
            $lines = [$prefix . " " . (str_ends_with($value, "\n") ? "|" : "|-")];
            foreach (explode("\n", rtrim($value, "\n")) as $line) {
                $lines[] = $line === "" ? "" : $blockIndent . $line;
            }
            return $lines;
        }
        return [$prefix . " " . self::scalar($value)];
    }

    private static function fitsLiteralBlock(string $value): bool
    {
        return !str_contains($value, "\r") && !str_starts_with($value, " ") && !str_starts_with($value, "\n")
            && !str_ends_with($value, "\n\n");
    }

    private static function scalar($value): string
    {
        if ($value === []) {
            return "[]";
        }
        if (is_string($value) && !str_contains($value, "\n") && $value !== "") {
            try {
                if (Yaml::parse("k: " . $value) === ["k" => $value]) {
                    return $value;
                }
            } catch (ParseException $e) {
                // needs quotes
            }
        }
        return Yaml::dump($value, 0);
    }

    private static function key(string $key): string
    {
        if (preg_match('/^[A-Za-z_][\w\-]*$/', $key) && !in_array(strtolower($key), self::RESERVED_KEYS, true)) {
            return $key;
        }
        return Yaml::dump($key, 0);
    }
}
