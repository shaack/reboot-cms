<?php

namespace Shaack\Tests;

require_once __DIR__ . "/TestHelper.php";

use Shaack\Reboot\AddOns\Admin\PageActionHandler;
use Shaack\Reboot\Request;

class PageActionHandlerTest
{
    private string $tmpDir;
    private string $pagesDir;
    private PageActionHandler $handler;

    public function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . "/test_pageactions_" . uniqid();
        mkdir($this->tmpDir . "/pages/folder", 0755, true);
        mkdir($this->tmpDir . "/pages-old", 0755, true);
        file_put_contents($this->tmpDir . "/pages/folder/page.md", "x");
        file_put_contents($this->tmpDir . "/pages-old/keep.md", "keep");
        $this->pagesDir = realpath($this->tmpDir . "/pages");
        $this->handler = new PageActionHandler($this->pagesDir, $this->tmpDir . "/history", 10);
    }

    public function tearDown(): void
    {
        if (is_dir($this->tmpDir)) {
            $items = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($this->tmpDir, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($items as $item) {
                $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
            }
            rmdir($this->tmpDir);
        }
    }

    private function request(array $post): Request
    {
        return new Request(TestHelper::createSiteStub($this->tmpDir), "", "/pages", $post);
    }

    public function testRenameFolder(): void
    {
        $this->handler->handle("rename_folder", "folder", $this->request(["new_name" => "renamed"]));
        Assert::true(is_file($this->pagesDir . "/renamed/page.md"));
    }

    public function testCannotRenameThePagesRoot(): void
    {
        foreach (["", "/", "."] as $name) {
            Assert::throws(\InvalidArgumentException::class, function () use ($name) {
                $this->handler->handle("rename_folder", $name, $this->request(["new_name" => "gone"]));
            }, "name '$name'");
        }
        Assert::true(is_dir($this->pagesDir), "site/pages is still there");
    }

    public function testCannotDeleteInSiblingFolderWithSamePrefix(): void
    {
        Assert::throws(\InvalidArgumentException::class, function () {
            $this->handler->handle("delete_page", "-old/keep.md", $this->request([]));
        });
        Assert::true(is_file($this->tmpDir . "/pages-old/keep.md"));
    }
}
