<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

namespace Shaack\Reboot;

use Shaack\Logger;

/**
 * Finds, loads and instantiates the AddOns of a site.
 *
 * An AddOn is a folder named after its class, with the class file at the top
 * level and optional `src/` (helper classes) and `admin/` (admin pages) folders:
 *
 *     site/addons/MyAddOn/MyAddOn.php          project specific AddOn
 *     core/addons/SecureDownloads/SecureDownloads.php  AddOn shipped with Reboot CMS
 *
 * The class lives in the namespace `Shaack\Reboot\AddOns`, the classes in `src/`
 * in `Shaack\Reboot\AddOns\<Name>\`. They are autoloaded as soon as the AddOn is
 * loaded. The site folder is searched before the core folder, so a project can
 * override a built-in AddOn by shipping one of the same name.
 *
 * The former single file format, `site/addons/MyAddOn.php` with the class in
 * the namespace `Shaack\Reboot`, still loads but is deprecated.
 */
class AddOnLoader
{
    public const NAMESPACE = 'Shaack\\Reboot\\AddOns\\';
    private const LEGACY_NAMESPACE = 'Shaack\\Reboot\\';

    /** @var array<string, string> registered autoloaders, name => dir */
    private static array $registered = [];

    /**
     * Loads the AddOn `$name` for `$site`. Returns null if it cannot be loaded,
     * the reason is logged.
     */
    public static function load(Reboot $reboot, Site $site, string $name): ?AddOn
    {
        if (!self::isValidName($name)) {
            Logger::error("Invalid addon name rejected: " . $name);
            return null;
        }
        $location = self::locate($reboot->getBaseFsPath(), $site->getFsPath(), $name);
        if ($location === null) {
            Logger::error("Addon not found: " . $name);
            return null;
        }
        [$file, $dir] = $location;
        if ($dir !== null) {
            self::registerAutoloader($name, $dir);
        } else {
            Logger::info("Addon '$name' uses the deprecated single file format. "
                . "Move it to addons/$name/$name.php and change its namespace to " . rtrim(self::NAMESPACE, '\\') . ".");
        }
        require_once $file;
        $className = self::NAMESPACE . $name;
        if (!class_exists($className, false)) {
            $legacyClassName = self::LEGACY_NAMESPACE . $name;
            if (!class_exists($legacyClassName, false)) {
                Logger::error("Addon '$name': class $className not found in $file");
                return null;
            }
            $className = $legacyClassName;
        }
        if (!is_subclass_of($className, AddOn::class)) {
            Logger::error("Addon '$name': $className does not extend " . AddOn::class);
            return null;
        }
        return new $className($reboot, $site, $dir);
    }

    /**
     * The file of the AddOn class and its folder, or null for the deprecated
     * single file format. Null if the AddOn is not found anywhere.
     * @return array{0: string, 1: ?string}|null
     */
    public static function locate(string $baseFsPath, string $siteFsPath, string $name): ?array
    {
        $candidates = [
            [$siteFsPath . "/addons/$name/$name.php", $siteFsPath . "/addons/$name"],
            [$siteFsPath . "/addons/$name.php", null],
            [$baseFsPath . "/core/addons/$name/$name.php", $baseFsPath . "/core/addons/$name"],
        ];
        foreach ($candidates as $candidate) {
            if (is_file($candidate[0])) {
                return $candidate;
            }
        }
        return null;
    }

    /**
     * Makes the classes in `<dir>/src/` available under `Shaack\Reboot\AddOns\<Name>\`.
     * Registering a name twice has no effect.
     */
    public static function registerAutoloader(string $name, string $dir): void
    {
        if (isset(self::$registered[$name])) {
            return;
        }
        self::$registered[$name] = $dir;
        $prefix = self::NAMESPACE . $name . '\\';
        spl_autoload_register(function (string $class) use ($prefix, $dir) {
            if (!str_starts_with($class, $prefix)) {
                return;
            }
            $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
            $file = $dir . '/src/' . $relative . '.php';
            if (is_file($file)) {
                require $file;
            }
        });
    }

    /**
     * An AddOn name is a class name and a folder name, nothing else is allowed.
     */
    public static function isValidName(string $name): bool
    {
        return preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $name) === 1;
    }
}
