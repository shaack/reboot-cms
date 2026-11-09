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

    public function testBranchesPerMajorVersion(): void
    {
        Assert::equals(["stable" => "distrib", "unstable" => "main"], Updater::branches("3.21.0"));
        Assert::equals(["stable" => "distrib", "unstable" => "main"], Updater::branches(null), "unknown is treated as 3");
        Assert::equals(["stable" => "distrib-4", "unstable" => "main-4"], Updater::branches("4.0.0"));
        Assert::equals(4, Updater::majorOf("4.2.1"));
    }

    public function testKeepPreviousAndRestoreIt(): void
    {
        $base = $this->tmpDir;
        mkdir($base . "/core/src", 0755, true);
        mkdir($base . "/web/admin", 0755, true);
        mkdir($base . "/vendor", 0755, true);
        mkdir($base . "/local", 0755, true);
        file_put_contents($base . "/core/src/A.php", "version 3");
        file_put_contents($base . "/web/admin/index.php", "admin 3");
        file_put_contents($base . "/vendor/autoload.php", "vendor 3");
        file_put_contents($base . "/README.md", "readme 3");
        file_put_contents($base . "/composer.json", json_encode(["name" => "site", "version" => "3.22.1"]));
        $updater = new Updater($base);
        Assert::null($updater->getPreviousVersion());
        Assert::throws(\RuntimeException::class, fn() => $updater->restorePrevious(), "nothing kept, nothing to restore");

        $updater->keepPrevious();
        Assert::equals("3.22.1", $updater->getPreviousVersion());

        // what an upgrade would do: other files and a new version
        file_put_contents($base . "/core/src/A.php", "version 4");
        file_put_contents($base . "/core/src/B.php", "only in 4");
        file_put_contents($base . "/web/admin/index.php", "admin 4");
        file_put_contents($base . "/README.md", "readme 4");
        file_put_contents($base . "/composer.json", json_encode(["name" => "site", "version" => "4.0.0"]));

        $updater->restorePrevious();
        Assert::equals("3.22.1", $updater->getLocalVersion());
        Assert::equals("version 3", file_get_contents($base . "/core/src/A.php"));
        Assert::false(file_exists($base . "/core/src/B.php"), "files of the newer version are gone");
        Assert::equals("admin 3", file_get_contents($base . "/web/admin/index.php"));
        Assert::equals("readme 3", file_get_contents($base . "/README.md"));
        Assert::equals("site", json_decode(file_get_contents($base . "/composer.json"), true)["name"]);
        Assert::null($updater->getPreviousVersion(), "the kept version is used up");
        Assert::false(is_dir($base . "/local/previous-version"));
    }

    public function testDiscardPrevious(): void
    {
        mkdir($this->tmpDir . "/core", 0755, true);
        file_put_contents($this->tmpDir . "/composer.json", json_encode(["version" => "3.22.1"]));
        $updater = new Updater($this->tmpDir);
        $updater->keepPrevious();
        Assert::notNull($updater->getPreviousVersion());
        $updater->discardPrevious();
        Assert::null($updater->getPreviousVersion());
    }

    public function testUpgradeFindings(): void
    {
        $site = $this->tmpDir . "/site";
        mkdir($site . "/pages/sub", 0755, true);
        mkdir($site . "/blocks/teaser", 0755, true);
        mkdir($site . "/blocks/assets", 0755, true);
        file_put_contents($site . "/pages/index.md", "# Home");
        Assert::equals([], Updater::upgradeFindings($site), "a plain Markdown site reads the same");

        file_put_contents($site . "/pages/sub/list.yml", "a: 1");
        file_put_contents($site . "/pages/index.yml", "a: 1");
        file_put_contents($site . "/blocks/teaser/teaser.php", "<?php");
        file_put_contents($site . "/blocks/assets/style.css", "");
        mkdir($site . "/data", 0755, true);
        mkdir($site . "/blueprints", 0755, true);
        $findings = Updater::upgradeFindings($site);
        Assert::count(4, $findings, "index.yml next to index.md and a folder without template are no findings");
        Assert::contains("site/pages/sub/list.yml", implode("\n", $findings));
        Assert::contains("site/blocks/teaser/", implode("\n", $findings));
        Assert::contains("site/data/", implode("\n", $findings));
        Assert::contains("site/blueprints/", implode("\n", $findings));
    }

    public function testGetRemoteVersionInvalidRepo(): void
    {
        $updater = new Updater($this->tmpDir, "nonexistent/nonexistent-repo-xyz", "nonexistent-branch");
        Assert::null($updater->getRemoteVersion());
    }
}
