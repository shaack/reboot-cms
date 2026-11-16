<?php

namespace Shaack\Tests;

require_once __DIR__ . "/../core/addons/Lightbox/Lightbox.php";

use Shaack\Reboot\AddOns\Lightbox;

class LightboxTest
{
    public function testPageWithoutLightboxLinkStaysUnchanged(): void
    {
        $html = '<html><body><a href="/media/a.jpg"><img src="/media/a.jpg"></a></body></html>';
        Assert::equals($html, Lightbox::enhancePage($html));
    }

    public function testAssetsGoBeforeTheEndOfTheBody(): void
    {
        $html = '<html><body><a href="/media/a.jpg" data-lightbox="g"><img src="/media/a.jpg"></a></body></html>';
        $result = Lightbox::enhancePage($html);
        Assert::contains('<style>', $result);
        Assert::contains('a[data-lightbox]', $result);
        Assert::true(str_ends_with($result, "</script>\n</body></html>"), "style and script before </body>");
    }

    public function testAttributeAnywhereInTheLink(): void
    {
        $html = '<a class="card" href="/x.png" data-lightbox="gallery" data-caption="X"><img src="/x.png"></a>';
        Assert::contains('<script>', Lightbox::enhancePage($html));
    }

    public function testAttributeOutsideALinkDoesNotCount(): void
    {
        $html = '<body><div data-lightbox="g"></div><p>data-lightbox</p></body>';
        Assert::equals($html, Lightbox::enhancePage($html));
    }

    public function testWithoutBodyTheAssetsAreAppended(): void
    {
        $result = Lightbox::enhancePage('<a href="/x.jpg" data-lightbox="g"><img></a>');
        Assert::true(str_starts_with($result, '<a href="/x.jpg"'));
        Assert::true(str_ends_with($result, "</script>\n"));
    }
}
