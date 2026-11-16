<?php

namespace Shaack\Tests;

require_once __DIR__ . "/TestHelper.php";
require_once __DIR__ . "/../core/addons/LanguageRedirect/LanguageRedirect.php";

use Shaack\Reboot\AddOns\LanguageRedirect;
use Shaack\Reboot\Request;

/**
 * Permanent redirects from `redirects` in `site/config.yml`.
 */
class RedirectsTest
{
    private function target(string $uri, array $redirects, string $webPath = ""): ?string
    {
        $site = TestHelper::createSiteStub("/tmp/site", $webPath, ["redirects" => $redirects]);
        return $site->redirectTarget(new Request($site, "", $uri, []));
    }

    public function testExactPath(): void
    {
        Assert::equals("/products/microtask", $this->target("/microtask", ["/microtask" => "/products/microtask"]));
    }

    public function testTrailingSlashOnBothSides(): void
    {
        Assert::equals("/products/microtask", $this->target("/microtask/", ["/microtask/" => "/products/microtask"]));
        Assert::equals("/products/microtask", $this->target("/microtask/", ["/microtask" => "/products/microtask"]));
    }

    public function testFileName(): void
    {
        Assert::equals("/products/microtask/privacy-policy",
            $this->target("/microtask/privacy-policy.html", ["/microtask/privacy-policy.html" => "/products/microtask/privacy-policy"]));
    }

    public function testNoPrefixMatch(): void
    {
        Assert::equals(null, $this->target("/microtask/other", ["/microtask" => "/products/microtask"]));
        Assert::equals(null, $this->target("/microtasks", ["/microtask" => "/products/microtask"]));
    }

    public function testNoRedirectsConfigured(): void
    {
        $site = TestHelper::createSiteStub("/tmp/site", "", []);
        Assert::equals(null, $site->redirectTarget(new Request($site, "", "/microtask", [])));
    }

    public function testQueryIsKept(): void
    {
        Assert::equals("/products/microtask?mtm_campaign=flyer",
            $this->target("/microtask?mtm_campaign=flyer", ["/microtask" => "/products/microtask"]));
    }

    public function testFullUrl(): void
    {
        Assert::equals("https://example.com/app", $this->target("/app", ["/app" => "https://example.com/app"]));
    }

    public function testSiteWebPath(): void
    {
        Assert::equals("/site/products/microtask", $this->target("/site/microtask", ["/microtask" => "/products/microtask"], "/site"));
    }

    public function testLanguagePrefixNeedsOwnEntry(): void
    {
        Assert::equals(null, $this->target("/de/microtask", ["/microtask" => "/products/microtask"]));
        Assert::equals("/de/products/microtask", $this->target("/de/microtask", ["/de/microtask" => "/de/products/microtask"]));
    }

    public function testRedirectToItselfIsIgnored(): void
    {
        Assert::equals(null, $this->target("/microtask", ["/microtask" => "/microtask"]));
    }

    private function localized(string $uri, array $redirects, ?string $acceptLanguage, ?string $cookie = null): ?string
    {
        $site = TestHelper::createSiteStub("/tmp/site", "", ["languages" => ["en", "de"], "defaultLanguage" => "en", "redirects" => $redirects]);
        $ref = new \ReflectionClass(LanguageRedirect::class);
        $addOn = $ref->newInstanceWithoutConstructor();
        $ref->getParentClass()->getProperty('site')->setValue($addOn, $site);
        $server = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? null;
        $cookies = $_COOKIE;
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = $acceptLanguage ?? '';
        $_COOKIE = $cookie === null ? [] : ['lang' => $cookie];
        try {
            return $addOn->localizedRedirect(new Request($site, "", $uri, []));
        } finally {
            $_SERVER['HTTP_ACCEPT_LANGUAGE'] = $server;
            $_COOKIE = $cookies;
        }
    }

    public function testGermanBrowserGetsGermanPage(): void
    {
        Assert::equals("/de/products/microtask", $this->localized("/microtask", ["/microtask" => "/products/microtask"], "de-DE,de;q=0.9,en;q=0.8"));
    }

    public function testEnglishBrowserGetsPlainRedirect(): void
    {
        Assert::equals(null, $this->localized("/microtask", ["/microtask" => "/products/microtask"], "en-US,en;q=0.9"));
        Assert::equals(null, $this->localized("/microtask", ["/microtask" => "/products/microtask"], "fr-FR"));
        Assert::equals(null, $this->localized("/microtask", ["/microtask" => "/products/microtask"], null));
    }

    public function testStoredChoiceWins(): void
    {
        Assert::equals(null, $this->localized("/microtask", ["/microtask" => "/products/microtask"], "de-DE", "en"));
        Assert::equals("/de/products/microtask", $this->localized("/microtask", ["/microtask" => "/products/microtask"], "en-US", "de"));
    }

    public function testQueryIsKeptInLanguage(): void
    {
        Assert::equals("/de/products/microtask?mtm_campaign=flyer",
            $this->localized("/microtask?mtm_campaign=flyer", ["/microtask" => "/products/microtask"], "de"));
    }

    public function testTargetWithLanguageOrFullUrlIsLeftAlone(): void
    {
        Assert::equals(null, $this->localized("/microtask", ["/microtask" => "/en/products/microtask"], "de"));
        Assert::equals(null, $this->localized("/app", ["/app" => "https://example.com/app"], "de"));
    }

    public function testOldPathWithLanguageIsLeftAlone(): void
    {
        Assert::equals(null, $this->localized("/de/microtask", ["/de/microtask" => "/products/microtask"], "de"));
    }

    public function testNoRedirectNoLocalizedRedirect(): void
    {
        Assert::equals(null, $this->localized("/other", ["/microtask" => "/products/microtask"], "de"));
    }
}
