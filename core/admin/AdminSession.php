<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

namespace Shaack\Reboot;

require __DIR__ . "/../utils/Htpasswd.php";

/**
 * Class AdminSession
 * @package Shaack\Reboot
 *
 * Set the backend passwords in /local/.htpasswd
 */
class AdminSession
{
    private $reboot;
    private $htpasswd;

    /**
     * @param Reboot $reboot
     */
    public function __construct($reboot)
    {
        session_start();
        $this->reboot = $reboot;
        $this->htpasswd = new Htpasswd($this->reboot->baseDir . "/../../local/.htpasswd");
        $user = $this->getUser();
        if (!$user && $reboot->route !== "/login") {
            $this->reboot->redirect($this->reboot->config["adminPath"] . "/login");
        } else if ($user) {
            if (@$_SESSION['checksum'] !== $this->getChecksum()) {
                $this->logout();
            }
        }
    }

    /**
     * Calculates a checksum for the admin session. Detects, if the .htpasswd was changed, the IP-Address or
     * the user agent of the user.
     * @return String md5 checksum
     */
    private function getChecksum()
    {
        return md5($this->htpasswd->getChecksum() . $_SERVER['REMOTE_ADDR'] . $_SERVER['HTTP_USER_AGENT']);
    }

    /**
     * @param String $username
     * @param String $password
     * @return bool true, if login is valid
     */
    public function login($username, $password)
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
        $_SESSION['user'] = null;
        $_SESSION['checksum'] = null;
        $this->reboot->redirect($this->reboot->config["adminPath"] . "/");
    }

    /**
     * @return mixed|null Returns the username, if logged in or null if not
     */
    public function getUser()
    {
        return $_SESSION['user'];
    }
}