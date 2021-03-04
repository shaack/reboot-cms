<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

namespace Shaack\Reboot;

class Logger
{
    public function __construct($active)
    {
        global $loggerActive;
        $loggerActive = $active;

        /**
         * @param string $message
         */
        function log($message)
        {
            global $loggerActive;
            if ($loggerActive) {
                if (is_string($message)) {
                    error_log($message);
                } else {
                    error_log(print_r($message, true));
                }
            }
        }
    }
}