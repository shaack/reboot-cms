<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

namespace Shaack\Reboot;

require __DIR__ . "/../utils/Htpasswd.php";

class AdminSession
{
    private $reboot;

    /**
     * @param Reboot $reboot
     */
    public function __construct($reboot)
    {
        session_start();
        $this->reboot = $reboot;
        if (!$this->getUser() && $reboot->route !== "/login") {
            $this->reboot->redirect($this->reboot->config["adminPath"] . "/login");
        }
    }

    /**
     * @param String $username
     * @param String $password
     */
    public function login($username, $password)
    {
        $htpasswd = new Htpasswd($this->reboot->baseDir . "/../../local/.htpasswd");
        if ($htpasswd->validate($username, $password)) {
            $_SESSION['user'] = $username;
            $_SESSION['ip'] = $_SERVER['REMOTE_ADDR'];
            return true;
        }
        return false;
    }

    public function logout()
    {
        $_SESSION['user'] = null;
        $_SESSION['ip'] = null;
    }

    /**
     * @return mixed|null Returns the username, if logged in or null if not
     */
    public function getUser()
    {
        if (@$_SESSION['ip'] == $_SERVER['REMOTE_ADDR']) {
            return @$_SESSION['user'];
        } else {
            return null;
        }
    }
}