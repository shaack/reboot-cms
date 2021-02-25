<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

namespace Shaack\Reboot;

class AdminSession
{
    private $reboot;

    /**
     * @param Reboot $reboot
     */
    public function __construct($reboot)
    {
        $this->reboot = $reboot;
        if(!$this->getUser() && $reboot->route !== "/login") {
            $this->reboot->redirect($this->reboot->config["adminPath"] . "/login");
        }
    }

    /**
     * @param String $username
     * @param String $password
     */
    function login($username, $password)
    {
        $htpasswd = new Htpasswd($this->reboot->baseDir . "/local/.htpasswd");
        if ($htpasswd->validate($username, $password)) {
            session_start();
            $_SESSION['user'] = $username;
            $_SESSION['ip'] = $_SERVER['REMOTE_ADDR'];
        }
    }

    function logout()
    {
        $_SESSION['user'] = null;
        $_SESSION['ip'] = null;
    }

    /**
     * @return mixed|null Returns the username, if logged in or null if not
     */
    function getUser()
    {
        if (@$_SESSION['ip'] == $_SERVER['REMOTE_ADDR']) {
            return @$_SESSION['user'];
        } else {
            return null;
        }
    }
}