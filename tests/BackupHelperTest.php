<?php

namespace Shaack\Tests;

use Shaack\Reboot\AddOns\Admin\BackupHelper;
use Shaack\Reboot\Reboot;

class BackupHelperTest
{
    private string $root;
    private Reboot $reboot;

    public function setUp(): void
    {
        $this->root = sys_get_temp_dir() . "/reboot-backup-test-" . uniqid();
        mkdir($this->root . "/site/pages/about", 0755, true);
        mkdir($this->root . "/web/media/photos", 0755, true);
        mkdir($this->root . "/local", 0755, true);
        file_put_contents($this->root . "/site/pages/index.md", "# Home");
        file_put_contents($this->root . "/site/pages/about/index.md", "# About");
        file_put_contents($this->root . "/site/config.yml", "title: Test");
        file_put_contents($this->root . "/web/media/photos/a.png", random_bytes(2048));
        // noise that must not end up in the archive
        file_put_contents($this->root . "/site/.DS_Store", "x");
        file_put_contents($this->root . "/web/media/photos/._a.png", "x");
        // not part of a backup
        file_put_contents($this->root . "/local/config.yml", "secret: 1");

        $ref = new \ReflectionClass(Reboot::class);
        $this->reboot = $ref->newInstanceWithoutConstructor();
        $ref->getProperty('baseFsPath')->setValue($this->reboot, $this->root);
    }

    public function tearDown(): void
    {
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($this->root);
    }

    /** @return string[] the paths inside the archive */
    private function entries(string $name): array
    {
        $archive = new \PharData($this->root . "/local/backups/" . $name);
        $entries = [];
        foreach (new \RecursiveIteratorIterator($archive) as $file) {
            /** @var \PharFileInfo $file */
            $entries[] = substr($file->getPathname(), strpos($file->getPathname(), $name) + strlen($name) + 1);
        }
        sort($entries);
        return $entries;
    }

    public function testFileName(): void
    {
        $name = BackupHelper::fileName("www.example.com");
        Assert::true((bool)preg_match('/^Backup_www\.example\.com_\d{4}-\d{2}-\d{2}_\d{4}\.zip$/', $name), $name);
        Assert::contains("Backup_localhost-21007_", BackupHelper::fileName("localhost:21007"));
        Assert::contains("Backup_site_", BackupHelper::fileName("../"));
    }

    public function testCreateWritesContentWithoutNoise(): void
    {
        $name = BackupHelper::create($this->reboot, "example.com");
        Assert::true(is_file($this->root . "/local/backups/" . $name), "archive exists");
        Assert::equals([
            "site/config.yml",
            "site/pages/about/index.md",
            "site/pages/index.md",
            "web/media/photos/a.png",
        ], $this->entries($name));
    }

    public function testCreatedArchiveIsAZipWithTheOriginalContent(): void
    {
        $name = BackupHelper::create($this->reboot, "example.com");
        $path = $this->root . "/local/backups/" . $name;
        Assert::equals("PK\x03\x04", file_get_contents($path, false, null, 0, 4), "zip signature");
        $archive = new \PharData($path);
        Assert::equals("# About", $archive["site/pages/about/index.md"]->getContent());
        Assert::equals(file_get_contents($this->root . "/web/media/photos/a.png"), $archive["web/media/photos/a.png"]->getContent());
    }

    public function testCreateReplacesAnArchiveOfTheSameMinute(): void
    {
        $first = BackupHelper::create($this->reboot, "example.com");
        file_put_contents($this->root . "/site/pages/new.md", "# New");
        unlink($this->root . "/site/config.yml");
        $second = BackupHelper::create($this->reboot, "example.com");
        Assert::equals($first, $second);
        Assert::equals([
            "site/pages/about/index.md",
            "site/pages/index.md",
            "site/pages/new.md",
            "web/media/photos/a.png",
        ], $this->entries($second));
    }

    public function testCreateFailsOnEmptySources(): void
    {
        $empty = sys_get_temp_dir() . "/reboot-backup-empty-" . uniqid();
        mkdir($empty . "/site", 0755, true);
        $ref = new \ReflectionClass(Reboot::class);
        $reboot = $ref->newInstanceWithoutConstructor();
        $ref->getProperty('baseFsPath')->setValue($reboot, $empty);
        try {
            Assert::throws(\RuntimeException::class, fn() => BackupHelper::create($reboot, "example.com"));
            Assert::count(0, BackupHelper::all($reboot), "no half-written archive left behind");
        } finally {
            rmdir($empty . "/local/backups");
            rmdir($empty . "/local");
            rmdir($empty . "/site");
            rmdir($empty);
        }
    }

    public function testAllListsOnlyBackups(): void
    {
        $name = BackupHelper::create($this->reboot, "example.com");
        file_put_contents($this->root . "/local/backups/notes.txt", "x");
        $all = BackupHelper::all($this->reboot);
        Assert::count(1, $all);
        Assert::equals($name, $all[0]['name']);
        Assert::true($all[0]['size'] > 0);
    }

    public function testPathRejectsForeignNames(): void
    {
        BackupHelper::create($this->reboot, "example.com");
        file_put_contents($this->root . "/local/backups/notes.txt", "x");
        Assert::throws(\RuntimeException::class, fn() => BackupHelper::path($this->reboot, "notes.txt"));
        Assert::throws(\RuntimeException::class, fn() => BackupHelper::path($this->reboot, "../config.yml"));
        Assert::throws(\RuntimeException::class, fn() => BackupHelper::path($this->reboot, "Backup_x_2026-01-01_0000.zip"));
    }

    public function testDelete(): void
    {
        $name = BackupHelper::create($this->reboot, "example.com");
        BackupHelper::delete($this->reboot, $name);
        Assert::count(0, BackupHelper::all($this->reboot));
    }
}
