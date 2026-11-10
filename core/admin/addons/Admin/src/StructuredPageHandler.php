<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

namespace Shaack\Reboot\AddOns\Admin;

use Shaack\Reboot\Blueprint;
use Shaack\Reboot\YamlWriter;
use Shaack\Utils\FileSystemUtils;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Reads and writes a structured page, a YAML file in `site/pages/` with the
 * fields of the page blueprint, see Blueprint::forPage().
 */
class StructuredPageHandler
{
    private Blueprint $blueprint;
    private string $pagesDir;
    private string $historyDir;
    private int $historyMaxVersions;

    public function __construct(Blueprint $blueprint, string $pagesDir, string $historyDir, int $historyMaxVersions)
    {
        $this->blueprint = $blueprint;
        $this->pagesDir = $pagesDir;
        $this->historyDir = $historyDir;
        $this->historyMaxVersions = $historyMaxVersions;
    }

    /**
     * The values of the file as written. A file that is no valid YAML throws, the
     * admin shows the message and does not overwrite the file.
     */
    public function read(string $file): array
    {
        $source = is_file($file) ? (string)file_get_contents($file) : "";
        if (trim($source) === "") {
            return [];
        }
        try {
            $data = Yaml::parse($source);
        } catch (ParseException $e) {
            throw new \RuntimeException("The page is no valid YAML: " . $e->getMessage());
        }
        return is_array($data) ? $data : [];
    }

    /**
     * The values for the form, see FieldInput::forForm().
     */
    public function readForForm(string $file): array
    {
        return FieldInput::forForm($this->blueprint, $this->read($file));
    }

    /**
     * The problems of the page, field path => message. On a multilingual site
     * the page is checked in its default language, the other languages show
     * that one where they have no value of their own.
     */
    public function validate(string $file): array
    {
        $values = $this->read($file);
        $site = $this->blueprint->getSite();
        if ($site) {
            $values = $site->localize($values, $site->getDefaultLanguage());
        }
        return $this->blueprint->validate($this->blueprint->normalize($values));
    }

    /**
     * The values of the posted form, on a multilingual site with a value per
     * language in the translated fields.
     */
    public function fromInput(array $input): array
    {
        $site = $this->blueprint->getSite();
        return $site && $site->isMultilingual()
            ? FieldInput::fromInput($this->blueprint, $input, $site->getLanguages(), $site->getDefaultLanguage())
            : FieldInput::fromInput($this->blueprint, $input);
    }

    /**
     * Write the posted form into the file. Values the blueprint does not know are kept.
     * @return bool whether the file changed
     */
    public function save(string $file, array $input): bool
    {
        $existing = $this->read($file);
        $values = $this->fromInput($input);
        foreach ($existing as $name => $value) {
            if (!array_key_exists($name, $this->blueprint->getFields())) {
                $values[$name] = $value;
            }
        }
        $yaml = YamlWriter::dump(FieldInput::clean($this->blueprint, $values));
        if (is_file($file) && file_get_contents($file) === $yaml) {
            return false;
        }
        PageHistoryHelper::savePageSnapshot($file, $this->pagesDir, $this->historyDir, $this->historyMaxVersions);
        FileSystemUtils::writeAtomic($file, $yaml);
        return true;
    }
}
