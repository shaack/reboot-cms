<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

namespace Shaack\Reboot\AddOns\Admin;

use Shaack\Reboot\Blueprint;
use Shaack\Reboot\Page;
use Shaack\Utils\FileSystemUtils;
use Symfony\Component\Yaml\Yaml;

/**
 * Writes the entries of a collection from the form of the admin page "Data".
 * An entry stays a Markdown file a person can edit by hand: the fields of the
 * blueprint come first and in its order, values the blueprint does not know
 * are kept, empty fields are left out, so their default applies.
 */
class DataEntryHandler
{
    private Blueprint $blueprint;
    private string $dataDir;
    private string $historyDir;
    private int $historyMaxVersions;

    /**
     * @param string $dataDir `site/data`, the collection is a folder in it
     * @param string $historyDir `local/history/data`, keeps the version before every save and delete
     */
    public function __construct(Blueprint $blueprint, string $dataDir, string $historyDir, int $historyMaxVersions)
    {
        $this->blueprint = $blueprint;
        $this->dataDir = $dataDir;
        $this->historyDir = $historyDir;
        $this->historyMaxVersions = $historyMaxVersions;
    }

    public function getFolder(): string
    {
        return $this->dataDir . "/" . $this->blueprint->getName();
    }

    /**
     * The file of an entry, or null if there is none or the slug leads out of the collection.
     */
    public function file(string $slug): ?string
    {
        if (!preg_match('/^[\w\-]+$/', $slug)) {
            return null;
        }
        $file = $this->getFolder() . "/" . $slug . ".md";
        return is_file($file) ? $file : null;
    }

    /**
     * The fields of an entry as written in its file, without defaults, for the form.
     * @return array [array $values, string $body]
     */
    public function read(string $slug): array
    {
        $file = $this->file($slug);
        if (!$file) {
            throw new \InvalidArgumentException("There is no entry '" . $slug . "'.");
        }
        [$values, $body] = Page::splitFrontmatter(trim((string)file_get_contents($file)));
        foreach ($this->blueprint->getFields() as $name => $props) {
            if ($props["type"] === "date" && is_int($values[$name] ?? null)) {
                $values[$name] = gmdate("Y-m-d", $values[$name]);
            }
        }
        return [$values, trim($body)];
    }

    /**
     * Save the form of an entry. Without a slug a new entry is created, its file
     * name is made from the first date and the first text field, for example
     * `2027-03-12-photo-basics`.
     * @param array $input the posted fields, name => string
     * @return string the slug of the saved entry
     */
    public function save(?string $slug, array $input, bool $draft): string
    {
        $values = $this->fromInput($input);
        $extra = [];
        $body = "";
        if ($slug !== null) {
            [$extra, $body] = $this->read($slug);
            $file = $this->file($slug);
        } else {
            $slug = $this->newSlug($values);
            $file = $this->getFolder() . "/" . $slug . ".md";
        }
        if (array_key_exists("body", $this->blueprint->getFields())) {
            $body = (string)($values["body"] ?? "");
        }
        if (!is_dir($this->getFolder())) {
            mkdir($this->getFolder(), 0755, true);
        }
        PageHistoryHelper::savePageSnapshot($file, $this->dataDir, $this->historyDir, $this->historyMaxVersions);
        FileSystemUtils::writeAtomic($file, $this->serialize($values, $extra, $draft, $body));
        return $slug;
    }

    public function delete(string $slug): void
    {
        $file = $this->file($slug);
        if (!$file) {
            throw new \InvalidArgumentException("There is no entry '" . $slug . "'.");
        }
        PageHistoryHelper::savePageSnapshot($file, $this->dataDir, $this->historyDir, $this->historyMaxVersions);
        unlink($file);
    }

    /**
     * Turn the strings of the form into the types of the fields. A value that does
     * not fit stays as it is, so the validation names the problem.
     */
    public function fromInput(array $input): array
    {
        $values = [];
        foreach ($this->blueprint->getFields() as $name => $props) {
            $value = $input[$name] ?? null;
            if ($props["type"] === "boolean") {
                $values[$name] = (string)$value === "1";
                continue;
            }
            if (!is_string($value)) {
                $values[$name] = null;
                continue;
            }
            $value = str_replace("\r\n", "\n", $value);
            $value = in_array($props["type"], ["textarea", "markdown"], true) ? rtrim($value) : trim($value);
            if ($value === "") {
                $values[$name] = null;
            } else if ($props["type"] === "number" && is_numeric($value)) {
                $values[$name] = str_contains($value, ".") || stripos($value, "e") !== false ? (float)$value : (int)$value;
            } else {
                $values[$name] = $value;
            }
        }
        return $values;
    }

    /**
     * The Markdown file of an entry.
     * @param array $extra values of the file the blueprint does not know, they are kept
     */
    public function serialize(array $values, array $extra, bool $draft, string $body): string
    {
        $lines = [];
        $fields = $this->blueprint->getFields();
        foreach ($fields as $name => $props) {
            $value = $values[$name] ?? null;
            if ($name === "body" || $value === null || $value === "") {
                continue;
            }
            if ($props["type"] === "boolean" && $value === (bool)($props["default"] ?? false)) {
                continue;
            }
            $lines[] = $this->line($name, $value, $props["type"]);
        }
        if ($draft) {
            $lines[] = "draft: true";
        }
        foreach ($extra as $name => $value) {
            if (!array_key_exists($name, $fields) && $name !== "draft") {
                $lines[] = $this->line((string)$name, $value, "");
            }
        }
        $markdown = "---\n" . implode("\n", $lines) . "\n---\n";
        if (trim($body) !== "") {
            $markdown .= "\n" . trim($body) . "\n";
        }
        return $markdown;
    }

    private function line(string $name, $value, string $type): string
    {
        // a date stays unquoted, as people write it
        if ($type === "date" && is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $name . ": " . $value;
        }
        $plainName = preg_match('/^[A-Za-z][\w\-]*$/', $name);
        // Yaml quotes every text with a space, a text that reads back the same without quotes stays without
        if ($plainName && is_string($value) && !str_contains($value, "\n")) {
            try {
                if (Yaml::parse($name . ": " . $value) === [$name => $value]) {
                    return $name . ": " . $value;
                }
            } catch (\Exception $e) {
                // needs quotes
            }
        }
        $yaml = rtrim(Yaml::dump([$name => $value], 2, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK));
        // Yaml quotes keys like "y" or "n", which YAML 1.1 read as booleans, a plain name reads better
        if ($plainName) {
            $yaml = preg_replace('/^\'' . preg_quote($name, '/') . '\':/', $name . ':', $yaml);
        }
        return $yaml;
    }

    private function newSlug(array $values): string
    {
        $date = "";
        $text = "";
        foreach ($this->blueprint->getFields() as $name => $props) {
            $value = $values[$name] ?? null;
            if (!is_string($value)) {
                continue;
            }
            if ($date === "" && $props["type"] === "date" && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                $date = $value;
            } else if ($text === "" && $props["type"] === "text") {
                $text = $value;
            }
        }
        $text = strtr(mb_strtolower($text), ["ä" => "ae", "ö" => "oe", "ü" => "ue", "ß" => "ss"]);
        $text = trim(preg_replace('/[^a-z0-9]+/', '-', $text), "-");
        $slug = trim($date . "-" . substr($text, 0, 60), "-") ?: "entry";
        $unique = $slug;
        for ($i = 2; file_exists($this->getFolder() . "/" . $unique . ".md"); $i++) {
            $unique = $slug . "-" . $i;
        }
        return $unique;
    }
}
