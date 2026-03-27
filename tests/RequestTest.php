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
}
