<?php

namespace Shaack\Reboot\AddOns;

use Shaack\Htpasswd;
use Shaack\Logger;
use Shaack\Reboot\AddOn;
use Shaack\Reboot\AddOns\Authentication\RememberMeStore;
use Shaack\Reboot\Request;
use Symfony\Component\Yaml\Yaml;

class Authentication extends AddOn
{
    private Htpasswd $htpasswd;
    private RememberMeStore $rememberMe;
    private string $rolesFile;
    private array $roles = [];

    /** The "Stay signed in" cookie, see RememberMeStore. */
    const REMEMBER_COOKIE = "reboot_remember";

    const ROLE_ADMIN = "admin";
    const ROLE_EDITOR = "editor";
    const ROLE_DISABLED = "disabled";
    const EDITOR_PAGES = ["/pages", "/media"];

    /**
     * @see AddOn::init()
     */
    protected function init() {
        session_set_cookie_params([
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
        session_start();
        $this->htpasswd = new Htpasswd($this->reboot->getBaseFsPath() . "/local/.htpasswd");
        $this->rememberMe = new RememberMeStore($this->reboot->getBaseFsPath());
        $this->rolesFile = $this->reboot->getBaseFsPath() . "/local/roles.yml";
        if (file_exists($this->rolesFile)) {
            $this->roles = Yaml::parseFile($this->rolesFile) ?? [];
        }
        if (empty($_SESSION['user']) && isset($_COOKIE[self::REMEMBER_COOKIE])) {
            $this->loginFromRememberCookie();
        }
    }

    /**
     * Restores the session from the "Stay signed in" cookie. The token is
     * good for one use, a valid one is replaced by a fresh one, anything
     * else clears the cookie.
     */
    private function loginFromRememberCookie(): void
    {
        $username = $this->rememberMe->consume($_COOKIE[self::REMEMBER_COOKIE]);
        if ($username === null || !in_array($username, $this->htpasswd->getUsers(), true)
            || $this->getUserRole($username) === self::ROLE_DISABLED) {
            Logger::info("Stay signed in: cookie rejected");
            $this->clearRememberCookie();
            return;
        }
        Logger::info("Stay signed in: session restored for " . $username);
        $this->startSession($username);
        $this->issueRememberCookie($username);
    }

    private function startSession(string $username): void
    {
        session_regenerate_id(true);
        $_SESSION['user'] = $username;
        $_SESSION['checksum'] = $this->getChecksum();
    }

    private function issueRememberCookie(string $username): void
    {
        $token = $this->rememberMe->create($username);
        setcookie(self::REMEMBER_COOKIE, $token, $this->rememberCookieOptions(time() + RememberMeStore::LIFETIME));
        $_COOKIE[self::REMEMBER_COOKIE] = $token;
    }

    private function clearRememberCookie(): void
    {
        if (isset($_COOKIE[self::REMEMBER_COOKIE])) {
            setcookie(self::REMEMBER_COOKIE, "", $this->rememberCookieOptions(1));
            unset($_COOKIE[self::REMEMBER_COOKIE]);
        }
    }

    private function rememberCookieOptions(int $expires): array
    {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
        return [
            'expires' => $expires,
            'path' => $this->site->getWebPath() ?: '/',
            'secure' => $https,
            'httponly' => true,
            'samesite' => 'Strict',
        ];
    }

    /**
     * @see AddOn::preRender()
     */
    public function preRender(Request $request): bool
    {
        if ($this->htpasswd->isEmpty()) {
            if ($request->getPath() !== "/setup") {
                $this->reboot->redirect($this->site->getWebPath() . "/setup");
                return false;
            }
            return true;
        }
        // Handle stop impersonating before access control
        if ($this->isImpersonating() && $request->getParam("action") === "stop_impersonate") {
            $this->stopImpersonating();
            $this->reboot->redirect($this->site->getWebPath() . "/users");
            return false;
        }

        $user = $this->getUser();
        if (!$user && $request->getPath() !== "/login") {
            Logger::info("No user found, redirect to the login");
            $this->reboot->redirect( $this->site->getWebPath() . "/login");
            return false;
        } else if ($user) {
            if (@$_SESSION['checksum'] !== $this->getChecksum()) {
                // The session is stale (password file, IP or browser changed). Only the session
                // ends, a "Stay signed in" cookie restores it on the next request.
                $this->endSession();
                return false;
            }
            // Disabled accounts lose access immediately (skip if impersonating — admin retains access)
            if (!$this->isImpersonating() && $this->getUserRole($user) === self::ROLE_DISABLED) {
                Logger::info("Access denied, account disabled: " . $user);
                $this->rememberMe->deleteForSubject($user);
                $this->logout();
                return false;
            }
            // Restrict editor access to allowed pages only (skip if impersonating — admin retains access)
            if (!$this->isImpersonating() && $this->getUserRole($user) === self::ROLE_EDITOR) {
                $path = $request->getPath();
                $allowed = false;
                foreach (self::EDITOR_PAGES as $editorPage) {
                    if ($path === $editorPage || str_starts_with($path, $editorPage . "/")) {
                        $allowed = true;
                        break;
                    }
                }
                if (!$allowed && $path !== "/login" && $path !== "/logout") {
                    $this->reboot->redirect($this->site->getWebPath() . "/pages");
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * Calculates a checksum for the admin session. Detects, if the .htpasswd was changed, the IP-Address or
     * the user agent of the user.
     * @return string md5 checksum
     */
    private function getChecksum(): string
    {
        return md5($this->htpasswd->getChecksum() . ($_SERVER['REMOTE_ADDR'] ?? '') . ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''));
    }

    /**
     * @param bool $remember keep the user signed in for two weeks via the "Stay signed in" cookie
     */
    public function login($username, $password, bool $remember = false): bool
    {
        if ($this->htpasswd->validate($username, $password)) {
            if ($this->getUserRole($username) === self::ROLE_DISABLED) {
                Logger::info("Login denied, account disabled: " . $username);
                return false;
            }
            $this->startSession($username);
            if ($remember) {
                $this->issueRememberCookie($username);
            }
            return true;
        }
        return false;
    }

    public function impersonate(string $username): void
    {
        $_SESSION['impersonator'] = $_SESSION['user'];
        $_SESSION['user'] = $username;
    }

    public function stopImpersonating(): void
    {
        if (isset($_SESSION['impersonator'])) {
            $_SESSION['user'] = $_SESSION['impersonator'];
            unset($_SESSION['impersonator']);
        }
    }

    public function isImpersonating(): bool
    {
        return isset($_SESSION['impersonator']);
    }

    public function getImpersonator(): ?string
    {
        return $_SESSION['impersonator'] ?? null;
    }

    /**
     * Explicit logout: ends the session and forgets the "Stay signed in" cookie.
     */
    public function logout()
    {
        Logger::info("logout " . $this->getUser());
        $this->rememberMe->delete($_COOKIE[self::REMEMBER_COOKIE] ?? null);
        $this->clearRememberCookie();
        $this->endSession();
    }

    /**
     * Ends the session and sends the browser to the admin, which asks for a
     * login unless a "Stay signed in" cookie restores the session.
     */
    private function endSession(): void
    {
        $_SESSION = [];
        session_regenerate_id(true);
        session_destroy();
        $this->reboot->redirect($this->reboot->getBaseWebPath() . "/admin");
    }

    /**
     * @return mixed|null Returns the username, if logged in or null if not
     */
    public function getUser()
    {
        return @$_SESSION['user'];
    }

    public function getHtpasswd(): Htpasswd
    {
        return $this->htpasswd;
    }

    public function refreshChecksum(): void
    {
        $_SESSION['checksum'] = $this->getChecksum();
    }

    /**
     * Forget the "Stay signed in" tokens of a user, after a password change,
     * a deletion or when the account is disabled.
     */
    public function forgetRememberedLogins(string $username): void
    {
        $this->rememberMe->deleteForSubject($username);
    }

    public function getUserRole(string $username): string
    {
        return $this->roles[$username] ?? self::ROLE_ADMIN;
    }

    public function setUserRole(string $username, string $role): void
    {
        if (!in_array($role, [self::ROLE_ADMIN, self::ROLE_EDITOR, self::ROLE_DISABLED])) {
            throw new \InvalidArgumentException("Invalid role: $role");
        }
        $this->roles[$username] = $role;
        $this->saveRoles();
    }

    public function deleteUserRole(string $username): void
    {
        unset($this->roles[$username]);
        $this->saveRoles();
    }

    public function isAdmin(?string $username = null): bool
    {
        $username = $username ?? $this->getUser();
        if (!$username) return false;
        return $this->getUserRole($username) === self::ROLE_ADMIN;
    }

    private function saveRoles(): void
    {
        file_put_contents($this->rolesFile, Yaml::dump($this->roles));
    }
}