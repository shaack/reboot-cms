<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

namespace Shaack\Reboot\AddOns\ProtectedAreas;

/**
 * The LoginThrottle of the core, with the attempts in
 * `local/protected-areas/`.
 */
class LoginThrottle extends \Shaack\Reboot\LoginThrottle
{
    /**
     * @param string $fileName the counter file in `local/protected-areas/`, one per kind of attempt
     */
    public function __construct(string $baseFsPath, string $fileName = 'login-attempts.json')
    {
        parent::__construct(rtrim($baseFsPath, '/') . '/local/protected-areas/' . $fileName);
    }
}
