<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

namespace Shaack\Reboot\AddOns\Admin;

use Shaack\Logger;
use Shaack\Reboot\AddOn;

/**
 * Builds the admin navigation from the static structure in `core/admin/config.yml`
 * and the entries the site's AddOns contribute through `AddOn::getAdminMenu()`.
 *
 * Flat AddOn entries are placed after the static flat entries and before the
 * first dropdown. Nested entries extend a dropdown of the same name or, if there
 * is none, open a new dropdown at the end. A static entry is never overridden by
 * an AddOn, and invalid entries are logged and skipped.
 */
class AdminMenu
{
    /**
     * @param array $structure the `navbar.structure` of the admin config
     * @param iterable<AddOn> $addOns the AddOns of the site
     */
    public static function build(array $structure, iterable $addOns): array
    {
        $flat = [];
        $dropdowns = [];
        foreach ($addOns as $addOn) {
            foreach (self::sanitize($addOn->getAdminMenu(), get_class($addOn)) as $label => $entry) {
                if (is_array($entry)) {
                    $dropdowns[$label] = array_merge($dropdowns[$label] ?? [], $entry);
                } else {
                    $flat[$label] = $entry;
                }
            }
        }
        return self::merge($structure, $flat, $dropdowns);
    }

    /**
     * Merges the collected AddOn entries into the static structure.
     */
    public static function merge(array $structure, array $flat, array $dropdowns): array
    {
        $result = [];
        $flatInserted = false;
        foreach ($structure as $label => $entry) {
            if (is_array($entry)) {
                if (!$flatInserted) {
                    $result = self::appendFlat($result, $flat);
                    $flatInserted = true;
                }
                if (isset($dropdowns[$label])) {
                    $entry = $entry + $dropdowns[$label]; // static entries win
                    unset($dropdowns[$label]);
                }
            }
            $result[$label] = $entry;
        }
        if (!$flatInserted) {
            $result = self::appendFlat($result, $flat);
        }
        foreach ($dropdowns as $label => $entries) {
            if (!isset($result[$label])) {
                $result[$label] = $entries;
            }
        }
        return $result;
    }

    private static function appendFlat(array $result, array $flat): array
    {
        foreach ($flat as $label => $path) {
            if (!isset($result[$label])) {
                $result[$label] = $path;
            }
        }
        return $result;
    }

    /**
     * Keeps only well-formed entries: a non-empty string label with a path
     * starting with `/`, or a nested array of such entries.
     */
    private static function sanitize(array $entries, string $source): array
    {
        $clean = [];
        foreach ($entries as $label => $entry) {
            if (!is_string($label) || trim($label) === '') {
                Logger::error("AdminMenu: $source returned an entry without a label");
                continue;
            }
            if (is_array($entry)) {
                $nested = [];
                foreach ($entry as $subLabel => $subPath) {
                    if (self::isValidPath($subPath) && is_string($subLabel) && trim($subLabel) !== '') {
                        $nested[$subLabel] = $subPath;
                    } else {
                        Logger::error("AdminMenu: $source returned an invalid entry in dropdown '$label'");
                    }
                }
                if ($nested) {
                    $clean[$label] = $nested;
                }
            } elseif (self::isValidPath($entry)) {
                $clean[$label] = $entry;
            } else {
                Logger::error("AdminMenu: $source returned an invalid path for '$label'");
            }
        }
        return $clean;
    }

    private static function isValidPath($path): bool
    {
        return is_string($path) && str_starts_with($path, '/') && !str_starts_with($path, '//');
    }
}
