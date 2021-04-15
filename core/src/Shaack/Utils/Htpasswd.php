<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

namespace Shaack\Utils;

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
    private $checksum;

    public function __construct($filePath)
    {
        $this->parseHtpasswd($filePath);
    }

    private function parseHtpasswd($filePath)
    {
        Logger::debug("parseHtpasswd " . $filePath);
        $lines = file($filePath);
        $checksum = "";
        foreach ($lines as $lineNum => $line) {
            $line = trim($line);
            if (strpos($line, "#") === false) {
                $exploded = explode(":", $line);
                $username = trim($exploded[0]);
                if($username) {
                    $apr1Password = trim($exploded[1]);
                    if ($apr1Password) {
                        $this->htpasswdUsers[$username] = $apr1Password;
                        $checksum = md5($checksum . $line);
                    }
                }
            }
        }
        $this->checksum = $checksum;
    }

    function getChecksum()
    {
        return $this->checksum;
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