<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

namespace Shaack\Reboot\AddOns;

use Shaack\Logger;
use Shaack\Reboot\AddOn;
use Shaack\Reboot\AddOns\ProtectedAreas\AccessControl;
use Shaack\Reboot\AddOns\ProtectedAreas\LoginThrottle;
use Shaack\Reboot\AddOns\ProtectedAreas\UserStore;
use Shaack\Reboot\Request;
use Shaack\Reboot\TokenStore;

/**
 * Built-in AddOn that puts parts of the site behind a login. The protected
 * areas are path prefixes, everything below them is only served to a logged
 * in user whose account lists a matching path. What the pages contain is up
 * to the site, the AddOn only knows paths and accounts.
 *
 * Accounts are managed in the admin under "Protected Areas" and stored in
 * `local/protected-areas/users.json`. The login page is brought along by the
 * AddOn and rendered inside the site template, so it looks like the site.
 *
 * Configuration in `site/config.yml`:
 *
 *     addons: [ ProtectedAreas ]
 *     protectedAreas:
 *       areas: [ /customers ]   # protected path prefixes, without language prefix
 *       loginPath: /login       # the login page, provided by the AddOn
 *       logoutPath: /logout     # logs out and returns to the homepage
 *       texts:                  # override the built-in texts per language
 *         de:
 *           title: "Kundenbereich"
 *
 * On multilingual sites the paths are compared without the language prefix,
 * so `/de/customers/acme` is protected like `/customers/acme`, and the login
 * page exists for every language, `/de/login` speaks German.
 */
class ProtectedAreas extends AddOn
{
    private const SESSION_KEY = 'protectedAreas';

    /** Seconds a login link stays valid */
    public const LOGIN_LINK_LIFETIME = 15 * 60;
    /** Seconds a visitor stays logged in after a login link, via the remember cookie */
    public const REMEMBER_LIFETIME = 30 * 24 * 60 * 60;
    /** The cookie that keeps a visitor logged in across sessions */
    public const REMEMBER_COOKIE = 'protected_areas_remember';

    private const TEXTS = [
        'en' => [
            'title' => 'Login',
            'intro' => 'This area is protected. Please log in.',
            'emailLabel' => 'E-mail address',
            'passwordLabel' => 'Password',
            'submit' => 'Log in',
            'wrongCredentials' => 'Unknown e-mail address or wrong password.',
            'tooManyAttempts' => 'Too many failed attempts. Please try again in a few minutes.',
            'denied' => 'Your account has no access to this page. Log in with another account to continue.',
            'loggedInAs' => 'You are logged in as %s.',
            'continue' => 'Continue',
            'logout' => 'Log out',
            'linkIntro' => 'Enter your e-mail address and we send you a link that logs you in. No password needed.',
            'linkSubmit' => 'Send me a login link',
            'linkSent' => 'If an account exists for this address, a login link is on its way. Please check your inbox.',
            'linkInvalid' => 'This login link is not valid any more. Please request a new one.',
            'orPassword' => 'Or log in with your password',
            'mailSubject' => 'Your login link for %s',
            'mailBody' => "Hello,\n\nopen this link to log in to %s:\n\n%s\n\nThe link is valid for %d minutes and can be used once. If you did not request it, you can ignore this mail.",
        ],
        'de' => [
            'title' => 'Anmeldung',
            'intro' => 'Dieser Bereich ist geschützt. Bitte melden Sie sich an.',
            'emailLabel' => 'E-Mail-Adresse',
            'passwordLabel' => 'Passwort',
            'submit' => 'Anmelden',
            'wrongCredentials' => 'Unbekannte E-Mail-Adresse oder falsches Passwort.',
            'tooManyAttempts' => 'Zu viele Fehlversuche. Bitte versuchen Sie es in einigen Minuten erneut.',
            'denied' => 'Ihr Konto hat keinen Zugang zu dieser Seite. Melden Sie sich mit einem anderen Konto an, um fortzufahren.',
            'loggedInAs' => 'Sie sind angemeldet als %s.',
            'continue' => 'Weiter',
            'logout' => 'Abmelden',
            'linkIntro' => 'Geben Sie Ihre E-Mail-Adresse ein und wir senden Ihnen einen Link, der Sie anmeldet. Kein Passwort nötig.',
            'linkSubmit' => 'Anmeldelink senden',
            'linkSent' => 'Falls ein Konto für diese Adresse existiert, ist ein Anmeldelink unterwegs. Bitte sehen Sie in Ihr Postfach.',
            'linkInvalid' => 'Dieser Anmeldelink ist nicht mehr gültig. Bitte fordern Sie einen neuen an.',
            'orPassword' => 'Oder mit Passwort anmelden',
            'mailSubject' => 'Ihr Anmeldelink für %s',
            'mailBody' => "Hallo,\n\nöffnen Sie diesen Link, um sich bei %s anzumelden:\n\n%s\n\nDer Link ist %d Minuten gültig und kann einmal verwendet werden. Falls Sie ihn nicht angefordert haben, können Sie diese Mail ignorieren.",
        ],
    ];

