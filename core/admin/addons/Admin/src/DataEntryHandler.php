<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

namespace Shaack\Reboot\AddOns\Admin;

use Shaack\Reboot\Blueprint;
use Shaack\Reboot\Entry;
use Shaack\Reboot\Page;
use Shaack\Utils\FileSystemUtils;
use Shaack\Reboot\YamlWriter;

/**
 * Writes the entries of a collection from the form of the admin page "Data".
 * An entry stays a Markdown file a person can edit by hand: the fields of the
 * blueprint come first and in its order, values the blueprint does not know
 * are kept, empty fields are left out, so their default applies.
 *
 * On a multilingual site the translated fields hold a value per language. The
 * body in the default language stays below the frontmatter, the frontmatter
 * holds it in the other languages, `body: {de: "..."}`, see Entry.
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
     * @return array [array $values, string|array $body] the body with a value per language if it has more than one
     */
    public function read(string $slug): array
    {
        $file = $this->file($slug);
        if (!$file) {
            throw new \InvalidArgumentException("There is no entry '" . $slug . "'.");
        }
        [$values, $body] = Page::splitFrontmatter(trim((string)file_get_contents($file)));
        $values = Entry::withBody($values, trim($body), $this->defaultLanguage());
        $body = $values["body"];
        unset($values["body"]);
        return [FieldInput::forForm($this->blueprint, $values), $body];
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
            $body = $values["body"] ?? "";
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
     * The strings of the form in the types of the fields, see FieldInput::fromInput(),
     * on a multilingual site with a value per language in the translated fields.
     */
    public function fromInput(array $input): array
    {
        $site = $this->blueprint->getSite();
        return $site && $site->isMultilingual()
            ? FieldInput::fromInput($this->blueprint, $input, $site->getLanguages(), $site->getDefaultLanguage())
            : FieldInput::fromInput($this->blueprint, $input);
    }

    /**
     * The Markdown file of an entry, the fields as frontmatter, the field `body` below it.
     * @param array $extra values of the file the blueprint does not know, they are kept
     * @param string|array $body the body, or a value per language: the default one goes
     *     below the frontmatter, the others into it
     */
    public function serialize(array $values, array $extra, bool $draft, $body): string
    {
        unset($values["body"]);
        $translations = [];
        if (is_array($body)) {
            $translations = array_filter($body, fn($text) => is_string($text) && trim($text) !== "");
            $body = (string)($translations[$this->defaultLanguage()] ?? "");
            unset($translations[$this->defaultLanguage()]);
        }
        $frontmatter = FieldInput::clean($this->blueprint, $values);
        unset($frontmatter["body"]);
        if ($draft) {
            $frontmatter["draft"] = true;
        }
        foreach ($extra as $name => $value) {
            if (!array_key_exists($name, $this->blueprint->getFields()) && $name !== "draft") {
                $frontmatter[$name] = $value;
            }
        }
        if ($translations) {
            $frontmatter["body"] = $translations;
        }
        $markdown = "---\n" . ($frontmatter ? YamlWriter::dump($frontmatter) : "\n") . "---\n";
        if (trim($body) !== "") {
            $markdown .= "\n" . trim($body) . "\n";
        }
        return $markdown;
    }

    private function defaultLanguage(): ?string
    {
        return $this->blueprint->getSite()?->getDefaultLanguage();
    }

    private function newSlug(array $values): string
    {
        // named in the default language
        $site = $this->blueprint->getSite();
        if ($site) {
            $values = $site->localize($values, $site->getDefaultLanguage());
        }
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
