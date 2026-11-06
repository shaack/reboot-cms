<?php

namespace Shaack\Tests;

use Shaack\Reboot\AddOns\EmailObfuscation\EmailObfuscator;

class EmailObfuscationTest
{
    public function testObfuscateHtmlEncodesBase64(): void
    {
        $result = EmailObfuscator::obfuscateHtml('info@example.com');
        Assert::contains("atob('" . base64_encode('info@example.com') . "')", $result);
        Assert::contains("TextDecoder", $result, "UTF-8 labels are decoded as UTF-8");
    }

    public function testFindsAddressesInVisibleTextOnly(): void
    {
        $html = '<p>Write to info@example.com or <span title="hidden@example.com">us</span>, info@example.com again.</p>';
        Assert::equals(['info@example.com'], EmailObfuscator::findEmailAddresses($html));
    }

    public function testMailtoLinkIsReplacedAsAWhole(): void
    {
        $html = '<p><a class="mail" href="mailto:info@example.com">Mail us</a></p>';
        $result = EmailObfuscator::obfuscatePage($html);
        Assert::false(str_contains($result, 'info@example.com'), 'address is gone');
        Assert::false(str_contains($result, 'Mail us'), 'label is hidden with the link');
        Assert::contains(base64_encode('<a href="mailto:info@example.com">Mail us</a>'), $result);
        Assert::true(str_starts_with($result, '<p><script>') && str_ends_with($result, '</script></p>'));
    }

    public function testPlainTextAddressIsReplaced(): void
    {
        $result = EmailObfuscator::obfuscatePage('<p>Contact: info@example.com</p>');
        Assert::equals('<p>Contact: ' . EmailObfuscator::obfuscateHtml('info@example.com') . '</p>', $result);
    }

    public function testLinkLabelIsNotObfuscatedTwice(): void
    {
        $html = '<a href="mailto:info@example.com">info@example.com</a>';
        $result = EmailObfuscator::obfuscatePage($html);
        Assert::equals(1, substr_count($result, '<script>'));
        Assert::equals(EmailObfuscator::obfuscateHtml('<a href="mailto:info@example.com">info@example.com</a>'), $result);
    }

    public function testPageWithoutAddressesIsUnchanged(): void
    {
        $html = '<html><body><p>Nothing to see @ here.</p></body></html>';
        Assert::equals($html, EmailObfuscator::obfuscatePage($html));
    }

    public function testAddressesOutsideVisibleTextStay(): void
    {
        $html = '<html><head><title>info@example.com</title><meta name="author" content="info@example.com">'
            . '<script>var a = "info@example.com";</script></head><body><p>info@example.com</p></body></html>';
        $result = EmailObfuscator::obfuscatePage($html);
        Assert::contains('<title>info@example.com</title>', $result);
        Assert::contains('content="info@example.com"', $result);
        Assert::contains('var a = "info@example.com";', $result);
        Assert::contains('<p>' . EmailObfuscator::obfuscateHtml('info@example.com') . '</p>', $result);
    }

    public function testTextAroundTagsDoesNotRunTogether(): void
    {
        Assert::equals(['x@b.de'], EmailObfuscator::findEmailAddresses('<p>x@b.de</p><p>document.write</p>'));
        $result = EmailObfuscator::obfuscatePage('<p>x@b.de</p><p>document.write</p>');
        Assert::false(str_contains($result, 'x@b.de'));
    }
}