    private array $config = [];
    /** @var string[] */
    private array $areas = [];
    private string $loginPath = '/login';
    private string $logoutPath = '/logout';

    /**
     * @see AddOn::init()
     */
    protected function init()
    {
        $this->config = $this->site->getConfig()['protectedAreas'] ?? [];
        $this->areas = AccessControl::normalizePaths((array)($this->config['areas'] ?? []));
        $this->loginPath = AccessControl::normalizePath((string)($this->config['loginPath'] ?? '/login'));
        $this->logoutPath = AccessControl::normalizePath((string)($this->config['logoutPath'] ?? '/logout'));
        if (!$this->areas) {
            Logger::info('ProtectedAreas: no areas configured, nothing is protected');
        }
    }

    /**
     * The login page, once per language of the site.
     * @see AddOn::getPages()
     */
    public function getPages(): array
    {
        $file = $this->dir . '/pages/login.php';
        $pages = [$this->loginPath => $file];
        foreach ($this->getLanguages() as $language) {
            $pages['/' . $language . $this->loginPath] = $file;
        }
        return $pages;
    }

    /**
     * @see AddOn::getAdminMenu()
     */
    public function getAdminMenu(): array
    {
        return ['Protected Areas' => $this->getAdminPath()];
    }

    /**
     * @see AddOn::preRender()
     */
    public function preRender(Request $request): bool
    {
        $path = $request->getPathWithoutLanguage();

        if ($path === $this->logoutPath) {
            $this->startSession();
            $this->logout();
            $this->reboot->redirect($this->site->getWebPath() . ($this->languagePrefix($request) ?: '/'));
            return false;
        }
        if ($path === $this->loginPath) {
            $this->startSession(); // the login page needs the session for the CSRF token
            $token = $request->getParam('token', 'get');
            if (is_string($token) && $token !== '') {
                $this->handleLoginLink($token, $request);
                return false; // handleLoginLink() redirects
            }
            return true;
        }
        if (!AccessControl::isProtected($path, $this->areas)) {
            return true;
        }

        $this->startSession();
        $user = $this->getUser();
        $next = $request->getPath();
        if ($user === null) {
            Logger::info('ProtectedAreas: login required for ' . $path);
            $this->reboot->redirect($this->getLoginUrl($request, $next));
            return false;
        }
        if (!AccessControl::canAccess($path, $this->getUserStore()->getPaths($user))) {
            Logger::info("ProtectedAreas: access denied for '$user' to " . $path);
            $this->reboot->redirect($this->getLoginUrl($request, $next, true));
            return false;
        }
        // A protected page must never end up in a shared cache, web/.htaccess
        // sets a generous default expiry for HTML.
        header('Cache-Control: no-store, private');
        header('Pragma: no-cache');
        header('Expires: 0');
        return true;
    }

