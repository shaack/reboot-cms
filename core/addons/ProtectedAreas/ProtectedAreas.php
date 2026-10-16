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
            // Only look for the cookie, do not create a session for a visitor who has none
            if (empty($_COOKIE[session_name()])) {
                return null;
            }
            $this->startSession();
        }
        $user = $_SESSION[self::SESSION_KEY]['user'] ?? null;
        return is_string($user) && $user !== '' ? $user : null;
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
        if (isset($_SESSION[self::SESSION_KEY])) {
            Logger::info('ProtectedAreas: logout ' . ($_SESSION[self::SESSION_KEY]['user'] ?? ''));
            unset($_SESSION[self::SESSION_KEY]);
            session_regenerate_id(true);
        }
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
