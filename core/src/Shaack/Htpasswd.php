<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

namespace Shaack;

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
    private $filePath;

    public function __construct($filePath)
    {
        $this->filePath = $filePath;
        $this->parseHtpasswd($filePath);
    }

    private function parseHtpasswd($filePath)
    {
        $lines = file($filePath);
        $checksum = "";
        foreach ($lines as $lineNum => $line) {
            $line = trim($line);
            if (strpos($line, "#") === false) {
                $exploded = explode(":", $line);
                $username = trim($exploded[0]);
                if ($username) {
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
            if ($user === $username) {
                return APR1_MD5::check($password, $passwordApr1);
            }
        }
        return false;
    }

    function getUsers(): array
    {
        return array_keys($this->htpasswdUsers);
    }

    function addUser(string $username, string $password): void
    {
        if (isset($this->htpasswdUsers[$username])) {
            throw new \InvalidArgumentException("User '$username' already exists");
        }
        $this->htpasswdUsers[$username] = APR1_MD5::hash($password);
        $this->save();
    }

    function changePassword(string $username, string $password): void
    {
        if (!isset($this->htpasswdUsers[$username])) {
            throw new \InvalidArgumentException("User '$username' does not exist");
        }
        $this->htpasswdUsers[$username] = APR1_MD5::hash($password);
        $this->save();
    }

    function deleteUser(string $username): void
    {
        if (!isset($this->htpasswdUsers[$username])) {
            throw new \InvalidArgumentException("User '$username' does not exist");
        }
        unset($this->htpasswdUsers[$username]);
        $this->save();
    }

    private function save(): void
    {
        $lines = [];
        $checksum = "";
        foreach ($this->htpasswdUsers as $username => $hash) {
            $line = "$username:$hash";
            $lines[] = $line;
            $checksum = md5($checksum . $line);
        }
        file_put_contents($this->filePath, implode("\n", $lines) . "\n");
        $this->checksum = $checksum;
    }
}