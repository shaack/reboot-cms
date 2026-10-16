<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

namespace Shaack\Reboot\AddOns\Authentication;

use Shaack\Reboot\TokenStore;

/**
 * Tokens for "Stay signed in" in the admin, two weeks, one use each. See
 * TokenStore for the format. The file is `local/remember-tokens.json`.
 */
class RememberMeStore extends TokenStore
{
    public const LIFETIME = 14 * 24 * 60 * 60; // two weeks

    public function __construct(string $baseFsPath)
    {
        parent::__construct(rtrim($baseFsPath, '/') . '/local/remember-tokens.json', self::LIFETIME);
    }
}
