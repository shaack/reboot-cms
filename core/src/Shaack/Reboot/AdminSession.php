<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

namespace Shaack\Reboot;

use Shaack\Htpasswd;
use Symfony\Component\Yaml\Yaml;

/**
 * Reads the admin login from the PHP session, for code outside the admin.
 * The admin and the site share the session cookie, so a page of the site can
 * tell whether the visitor is logged in to the admin, without loading the
 * Authentication AddOn and its redirects.
 *
 * The rules are those of the Authentication AddOn: the user must exist in
 * `local/.htpasswd`, must not be disabled in `local/roles.yml`, and the session
 * checksum must match, so a changed password file, IP address or browser ends
 * the trust. The "Stay signed in" cookie is not evaluated here, it is restored
 * on the next visit of the admin.
 */
class AdminSession
{
    /**
     * The session checksum, see Authentication::preRender(). Detects a changed
     * .htpasswd, IP address or browser.
     */
    public static function checksum(Htpasswd $htpasswd): string
    {
        return md5($htpasswd->getChecksum() . ($_SERVER['REMOTE_ADDR'] ?? '') . ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''));
    }

    /**
     * The admin logged in with the active session, or null. While an admin
     * impersonates another user, the admin counts, not the impersonated user.
     * Never starts a session, the caller decides about that.
     *
     * @param ?array $session the session data, the active session if null
     */
    public static function getAdmin(string $baseFsPath, ?array $session = null): ?string
    {
        if ($session === null) {
            if (session_status() !== PHP_SESSION_ACTIVE) {
                return null;
            }
            $session = $_SESSION;
        }
        $user = $session['impersonator'] ?? $session['user'] ?? null;
        if (!is_string($user) || $user === '') {
            return null;
        }
        $htpasswd = new Htpasswd($baseFsPath . '/local/.htpasswd');
        if (!in_array($user, $htpasswd->getUsers(), true)) {
            return null;
        }
        if (($session['checksum'] ?? null) !== self::checksum($htpasswd)) {
            return null;
        }
        $rolesFile = $baseFsPath . '/local/roles.yml';
        $roles = file_exists($rolesFile) ? (Yaml::parseFile($rolesFile) ?? []) : [];
        // Without an entry a user is an admin, like in Authentication::getUserRole()
        if (($roles[$user] ?? 'admin') !== 'admin') {
            return null;
        }
        return $user;
    }
}
