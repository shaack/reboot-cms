<?php

namespace Shaack\Tests;

use Shaack\Reboot\Updater;

class UpdaterTest
{
    private string $tmpDir;

    public function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . "/test_updater_" . uniqid();
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

    public function testGetLocalVersion(): void
    {
        file_put_contents($this->tmpDir . "/composer.json", json_encode(["version" => "1.2.3"]));
        $updater = new Updater($this->tmpDir);
        Assert::equals("1.2.3", $updater->getLocalVersion());
    }

    public function testGetLocalVersionMissingFile(): void
    {
        $updater = new Updater($this->tmpDir);
        Assert::null($updater->getLocalVersion());
    }

    public function testGetLocalVersionMissingField(): void
    {
        file_put_contents($this->tmpDir . "/composer.json", json_encode(["name" => "test"]));
        $updater = new Updater($this->tmpDir);
        Assert::null($updater->getLocalVersion());
    }

    public function testGetRemoteVersionInvalidRepo(): void
    {
        $updater = new Updater($this->tmpDir, "nonexistent/nonexistent-repo-xyz", "nonexistent-branch");
        Assert::null($updater->getRemoteVersion());
    }
}
