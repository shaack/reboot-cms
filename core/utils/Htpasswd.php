<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

namespace Shaack\Reboot;

class Htpasswd
{
    public function __construct($filePath)
    {
        $this->parseHtpasswd($filePath);
    }

    private function parseHtpasswd($filePath) {
        $lines = file($filePath);
        foreach ($lines as $lineNum => $line) {
            echo "Line #<b>{$lineNum}</b> : " . htmlspecialchars($line) . "<br>\n";
        }
    }

    function validate($username, $password) {

    }
}