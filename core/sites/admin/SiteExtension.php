<?php

namespace Shaack\Reboot;

use Shaack\Utils\Htpasswd;
use Shaack\Utils\Logger;

class SiteExtension extends Site
{
    private $htpasswd;

    public function __construct(Reboot $reboot, string $siteName, string $siteWebPath)
    {
        parent::__construct($reboot, $siteName, $siteWebPath);
        session_start();
        $this->htpasswd = new Htpasswd($reboot->getBaseFsPath() . "/local/.htpasswd");
    }

    public function render(Request $request): string
    {
        $user = $this->getUser();
        if (!$user && $request->getPath() !== "/login") {
            Logger::info("No user found, redirect to the login");
            $this->reboot->redirect( $this->reboot->getBaseWebPath() . "/" . $this->name . "/login");
        } else if ($user) {
            if (@$_SESSION['checksum'] !== $this->getChecksum()) {
                $this->logout();
            }
        }
        return parent::render($request);
    }

    /**
     * Calculates a checksum for the admin session. Detects, if the .htpasswd was changed, the IP-Address or
     * the user agent of the user.
     * @return String md5 checksum
     */
    private function getChecksum(): string
    {
        return md5($this->htpasswd->getChecksum() . $_SERVER['REMOTE_ADDR'] . $_SERVER['HTTP_ACCEPT_LANGUAGE']);
    }

    public function getDefaultSite(): Site
    {
        return new Site($this->reboot, "default", "");
    }

    public function login($username, $password): bool
    {
        if ($this->htpasswd->validate($username, $password)) {
            $_SESSION['user'] = $username;
            $_SESSION['checksum'] = $this->getChecksum();
            return true;
        }
        return false;
    }

    public function logout()
    {
        Logger::info("logout " . $this->getUser());
        $_SESSION['user'] = null;
        $_SESSION['checksum'] = null;
        $this->reboot->redirect($this->reboot->getBaseWebPath() . "/admin");
    }

    /**
     * @return mixed|null Returns the username, if logged in or null if not
     */
    public function getUser()
    {
        return @$_SESSION['user'];
    }
}