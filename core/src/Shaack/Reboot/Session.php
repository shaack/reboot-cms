<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

namespace Shaack\Reboot;

/**
 * Starts the PHP session with the same cookie flags everywhere: HttpOnly,
 * Secure on HTTPS, strict mode (an unknown session id from the browser is not
 * taken over). Site and admin share the cookie, only SameSite differs.
 */
class Session
{
    /**
     * @param string $sameSite "Strict" for the admin, "Lax" where a link from a mail must keep the login
     */
    public static function start(string $sameSite = 'Lax'): void
    {
        if (session_status() !== PHP_SESSION_NONE) {
            return;
        }
        ini_set('session.use_strict_mode', '1');
        session_set_cookie_params([
            'httponly' => true,
            'secure' => self::isHttps(),
            'samesite' => $sameSite,
        ]);
        session_start();
    }

    public static function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    }
}
