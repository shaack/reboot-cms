<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

namespace Shaack\Utils;

class Logger
{
    private static $level = 1; // 0 = debug, 1 = info, 2 = error

    static function setLevel($level) {
        self::$level = $level;
    }

    static function getLevel() {
        return self::$level;
    }

    /**
     * @param string $message
     * @param int $level
     */
    static private function log(string $message, int $level = 1)
    {
        if (self::$level <= $level) {
            if (is_string($message) || is_numeric($message)) {
                error_log($message);
            } else {
                error_log(print_r($message, true));
            }
        }
    }
    static function debug($message) {
        self::log("DEBUG: " . $message, 0);
    }
    static function info($message) {
        self::log("INFO:  " . $message, 1);
    }
    static function error($message) {
        self::log("ERROR: " . $message, 2);
    }
    static function tmp($message) {
        self::log("TMP:   " . $message, 3);
    }
}