<?php

namespace Shaack\Tests;

require_once __DIR__ . "/TestHelper.php";

use Shaack\Reboot\Request;

class RequestTest
{
    public function testSimplePath(): void
    {
        $site = TestHelper::createSiteStub("/tmp/site", "");
        $request = new Request($site, "", "/about", []);
        Assert::equals("/about", $request->getPath());
    }

    public function testRootPath(): void
    {
        $site = TestHelper::createSiteStub("/tmp/site", "");
        $request = new Request($site, "", "/", []);
        Assert::equals("/", $request->getPath());
    }

    public function testTrailingSlashStripped(): void
    {
        $site = TestHelper::createSiteStub("/tmp/site", "");
        $request = new Request($site, "", "/about/", []);
        Assert::equals("/about", $request->getPath());
    }

    public function testBaseWebPathStripped(): void
    {
        $site = TestHelper::createSiteStub("/tmp/site", "");
        $request = new Request($site, "/myapp", "/myapp/about", []);
        Assert::equals("/about", $request->getPath());
    }

    public function testBaseWebPathRoot(): void
    {
        $site = TestHelper::createSiteStub("/tmp/site", "");
        $request = new Request($site, "/myapp", "/myapp/", []);
        Assert::equals("/", $request->getPath());
    }

    public function testSiteWebPathStripped(): void
    {
        $site = TestHelper::createSiteStub("/tmp/site", "/blog");
        $request = new Request($site, "", "/blog/post", []);
        Assert::equals("/post", $request->getPath());
    }

    public function testQueryParamsGet(): void
    {
        $site = TestHelper::createSiteStub("/tmp/site", "");
        $request = new Request($site, "", "/search?q=hello&page=2", []);
        Assert::equals("/search", $request->getPath());
        Assert::equals("hello", $request->getParam("q"));
        Assert::equals("2", $request->getParam("page"));
        Assert::equals("hello", $request->getParam("q", "get"));
        Assert::null($request->getParam("q", "post"));
    }

    public function testPostParams(): void
    {
        $site = TestHelper::createSiteStub("/tmp/site", "");
        $request = new Request($site, "", "/form", ["name" => "Alice", "email" => "a@b.com"]);
        Assert::equals("Alice", $request->getParam("name"));
        Assert::equals("Alice", $request->getParam("name", "post"));
        Assert::null($request->getParam("name", "get"));
    }

    public function testMissingParam(): void
    {
        $site = TestHelper::createSiteStub("/tmp/site", "");
        $request = new Request($site, "", "/page", []);
        Assert::null($request->getParam("nonexistent"));
        Assert::null($request->getParam("nonexistent", "get"));
        Assert::null($request->getParam("nonexistent", "post"));
    }

    public function testPostParamsPriorityOverGet(): void
    {
        $site = TestHelper::createSiteStub("/tmp/site", "");
        $request = new Request($site, "", "/page?key=fromget", ["key" => "frompost"]);
        // When method is null, post is checked first
        Assert::equals("frompost", $request->getParam("key"));
        Assert::equals("fromget", $request->getParam("key", "get"));
        Assert::equals("frompost", $request->getParam("key", "post"));
    }

    public function testToString(): void
    {
        $site = TestHelper::createSiteStub("/tmp/site", "");
        $request = new Request($site, "", "/about", []);
        Assert::contains("/about", (string)$request);
    }

    public function testNestedPath(): void
    {
        $site = TestHelper::createSiteStub("/tmp/site", "");
        $request = new Request($site, "", "/docs/api/reference", []);
        Assert::equals("/docs/api/reference", $request->getPath());
    }

    public function testLanguageDefaultsToEnWhenNotConfigured(): void
    {
        $site = TestHelper::createSiteStub("/tmp/site", "");
        $request = new Request($site, "", "/about", []);
        Assert::equals("en", $request->getLanguage());
        Assert::equals("/about", $request->getPathWithoutLanguage());
    }

