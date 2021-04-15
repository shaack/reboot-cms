<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

namespace Shaack\Utils;

class Logger
{
    private static $level = 1;

    static function setLevel($level) {
        Logger::$level = $level;
    }

    /**
     * @param string $message
     * @param int $level
     */
    static private function log(string $message, int $level = 1)
    {
        if (Logger::$level <= $level) {
            if (is_string($message)) {
                error_log($message);
            } else {
                error_log(print_r($message, true));
            }
        }
    }
    static function debug(string $message) {
        Logger::log("DEBUG: " . $message, 0);
    }
    static function info(string $message) {
        Logger::log("INFO:  " . $message, 1);
    }
    static function error(string $message) {
        Logger::log("ERROR: " . $message, 2);
    }
    static function tmp(string $message) {
        Logger::log("TMP:   " . $message, 3);
    }
}