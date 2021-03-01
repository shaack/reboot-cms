<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

namespace Shaack\Reboot;

use WhiteHat101\Crypt\APR1_MD5;

/**
 * Class Htpasswd
 * @package Shaack\Reboot
 *
 * Uses https://github.com/whitehat101/apr1-md5
 */
class Htpasswd
{
    private $htpasswdUsers = array();

    public function __construct($filePath)
    {
        $this->parseHtpasswd($filePath);
    }

    private function parseHtpasswd($filePath)
    {
        $lines = file($filePath);
        foreach ($lines as $lineNum => $line) {
            $line = trim($line);
            if (strpos($line, "#") === false) {
                $exploded = explode(":", $line);
                $this->htpasswdUsers[$exploded[0]] = $exploded[1];
            }
        }
    }

    function validate($username, $password)
    {
        foreach ($this->htpasswdUsers as $user => $passwordApr1) {
            if($user === $username) {
                return APR1_MD5::check($password, $passwordApr1);
            }
        }
        return false;
    }
}