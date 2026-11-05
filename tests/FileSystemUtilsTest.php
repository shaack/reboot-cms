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

    public function testIsInside(): void
    {
        Assert::true(FileSystemUtils::isInside("/site/pages", "/site/pages"));
        Assert::true(FileSystemUtils::isInside("/site/pages/a/b.md", "/site/pages"));
        Assert::true(FileSystemUtils::isInside("/site/pages/a", "/site/pages/"));
        Assert::false(FileSystemUtils::isInside("/site/pages-old/a.md", "/site/pages"), "a sibling with the same prefix");
        Assert::false(FileSystemUtils::isInside("/site", "/site/pages"));
        Assert::false(FileSystemUtils::isInside(false, "/site/pages"));
        Assert::false(FileSystemUtils::isInside("/site/pages", false));
    }

    public function testWriteAtomic(): void
    {
        $file = $this->tmpDir . "/data.txt";
        FileSystemUtils::writeAtomic($file, "one");
        Assert::equals("one", file_get_contents($file));
        chmod($file, 0640);
        FileSystemUtils::writeAtomic($file, "two");
        Assert::equals("two", file_get_contents($file));
        Assert::equals(0640, fileperms($file) & 0777, "an existing file keeps its permissions");
        FileSystemUtils::writeAtomic($file, "three", 0600);
        Assert::equals(0600, fileperms($file) & 0777);
        Assert::equals(["data.txt"], array_values(array_diff(scandir($this->tmpDir), [".", ".."])), "no temporary file is left");
    }

    public function testWriteAtomicFollowsSymlink(): void
    {
        $target = $this->tmpDir . "/target.txt";
        $link = $this->tmpDir . "/link.txt";
        file_put_contents($target, "old");
        symlink($target, $link);
        FileSystemUtils::writeAtomic($link, "new");
        Assert::true(is_link($link), "the link stays a link");
        Assert::equals("new", file_get_contents($target));
    }

    public function testWriteAtomicFailsWithoutReplacing(): void
    {
        Assert::throws(\RuntimeException::class, function () {
            FileSystemUtils::writeAtomic($this->tmpDir . "/missing-dir/data.txt", "x");
        });
    }

    public function testUpdateJson(): void
    {
        $file = $this->tmpDir . "/store.json";
        FileSystemUtils::updateJson($file, function (array &$data) {
            $data["a"] = 1;
            return true;
        });
        FileSystemUtils::updateJson($file, function (array &$data) {
            $data["b"] = 2;
            return true;
        });
        Assert::equals(["a" => 1, "b" => 2], json_decode(file_get_contents($file), true));
        Assert::equals(0600, fileperms($file) & 0777);
        FileSystemUtils::updateJson($file, function (array &$data) {
            $data["c"] = 3;
            return false;
        });
        Assert::equals(["a" => 1, "b" => 2], json_decode(file_get_contents($file), true), "false writes nothing");
    }

    public function testUpdateJsonKeepsDamagedFile(): void
    {
        $file = $this->tmpDir . "/store.json";
        file_put_contents($file, '{"a": 1, "b"');
        Assert::throws(\RuntimeException::class, function () use ($file) {
            FileSystemUtils::updateJson($file, function (array &$data) {
                $data["c"] = 3;
                return true;
            });
        });
        Assert::equals('{"a": 1, "b"', file_get_contents($file), "a damaged file is not overwritten with only the new entry");
    }
}
