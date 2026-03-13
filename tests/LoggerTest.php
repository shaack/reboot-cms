<?php

namespace Shaack\Tests;

use Shaack\Logger;

class LoggerTest
{
    public function testSetAndGetLevel(): void
    {
        Logger::setLevel(0);
        Assert::equals(0, Logger::getLevel());
        Logger::setLevel(2);
        Assert::equals(2, Logger::getLevel());
        // Reset
        Logger::setLevel(1);
    }
}