    // Public API, used by the login page, the admin page and site templates

    /**
     * The logged in user, or null. Safe to call from the template on any page.
     */
    public function getUser(): ?string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            // Only look for the cookies, do not create a session for a visitor who has none
            if (empty($_COOKIE[session_name()]) && empty($_COOKIE[self::REMEMBER_COOKIE])) {
                return null;
            }
            $this->startSession();
        }
        $user = $_SESSION[self::SESSION_KEY]['user'] ?? null;
        if (!is_string($user) || $user === '') {
            $user = $this->restoreRememberedLogin();
        }
        return $user;
    }

    /**
     * Checks the credentials and opens the session. Returns false for a wrong
     * password, an unknown address or a throttled IP address.
     */
    public function login(string $email, string $password, string $ip): bool
    {
        $username = UserStore::normalizeEmail($email);
        $throttle = $this->getLoginThrottle();
        if ($throttle->isBlocked($ip)) {
            Logger::info("ProtectedAreas: throttled login from $ip");
            return false;
        }
        $store = $this->getUserStore();
        if (!UserStore::isValidEmail($username) || !$store->verify($username, $password)) {
            $throttle->registerFailure($ip);
            Logger::info("ProtectedAreas: failed login for '$username' from $ip");
            return false;
        }
        $this->startSession();
        session_regenerate_id(true);
        $_SESSION[self::SESSION_KEY] = ['user' => $username, 'loginAt' => time()];
        $store->registerLogin($username);
        $throttle->clear($ip);
        Logger::info("ProtectedAreas: login '$username'");
        return true;
    }

    public function isThrottled(string $ip): bool
    {
        return $this->getLoginThrottle()->isBlocked($ip);
    }

    /**
     * Ends the login. Other parts of the session, for example an admin login
     * in the same browser, are left alone.
     */
    public function logout(): void
    {
        if (isset($_COOKIE[self::REMEMBER_COOKIE])) {
            $this->getRememberStore()->delete($_COOKIE[self::REMEMBER_COOKIE]);
            setcookie(self::REMEMBER_COOKIE, '', $this->rememberCookieOptions(1));
            unset($_COOKIE[self::REMEMBER_COOKIE]);
        }
        if (isset($_SESSION[self::SESSION_KEY])) {
            Logger::info('ProtectedAreas: logout ' . ($_SESSION[self::SESSION_KEY]['user'] ?? ''));
            unset($_SESSION[self::SESSION_KEY]);
            session_regenerate_id(true);
        }
    }

    // Login links

    /**
     * Whether the login page offers a link by mail: the site needs a working
     * `mail` configuration in `local/config.yml`, and `protectedAreas.loginLinks`
     * may switch the feature off.
     */
    public function canSendLoginLinks(): bool
    {
        if (($this->config['loginLinks'] ?? true) === false) {
            return false;
        }
        return $this->reboot->getMailer()->isConfigured();
    }

    /**
     * Sends a login link if an account exists for the address. Unknown
     * addresses are ignored without any difference in the response, so the
     * form does not reveal which accounts exist. Requests are limited per IP
     * like failed logins, so the form cannot be used to flood a mailbox.
     *
     * @param ?string $next the page to open after the login, relative to the site root
     */
    public function requestLoginLink(string $email, string $ip, Request $request, ?string $next = null): void
    {
        $throttle = $this->getLinkThrottle();
        if ($throttle->isBlocked($ip)) {
            Logger::info("ProtectedAreas: login link request throttled from $ip");
            return;
        }
        $throttle->registerFailure($ip);
        $email = UserStore::normalizeEmail($email);
        if (!UserStore::isValidEmail($email) || !$this->getUserStore()->hasUser($email)) {
            Logger::info("ProtectedAreas: login link requested for unknown address '$email' from $ip");
            return;
        }
        $this->sendLoginLink($email, $request, $next);
    }

    /**
     * Sends a login link to an existing account, for the admin and for
     * requestLoginLink(). Throws if the account does not exist or the mail
     * cannot be sent.
     */
    public function sendLoginLink(string $email, Request $request, ?string $next = null): void
    {
        $email = UserStore::normalizeEmail($email);
        if (!$this->getUserStore()->hasUser($email)) {
            throw new \InvalidArgumentException("No account for '$email'");
        }
        $token = $this->getLoginLinkStore()->create($email);
        $params = ['token' => $token];
        $next = AccessControl::sanitizeNext($next, '');
        if ($next !== '') {
            $params['next'] = $next;
        }
        $url = $this->getAbsoluteUrl($this->getLoginPath($request) . '?' . http_build_query($params));
        $texts = $this->getTexts($request->getLanguage());
        $siteName = $this->getSiteName();
        $this->reboot->getMailer()->send(
            $email,
            sprintf($texts['mailSubject'], $siteName),
            sprintf($texts['mailBody'], $siteName, $url, (int)(self::LOGIN_LINK_LIFETIME / 60))
        );
        Logger::info("ProtectedAreas: login link sent to '$email'");
    }

    /**
     * Logs the visitor in from a link token, keeps the login for
     * REMEMBER_LIFETIME via the remember cookie and redirects to the page the
     * link was requested for. An invalid token leads back to the login page
     * with a message.
     */
    private function handleLoginLink(string $token, Request $request): void
    {
        $email = $this->getLoginLinkStore()->consume($token);
        $store = $this->getUserStore();
        if ($email === null || !$store->hasUser($email)) {
            Logger::info('ProtectedAreas: invalid login link used');
            $this->reboot->redirect($this->site->getWebPath() . $this->getLoginPath($request) . '?invalid=1');
            return;
        }
        session_regenerate_id(true);
        $_SESSION[self::SESSION_KEY] = ['user' => $email, 'loginAt' => time()];
        $store->registerLogin($email);
        $this->issueRememberCookie($email);
        Logger::info("ProtectedAreas: login via link '$email'");
        $home = $this->languagePrefix($request) ?: '/';
        $next = AccessControl::sanitizeNext($request->getParam('next', 'get'), $this->firstPathOf($email) ?? $home);
        $this->reboot->redirect($this->site->getWebPath() . $next);
    }

    /**
     * Restores the login from the remember cookie, returns the user or null.
     * The token is good for one use and replaced by a fresh one.
     */
    private function restoreRememberedLogin(): ?string
    {
        $raw = $_COOKIE[self::REMEMBER_COOKIE] ?? null;
        if (!is_string($raw) || $raw === '') {
            return null;
        }
        $email = $this->getRememberStore()->consume($raw);
        if ($email === null || !$this->getUserStore()->hasUser($email)) {
            setcookie(self::REMEMBER_COOKIE, '', $this->rememberCookieOptions(1));
            unset($_COOKIE[self::REMEMBER_COOKIE]);
            return null;
        }
        session_regenerate_id(true);
        $_SESSION[self::SESSION_KEY] = ['user' => $email, 'loginAt' => time()];
        $this->issueRememberCookie($email);
        Logger::info("ProtectedAreas: login restored from cookie '$email'");
        return $email;
    }

    private function issueRememberCookie(string $email): void
    {
        $token = $this->getRememberStore()->create($email);
        setcookie(self::REMEMBER_COOKIE, $token, $this->rememberCookieOptions(time() + self::REMEMBER_LIFETIME));
        $_COOKIE[self::REMEMBER_COOKIE] = $token;
    }

    private function rememberCookieOptions(int $expires): array
    {
        return [
            'expires' => $expires,
            'path' => $this->site->getWebPath() ?: '/',
            'secure' => $this->isHttps(),
            'httponly' => true,
            'samesite' => 'Lax', // a link from a mail to a protected page must keep the login
        ];
    }

    private function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    }

    /**
     * An absolute URL for a path below the site, for mails. `protectedAreas.baseUrl`
     * in the site config wins over the host of the current request.
     */
    public function getAbsoluteUrl(string $pathWithQuery): string
    {
        $base = $this->config['baseUrl'] ?? null;
        if (!is_string($base) || $base === '') {
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $base = ($this->isHttps() ? 'https' : 'http') . '://' . $host . $this->site->getWebPath();
        }
        return rtrim($base, '/') . $pathWithQuery;
    }

    private function getSiteName(): string
    {
        $config = $this->site->getConfig();
        $name = $config['navbar']['brand'] ?? $config['title'] ?? null;
        return is_string($name) && $name !== '' ? $name : ($_SERVER['HTTP_HOST'] ?? 'this site');
    }

    /** The first allowed path of an account, as the landing page after a link login. */
    private function firstPathOf(string $email): ?string
    {
        $paths = $this->getUserStore()->getPaths($email);
        return $paths[0] ?? null;
    }

    /** @return string[] the protected path prefixes */
    public function getAreas(): array
    {
        return $this->areas;
    }

    /**
     * The login page of the request's language, relative to the site root.
     */
    public function getLoginPath(?Request $request = null): string
    {
        return ($request ? $this->languagePrefix($request) : '') . $this->loginPath;
    }

    public function getLogoutPath(?Request $request = null): string
    {
        return ($request ? $this->languagePrefix($request) : '') . $this->logoutPath;
    }

    /**
     * The full login URL including the site's web path, with the page to return to.
     */
    public function getLoginUrl(Request $request, ?string $next = null, bool $denied = false): string
    {
        $params = [];
        if ($next !== null && $next !== $this->getLoginPath($request)) {
            $params['next'] = $next;
        }
        if ($denied) {
            $params['denied'] = '1';
        }
        $url = $this->site->getWebPath() . $this->getLoginPath($request);
        return $params ? $url . '?' . http_build_query($params) : $url;
    }

    /**
     * The texts of a language, built-in ones merged with `protectedAreas.texts`.
     */
    public function getTexts(string $language): array
    {
        $texts = self::TEXTS[$language] ?? self::TEXTS['en'];
        $configured = $this->config['texts'][$language] ?? [];
        return is_array($configured) ? array_merge($texts, $configured) : $texts;
    }

    public function getUserStore(): UserStore
    {
        return new UserStore($this->reboot->getBaseFsPath());
    }

    public function getLoginThrottle(): LoginThrottle
    {
        return new LoginThrottle($this->reboot->getBaseFsPath());
    }

    /** Limits login link requests per IP, separate from the password counter. */
    public function getLinkThrottle(): LoginThrottle
    {
        return new LoginThrottle($this->reboot->getBaseFsPath(), 'link-requests.json');
    }

    public function getLoginLinkStore(): TokenStore
    {
        return new TokenStore($this->reboot->getBaseFsPath() . '/local/protected-areas/login-links.json', self::LOGIN_LINK_LIFETIME);
    }

    public function getRememberStore(): TokenStore
    {
        return new TokenStore($this->reboot->getBaseFsPath() . '/local/protected-areas/remember-tokens.json', self::REMEMBER_LIFETIME);
    }

    // Internals

    private function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params([
                'httponly' => true,
                'samesite' => 'Lax', // a link from a mail to a protected page must keep the login
            ]);
            session_start();
        }
    }

    /** @return string[] */
    private function getLanguages(): array
    {
        $languages = $this->site->getConfig()['languages'] ?? [];
        return is_array($languages) ? array_values(array_filter($languages, 'is_string')) : [];
    }

    /**
     * "/de" for a request in German on a multilingual site, "" otherwise.
     */
    private function languagePrefix(Request $request): string
    {
        return $request->getPath() !== $request->getPathWithoutLanguage() ? '/' . $request->getLanguage() : '';
    }
}
