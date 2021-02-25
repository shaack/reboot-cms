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
     * AdminSession constructor.
     * @param Reboot $reboot
     */
    public function __construct($reboot)
    {
        $this->reboot = $reboot;
    }

    function login($username, $password) {
        $htpasswd = new Htpasswd($this->reboot->baseDir . "/local/.htpasswd");
        if($htpasswd->validate($username, $password)) {
            session_start();
            $_SESSION['user'] = $username;
            $_SESSION['ip'] = $_SERVER['REMOTE_ADDR'];
        }
    }

    function logout() {
        $_SESSION['user'] = null;
        $_SESSION['ip'] = null;
    }

    function getUser() {
        if($_SESSION['ip'] == $_SERVER['REMOTE_ADDR']) {
            return $_SESSION['user'];
        } else {
            return null;
        }
    }
}