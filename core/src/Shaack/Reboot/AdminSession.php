<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

namespace Shaack\Reboot;

use Shaack\Htpasswd;
use Shaack\Logger;
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
 * the trust.
 *
 * The "Stay signed in" cookie lives here too. Its token is only sent to the
 * admin. The site gets a hint cookie without any secret, which tells a page
 * of the site, a protected area for example, that a detour through
 * `/admin/resume` restores an expired admin session.
 */
class AdminSession
{
    /** The "Stay signed in" cookie, a token of the store below. */
    public const REMEMBER_COOKIE = "reboot_remember";
    /** How long "Stay signed in" lasts, two weeks. */
    public const REMEMBER_LIFETIME = 14 * 24 * 60 * 60;
    /** Tells the site that the admin remembers a login, holds no secret. */
    public const RESUME_HINT_COOKIE = "reboot_remembered";
    /** The admin page that restores the session and returns to the site. */
    public const RESUME_PATH = "/resume";

    /** The tokens for "Stay signed in", `local/remember-tokens.json`. */
    public static function rememberStore(string $baseFsPath): TokenStore
    {
        return new TokenStore(rtrim($baseFsPath, '/') . '/local/remember-tokens.json', self::REMEMBER_LIFETIME);
    }

    /**
     * Restores the login from the "Stay signed in" cookie into the active
     * session. The token is good for one use, a valid one is replaced by a
     * fresh one, anything else clears the cookie. The user must exist in
     * `local/.htpasswd` and must not be disabled.
     *
     * @return ?string the user, null without a valid cookie
     */
    public static function restoreFromRememberCookie(string $baseFsPath, string $baseWebPath): ?string
    {
        $raw = $_COOKIE[self::REMEMBER_COOKIE] ?? null;
        if (!is_string($raw) || $raw === '') {
            return null;
        }
        $store = self::rememberStore($baseFsPath);
        $user = $store->consume($raw);
        $htpasswd = new Htpasswd($baseFsPath . '/local/.htpasswd');
        if ($user === null || !in_array($user, $htpasswd->getUsers(), true) || self::role($baseFsPath, $user) === 'disabled') {
            Logger::info("Stay signed in: cookie rejected");
            self::clearRememberCookie($baseWebPath);
            return null;
        }
        Logger::info("Stay signed in: session restored for " . $user);
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        unset($_SESSION['impersonator']);
        $_SESSION['user'] = $user;
        $_SESSION['checksum'] = self::checksum($htpasswd);
        self::issueRememberCookie($baseFsPath, $baseWebPath, $user);
        return $user;
    }

    /**
     * Whether the active session holds a login to the admin that is still
     * valid, of any role. An expired or stale one is worth a restore from the
     * "Stay signed in" cookie, a valid one is not.
     */
    public static function hasValidLogin(string $baseFsPath): bool
    {
        $user = $_SESSION['user'] ?? null;
        if (!is_string($user) || $user === '') {
            return false;
        }
        return ($_SESSION['checksum'] ?? null) === self::checksum(new Htpasswd($baseFsPath . '/local/.htpasswd'));
    }

    /** Sets the "Stay signed in" cookie with a fresh token for the user. */
    public static function issueRememberCookie(string $baseFsPath, string $baseWebPath, string $user): void
    {
        $token = self::rememberStore($baseFsPath)->create($user);
        $expires = time() + self::REMEMBER_LIFETIME;
        self::setCookie(self::REMEMBER_COOKIE, $token, $expires, self::adminPath($baseWebPath), 'Strict');
        $_COOKIE[self::REMEMBER_COOKIE] = $token;
        // Lax, so the hint also arrives with a link from a mail
        self::setCookie(self::RESUME_HINT_COOKIE, self::resumeHint($baseFsPath), $expires, $baseWebPath ?: '/', 'Lax');
        $_COOKIE[self::RESUME_HINT_COOKIE] = self::resumeHint($baseFsPath);
    }

    /** Removes the "Stay signed in" cookie and its hint from the browser. */
    public static function clearRememberCookie(string $baseWebPath): void
    {
        if (isset($_COOKIE[self::REMEMBER_COOKIE])) {
            self::setCookie(self::REMEMBER_COOKIE, "", 1, self::adminPath($baseWebPath), 'Strict');
            unset($_COOKIE[self::REMEMBER_COOKIE]);
        }
        self::clearResumeHint($baseWebPath);
    }

    /** Removes the hint cookie, for example when the admin has no token to go with it. */
    public static function clearResumeHint(string $baseWebPath): void
    {
        if (isset($_COOKIE[self::RESUME_HINT_COOKIE])) {
            self::setCookie(self::RESUME_HINT_COOKIE, "", 1, $baseWebPath ?: '/', 'Lax');
            unset($_COOKIE[self::RESUME_HINT_COOKIE]);
        }
    }

    /**
     * Whether the browser brings the hint cookie of this installation. Another
     * installation on the same domain, in a parent folder for example, sends
     * its own hint along, which must not count here.
     */
    public static function hasResumeHint(string $baseFsPath): bool
    {
        return ($_COOKIE[self::RESUME_HINT_COOKIE] ?? null) === self::resumeHint($baseFsPath);
    }

    /** The URL of the admin page that restores the session and returns to `$next`. */
    public static function resumeUrl(string $baseWebPath, string $next): string
    {
        return self::adminPath($baseWebPath) . self::RESUME_PATH . "?next=" . rawurlencode($next);
    }

    /**
     * `$next` if it is a path on this server, else the start page. Keeps the
     * resume page from sending a visitor to another site.
     */
    public static function safeNext(string $baseWebPath, mixed $next): string
    {
        if (is_string($next) && preg_match('#^/(?![/\\\\])[^\x00-\x1f\\\\]*\\z#', $next)) {
            return $next;
        }
        return ($baseWebPath ?: '') . '/';
    }

    private static function resumeHint(string $baseFsPath): string
    {
        return substr(hash('sha256', rtrim($baseFsPath, '/')), 0, 16);
    }

    private static function adminPath(string $baseWebPath): string
    {
        return rtrim($baseWebPath, '/') . '/admin';
    }

    private static function setCookie(string $name, string $value, int $expires, string $path, string $sameSite): void
    {
        if (headers_sent()) {
            return;
        }
        setcookie($name, $value, [
            'expires' => $expires,
            'path' => $path,
            'secure' => Session::isHttps(),
            'httponly' => true,
            'samesite' => $sameSite,
        ]);
    }

    private static function role(string $baseFsPath, string $user): string
    {
        $rolesFile = $baseFsPath . '/local/roles.yml';
        $roles = file_exists($rolesFile) ? (Yaml::parseFile($rolesFile) ?? []) : [];
        // Without an entry a user is an admin, like in Authentication::getUserRole()
        return is_array($roles) && is_string($roles[$user] ?? null) ? $roles[$user] : 'admin';
    }

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
        if (self::role($baseFsPath, $user) !== 'admin') {
            return null;
        }
        return $user;
    }
}
