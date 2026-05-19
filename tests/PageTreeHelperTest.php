<?php

namespace Shaack\Tests;

use Shaack\Reboot\Admin\PageTreeHelper;

class PageTreeHelperTest
{
    private string $tmpDir;

    public function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . "/test_pagetree_" . uniqid();
        mkdir($this->tmpDir, 0755, true);
    }

    public function tearDown(): void
    {
        if (is_dir($this->tmpDir)) {
            $this->removeDir($this->tmpDir);
        }
    }

    private function removeDir(string $dir): void
    {
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($dir);
    }

    public function testCollectFoldersEmptyDir(): void
    {
        $folders = PageTreeHelper::collectFolders($this->tmpDir);
        Assert::equals(["/"], $folders);
    }

    public function testCollectFoldersReturnsEachFolderOnce(): void
    {
        mkdir($this->tmpDir . "/_archive");
        mkdir($this->tmpDir . "/articles");
        mkdir($this->tmpDir . "/howto");
        mkdir($this->tmpDir . "/howto/Archive");

        $folders = PageTreeHelper::collectFolders($this->tmpDir);

        // every folder must appear exactly once (regression test for duplicate entries)
        Assert::equals(
            ["/", "/_archive", "/articles", "/howto", "/howto/Archive"],
            $folders
        );
        Assert::equals(count($folders), count(array_unique($folders)));
    }
}
