<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

namespace Shaack\Utils;

class Logger
{
    private static $active;

    static function setActive($active) {
        Logger::$active = $active;
    }

    /**
     * @param string $message
     */
    static function log(string $message)
    {
        if (Logger::$active) {
            if (is_string($message)) {
                error_log($message);
            } else {
                error_log(print_r($message, true));
            }
        }
    }
}