    public function testLanguageFromPathPrefix(): void
    {
        $site = TestHelper::createSiteStub("/tmp/site", "", ["languages" => ["en", "de"]]);
        $request = new Request($site, "", "/de/contact", []);
        Assert::equals("de", $request->getLanguage());
        Assert::equals("/de/contact", $request->getPath());
        Assert::equals("/contact", $request->getPathWithoutLanguage());
    }

    public function testUnprefixedPathUsesDefaultLanguage(): void
    {
        $site = TestHelper::createSiteStub("/tmp/site", "", ["languages" => ["en", "de"]]);
        $request = new Request($site, "", "/contact", []);
        Assert::equals("en", $request->getLanguage());
        Assert::equals("/contact", $request->getPathWithoutLanguage());
    }

    public function testExplicitDefaultLanguage(): void
    {
        $site = TestHelper::createSiteStub("/tmp/site", "", ["languages" => ["en", "de"], "defaultLanguage" => "de"]);
        $request = new Request($site, "", "/about", []);
        Assert::equals("de", $request->getLanguage());
    }

    public function testLanguagePrefixAtRoot(): void
    {
        $site = TestHelper::createSiteStub("/tmp/site", "", ["languages" => ["en", "de"]]);
        $request = new Request($site, "", "/de", []);
        Assert::equals("de", $request->getLanguage());
        Assert::equals("/", $request->getPathWithoutLanguage());
    }

    public function testUnknownSegmentIsNotTreatedAsLanguage(): void
    {
        $site = TestHelper::createSiteStub("/tmp/site", "", ["languages" => ["en", "de"]]);
        $request = new Request($site, "", "/fr/contact", []);
        Assert::equals("en", $request->getLanguage());
        Assert::equals("/fr/contact", $request->getPathWithoutLanguage());
    }

    // --- Canonical paths, see Request::canonicalizePath() ---

    public function testDotSegmentsResolved(): void
    {
        $site = TestHelper::createSiteStub("/tmp/site", "");
        Assert::equals("/customers", (new Request($site, "", "/./customers", []))->getPath());
        Assert::equals("/customers", (new Request($site, "", "/blog/../customers", []))->getPath());
        Assert::equals("/customers/beta", (new Request($site, "", "/customers/acme/../beta/", []))->getPath());
        Assert::equals("/etc/passwd", (new Request($site, "", "/../../etc/passwd", []))->getPath(), ".. never leaves the root");
    }

    public function testRepeatedSlashesCollapsed(): void
    {
        $site = TestHelper::createSiteStub("/tmp/site", "");
        Assert::equals("/", (new Request($site, "", "///", []))->getPath());
        Assert::equals("/customers", (new Request($site, "", "/.//customers", []))->getPath());
        Assert::equals("/evil.com/x", (new Request($site, "", "//evil.com/x", []))->getPath(), "no host is parsed from the path");
    }

    public function testPercentEncodingDecoded(): void
    {
        $site = TestHelper::createSiteStub("/tmp/site", "");
        Assert::equals("/über", (new Request($site, "", "/%C3%BCber", []))->getPath());
        Assert::equals("/customers", (new Request($site, "", "/%2e/customers", []))->getPath());
        Assert::equals("/customers", (new Request($site, "", "/blog/%2e%2e/customers", []))->getPath());
        $request = new Request($site, "", "/search?q=a%20b", []);
        Assert::equals("/search", $request->getPath());
        Assert::equals("a b", $request->getParam("q"));
    }

    public function testControlCharactersAndBackslashInvalid(): void
    {
        $site = TestHelper::createSiteStub("/tmp/site", "");
        Assert::true((new Request($site, "", "/about", []))->isValidPath());
        Assert::false((new Request($site, "", "/about%00.md", []))->isValidPath());
        Assert::false((new Request($site, "", "/a%5C..%5Cb", []))->isValidPath());
        Assert::false((new Request($site, "", "/a%0Ab", []))->isValidPath());
    }
}
