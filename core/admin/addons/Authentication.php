<?php

namespace Shaack\Reboot;

use Shaack\Htpasswd;
use Shaack\Logger;

class Authentication extends AddOn
{
    private Htpasswd $htpasswd;

    /**
     * @see AddOn::init()
     */
    protected function init() {
        session_start();
        $this->htpasswd = new Htpasswd($this->reboot->getBaseFsPath() . "/local/.htpasswd");
    }

    /**
     * @see AddOn::preRender()
     */
    public function preRender(Request $request): bool
    {
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