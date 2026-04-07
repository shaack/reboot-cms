<?php

namespace Shaack\Reboot;

use Shaack\Htpasswd;
use Shaack\Logger;
use Symfony\Component\Yaml\Yaml;

class Authentication extends AddOn
{
    private Htpasswd $htpasswd;
    private string $rolesFile;
    private array $roles = [];

    const ROLE_ADMIN = "admin";
    const ROLE_EDITOR = "editor";
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
        $this->rolesFile = $this->reboot->getBaseFsPath() . "/local/roles.yml";
        if (file_exists($this->rolesFile)) {
            $this->roles = Yaml::parseFile($this->rolesFile) ?? [];
        }
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
        return md5($this->htpasswd->getChecksum() . $_SERVER['REMOTE_ADDR'] . $_SERVER['HTTP_ACCEPT_LANGUAGE']);
    }

    public function login($username, $password): bool
    {
        if ($this->htpasswd->validate($username, $password)) {
            session_regenerate_id(true);
            $_SESSION['user'] = $username;
            $_SESSION['checksum'] = $this->getChecksum();
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

    public function logout()
    {
        Logger::info("logout " . $this->getUser());
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

    public function getUserRole(string $username): string
    {
        return $this->roles[$username] ?? self::ROLE_ADMIN;
    }

    public function setUserRole(string $username, string $role): void
    {
        if (!in_array($role, [self::ROLE_ADMIN, self::ROLE_EDITOR])) {
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