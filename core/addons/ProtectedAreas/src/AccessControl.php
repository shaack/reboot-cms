<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

namespace Shaack\Reboot\AddOns\ProtectedAreas;

/**
 * The pure path logic of the ProtectedAreas AddOn: which paths are protected,
 * which paths a user may enter, and which redirect targets are safe.
 * Everything here is static and free of side effects, so it is easy to test.
 */
class AccessControl
{
    /**
     * A path with exactly one leading slash and no trailing slash. "/" stays "/".
     */
    public static function normalizePath(string $path): string
    {
        $path = '/' . trim($path, '/');
        return preg_replace('#/+#', '/', $path);
    }

    /**
     * @param string[] $paths
     * @return string[] normalized, without duplicates and empty entries
     */
    public static function normalizePaths(array $paths): array
    {
        $result = [];
        foreach ($paths as $path) {
            if (!is_string($path) || trim($path) === '') {
                continue;
            }
            $normalized = self::normalizePath($path);
            if (!in_array($normalized, $result, true)) {
                $result[] = $normalized;
            }
        }
        return $result;
    }

    /**
     * True if `$path` is `$prefix` itself or lies below it. The prefix "/"
     * matches every path.
     */
    public static function isBelow(string $path, string $prefix): bool
    {
        $path = self::normalizePath($path);
        $prefix = self::normalizePath($prefix);
        if ($prefix === '/') {
            return true;
        }
        return $path === $prefix || str_starts_with($path, $prefix . '/');
    }

    /**
     * True if the path lies in one of the protected areas.
     * @param string[] $areas
     */
    public static function isProtected(string $path, array $areas): bool
    {
        foreach ($areas as $area) {
            if (self::isBelow($path, $area)) {
                return true;
            }
        }
        return false;
    }

    /**
     * True if a user with the given allowed paths may enter `$path`.
     * @param string[] $allowedPaths
     */
    public static function canAccess(string $path, array $allowedPaths): bool
    {
        foreach ($allowedPaths as $allowed) {
            if (self::isBelow($path, $allowed)) {
                return true;
            }
        }
        return false;
    }

    /**
     * A redirect target taken from the request, or the fallback if the value
     * is missing or could lead off the site. Only absolute paths of this site
     * are accepted, never a URL with a host.
     */
    public static function sanitizeNext($next, string $fallback): string
    {
        if (!is_string($next) || $next === '' || $next[0] !== '/' || str_starts_with($next, '//')) {
            return $fallback;
        }
        if (preg_match('/[\x00-\x1f\x7f\\\\]/', $next) || str_contains($next, '://')) {
            return $fallback;
        }
        return $next;
    }
}
