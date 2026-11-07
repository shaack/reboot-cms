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

    public function testHistoryOfAnAddressHoldsBothKindsOfPage(): void
    {
        $history = $this->tmpDir . "/history";
        file_put_contents($this->pagesDir . "/about.md", "# Markdown");
        \Shaack\Reboot\AddOns\Admin\PageHistoryHelper::savePageSnapshot($this->pagesDir . "/about.md", $this->pagesDir, $history, 10);
        rename(glob($history . "/about/*.md")[0], $history . "/about/2026-01-01_10-00-00.md");
        unlink($this->pagesDir . "/about.md");
        file_put_contents($this->pagesDir . "/about.yml", "title: Form\n");
        \Shaack\Reboot\AddOns\Admin\PageHistoryHelper::savePageSnapshot($this->pagesDir . "/about.yml", $this->pagesDir, $history, 10);
        $versions = \Shaack\Reboot\AddOns\Admin\PageHistoryHelper::getPageHistory("/about.yml", $this->pagesDir, $history);
        Assert::equals(["yml", "md"], array_column($versions, "type"), "newest first, the Markdown version of the converted page included");
        Assert::equals("2026-01-01 10:00:00", $versions[1]["timestamp"]);
    }

    public function testRestoringTheOtherKindTurnsThePageBack(): void
    {
        $history = $this->tmpDir . "/history";
        mkdir($history . "/about", 0755, true);
        file_put_contents($history . "/about/2026-01-01_10-00-00.md", "# Markdown");
        file_put_contents($this->pagesDir . "/about.yml", "title: Form\n");
        $result = $this->handler->handle("restore_page", "/about.yml", $this->request(["version" => "2026-01-01_10-00-00.md"]));
        Assert::equals("/about.md", $result["editPageName"]);
        Assert::equals("# Markdown", file_get_contents($this->pagesDir . "/about.md"));
        Assert::false(file_exists($this->pagesDir . "/about.yml"), "the structured page is replaced");
        Assert::equals(1, count(glob($history . "/about/*.yml")), "and kept in the history");
    }

    public function testRestoringTheSameKindOverwrites(): void
    {
        $history = $this->tmpDir . "/history";
        mkdir($history . "/about", 0755, true);
        file_put_contents($history . "/about/2026-01-01_10-00-00.yml", "title: Old\n");
        file_put_contents($this->pagesDir . "/about.yml", "title: New\n");
        $result = $this->handler->handle("restore_page", "/about.yml", $this->request(["version" => "2026-01-01_10-00-00.yml"]));
        Assert::equals("/about.yml", $result["editPageName"]);
        Assert::equals("title: Old\n", file_get_contents($this->pagesDir . "/about.yml"));
    }
}
