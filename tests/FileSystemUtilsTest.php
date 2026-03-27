<?php

namespace Shaack\Tests;

use Shaack\Utils\FileSystemUtils;

class FileSystemUtilsTest
{
    private string $tmpDir;

    public function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . "/test_fsutils_" . uniqid();
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
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }
        rmdir($dir);
    }

    public function testListFiles(): void
    {
        file_put_contents($this->tmpDir . "/file1.txt", "hello");
        file_put_contents($this->tmpDir . "/file2.txt", "world");
        $list = FileSystemUtils::getFileList($this->tmpDir);
        Assert::equals(2, count($list));
    }

    public function testListFilesHiddenSkipped(): void
    {
        file_put_contents($this->tmpDir . "/visible.txt", "yes");
        file_put_contents($this->tmpDir . "/.hidden", "no");
        $list = FileSystemUtils::getFileList($this->tmpDir);
        Assert::equals(1, count($list));
        Assert::contains("visible.txt", $list[0]['name']);
    }

    public function testListFilesWithSubdir(): void
    {
        file_put_contents($this->tmpDir . "/file.txt", "hello");
        mkdir($this->tmpDir . "/subdir", 0755);
        $list = FileSystemUtils::getFileList($this->tmpDir);
        Assert::equals(2, count($list)); // file + dir entry
    }

    public function testListFilesRecursive(): void
    {
        file_put_contents($this->tmpDir . "/top.txt", "top");
        mkdir($this->tmpDir . "/sub", 0755);
        file_put_contents($this->tmpDir . "/sub/nested.txt", "nested");
        $list = FileSystemUtils::getFileList($this->tmpDir, true);
        // Should include: top.txt, sub/ dir entry, sub/nested.txt
        Assert::true(count($list) >= 3);
    }

    public function testListFilesRecursiveWithDepth(): void
    {
        mkdir($this->tmpDir . "/a", 0755);
        mkdir($this->tmpDir . "/a/b", 0755);
        file_put_contents($this->tmpDir . "/a/b/deep.txt", "deep");
        // depth=0 means no recursion into subdirectories
        $list = FileSystemUtils::getFileList($this->tmpDir, true, 0);
        $names = array_column($list, 'name');
        $hasDeep = false;
        foreach ($names as $name) {
            if (str_contains($name, "deep.txt")) {
                $hasDeep = true;
            }
        }
        Assert::false($hasDeep, "Should not recurse beyond depth 0");
    }

    public function testFileEntryHasProperties(): void
    {
        file_put_contents($this->tmpDir . "/test.txt", "content");
        $list = FileSystemUtils::getFileList($this->tmpDir);
        Assert::equals(1, count($list));
        Assert::true(array_key_exists('name', $list[0]));
        Assert::true(array_key_exists('type', $list[0]));
        Assert::true(array_key_exists('size', $list[0]));
        Assert::true(array_key_exists('lastmod', $list[0]));
        Assert::equals(7, $list[0]['size']); // "content" = 7 bytes
    }

    public function testDirTrailingSlashHandled(): void
    {
        file_put_contents($this->tmpDir . "/file.txt", "data");
        // With trailing slash
        $list1 = FileSystemUtils::getFileList($this->tmpDir . "/");
        // Without trailing slash
        $list2 = FileSystemUtils::getFileList($this->tmpDir);
        Assert::equals(count($list1), count($list2));
    }
}
