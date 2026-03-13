<?php

namespace Shaack\Tests;

use Shaack\Reboot\CsrfProtection;

class CsrfProtectionTest
{
    public function setUp(): void
    {
        $_SESSION = [];
    }

    public function testGetTokenReturnsString(): void
    {
        $token = CsrfProtection::getToken();
        Assert::true(is_string($token));
        Assert::equals(64, strlen($token)); // 32 bytes = 64 hex chars
    }

    public function testGetTokenReturnsSameTokenPerSession(): void
    {
        $token1 = CsrfProtection::getToken();
        $token2 = CsrfProtection::getToken();
        Assert::equals($token1, $token2);
    }

    public function testGetTokenRegeneratesAfterClear(): void
    {
        $token1 = CsrfProtection::getToken();
        $_SESSION['csrf_token'] = '';
        $token2 = CsrfProtection::getToken();
        Assert::true($token1 !== $token2, "Token should regenerate after being cleared");
    }
}
