<?php

namespace Shaack\Tests;

use Shaack\Utils\HttpUtils;

class HttpUtilsTest
{
    public function testSanitizeFileName(): void
    {
        Assert::equals("hello", HttpUtils::sanitizeFileName("hello"));
        Assert::equals("hello-world", HttpUtils::sanitizeFileName("hello-world"));
        Assert::equals("hello_world", HttpUtils::sanitizeFileName("hello_world"));
        Assert::equals("helloworld", HttpUtils::sanitizeFileName("hello world"));
        Assert::equals("helloworld", HttpUtils::sanitizeFileName("hello/world"));
        Assert::equals("helloworld", HttpUtils::sanitizeFileName("hello..world"));
        Assert::equals("test123", HttpUtils::sanitizeFileName("test123"));
    }

    public function testSanitizeFileNameSpecialChars(): void
    {
        Assert::equals("", HttpUtils::sanitizeFileName("../../"));
        Assert::equals("script", HttpUtils::sanitizeFileName("<script>"));
        Assert::equals("file", HttpUtils::sanitizeFileName("file!@#$%"));
    }
}
