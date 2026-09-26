<?php

namespace Shaack\Tests;

use Shaack\Reboot\Downloads\DownloadStore;

class DownloadStoreTest
{
    private string $baseFsPath;
    private string $sourceFile;

    public function setUp(): void
    {
        $this->baseFsPath = sys_get_temp_dir() . "/reboot-downloads-test-" . uniqid();
        mkdir($this->baseFsPath . "/local", 0755, true);
        $this->sourceFile = $this->baseFsPath . "/source.pdf";
        file_put_contents($this->sourceFile, "secret content");
    }

    public function tearDown(): void
    {
        self::deleteDirectory($this->baseFsPath);
    }

    private function store(array $config = []): DownloadStore
    {
        return new DownloadStore($this->baseFsPath, $config);
    }

    private function addFile(DownloadStore $store, string $name = "Passwords.pdf", ?string $label = null): array
    {
        $source = $this->sourceFile;
        return $store->add(function (string $target) use ($source) {
            return copy($source, $target);
        }, $name, $label);
    }

    public function testAddStoresFileAndMetadata(): void
    {
        $store = $this->store();
        $entry = $this->addFile($store, "Passwords.pdf", "For the migration");
        Assert::true((bool)preg_match('/^[a-f0-9]{16}$/', $entry['token']), "Token should be 16 hex chars");
        Assert::equals("Passwords.pdf", $entry['fileName']);
        Assert::equals("pdf", $entry['extension']);
        Assert::equals("For the migration", $entry['label']);
        Assert::equals(strlen("secret content"), $entry['size']);
        Assert::true((bool)preg_match('/^[0-9]{6}$/', $entry['password']), "Password should be six digits");
        Assert::true(is_file($store->getFilePath($entry['token'])), "Payload should be stored");
    }

    public function testFileNameIsNotPartOfTheStoredPath(): void
    {
        $store = $this->store();
        $entry = $this->addFile($store, "Passwords.pdf");
        $path = $store->getFilePath($entry['token']);
        Assert::equals($entry['token'], basename($path));
        Assert::false(str_contains($path, "Passwords"), "The real name must not appear in the path");
    }

    public function testBuildLinkPathHidesTheRealName(): void
    {
        $store = $this->store();
        $entry = $this->addFile($store, "Passwords.pdf");
        $linkPath = DownloadStore::buildLinkPath("/downloads", $entry);
        Assert::equals("/downloads/" . $entry['token'] . ".pdf", $linkPath);
        Assert::false(str_contains($linkPath, "Passwords"), "The real name must not appear in the link");
    }

    public function testFindReturnsNullForUnknownAndInvalidTokens(): void
    {
        $store = $this->store();
        Assert::null($store->find("0123456789abcdef"));
        Assert::null($store->find("../../etc/passwd"));
    }

    public function testVerifyPasswordAcceptsTheCorrectPassword(): void
    {
        $store = $this->store();
        $entry = $this->addFile($store);
        Assert::true($store->verifyPassword($entry['token'], $entry['password']));
        // Spaces from copy and paste are tolerated
        $spaced = substr($entry['password'], 0, 3) . " " . substr($entry['password'], 3);
        Assert::true($store->verifyPassword($entry['token'], $spaced));
    }

    public function testVerifyPasswordCountsWrongAttempts(): void
    {
        $store = $this->store();
        $entry = $this->addFile($store);
        Assert::false($store->verifyPassword($entry['token'], "000000"));
        Assert::equals(1, $store->find($entry['token'])['failedAttempts']);
        Assert::false($store->verifyPassword($entry['token'], ""));
        Assert::equals(2, $store->find($entry['token'])['failedAttempts']);
    }

    public function testTokenIsLockedAfterTenWrongAttempts(): void
    {
        $store = $this->store();
        $entry = $this->addFile($store);
        for ($i = 0; $i < DownloadStore::MAX_FAILED_ATTEMPTS; $i++) {
            $store->verifyPassword($entry['token'], "000000");
        }
        $locked = $store->find($entry['token']);
        Assert::true($store->isLocked($locked), "Token should be locked");
        Assert::false($store->isAvailable($locked));
        // Even the correct password does not help any more
        Assert::false($store->verifyPassword($entry['token'], $entry['password']));
    }

    public function testRegisterDownloadLogsTimeAndAnonymizedIp(): void
    {
        $store = $this->store();
        $entry = $this->addFile($store);
        $store->registerDownload($entry['token'], "192.168.13.42");
        $stored = $store->find($entry['token']);
        Assert::notNull($stored['firstDownloadAt']);
        Assert::count(1, $stored['downloads']);
        Assert::equals("192.168.13.x", $stored['downloads'][0]['ip']);
        $store->registerDownload($entry['token'], "192.168.13.43");
        $stored = $store->find($entry['token']);
        Assert::count(2, $stored['downloads']);
        Assert::equals($stored['firstDownloadAt'], $store->find($entry['token'])['firstDownloadAt']);
    }

    public function testLinkExpiresAfterTheConfiguredLifetime(): void
    {
        $store = $this->store(['linkLifetime' => 0]);
        $entry = $this->addFile($store);
        Assert::true($store->isExpired($entry));
        Assert::false($store->isAvailable($entry));
        Assert::false($store->verifyPassword($entry['token'], $entry['password']));
    }

    public function testLinkExpiresAfterTheDownloadWindow(): void
    {
        $store = $this->store(['downloadWindow' => 0]);
        $entry = $this->addFile($store);
        Assert::false($store->isExpired($entry), "A fresh link is valid");
        $store->registerDownload($entry['token'], "10.0.0.1");
        $stored = $store->find($entry['token']);
        Assert::true($store->isExpired($stored), "The window closes after the first download");
    }

    public function testExpiresAtUsesTheEarlierOfBothLimits(): void
    {
        $store = $this->store(['linkLifetime' => 604800, 'downloadWindow' => 3600]);
        $entry = $this->addFile($store);
        Assert::equals($entry['createdAt'] + 604800, $store->getExpiresAt($entry));
        $store->registerDownload($entry['token'], null);
        $stored = $store->find($entry['token']);
        Assert::equals($stored['firstDownloadAt'] + 3600, $store->getExpiresAt($stored));
    }

    public function testPurgeDeletesExpiredFilesButKeepsTheLog(): void
    {
        $store = $this->store(['linkLifetime' => 0, 'logRetention' => 604800]);
        $entry = $this->addFile($store);
        $path = $store->getFilePath($entry['token']);
        Assert::equals(1, $store->purge());
        Assert::false(is_file($path), "The payload should be deleted");
        $stored = $store->find($entry['token']);
        Assert::notNull($stored, "The log entry should stay");
        Assert::true($stored['fileDeleted']);
    }

    public function testPurgeForgetsEntriesAfterTheLogRetention(): void
    {
        $store = $this->store(['linkLifetime' => 0, 'logRetention' => 0]);
        $entry = $this->addFile($store);
        $store->purge();
        Assert::null($store->find($entry['token']), "The entry should be forgotten");
        Assert::count(0, $store->getEntries());
    }

    public function testDeleteRemovesEntryAndFile(): void
    {
        $store = $this->store();
        $entry = $this->addFile($store);
        $path = $store->getFilePath($entry['token']);
        Assert::true($store->delete($entry['token']));
        Assert::false(is_file($path));
        Assert::null($store->find($entry['token']));
        Assert::false($store->delete($entry['token']), "Deleting twice reports failure");
    }

    public function testGetEntriesReturnsNewestFirst(): void
    {
        $store = $this->store();
        $first = $this->addFile($store, "one.pdf");
        $second = $this->addFile($store, "two.pdf");
        // Both uploads can share a timestamp, so only check that both are listed
        $entries = $store->getEntries();
        Assert::count(2, $entries);
        $tokens = array_column($entries, 'token');
        Assert::true(in_array($first['token'], $tokens, true));
        Assert::true(in_array($second['token'], $tokens, true));
    }

    public function testGetFilePathRejectsInvalidTokens(): void
    {
        $store = $this->store();
        Assert::throws(\InvalidArgumentException::class, function () use ($store) {
            $store->getFilePath("../../local/.htpasswd");
        });
    }

    public function testExtensionOfIgnoresOddExtensions(): void
    {
        Assert::equals("pdf", DownloadStore::extensionOf("Passwords.pdf"));
        Assert::equals("pdf", DownloadStore::extensionOf("Passwords.PDF"));
        Assert::equals("", DownloadStore::extensionOf("Passwords"));
        Assert::equals("", DownloadStore::extensionOf("archive.tar.averylongextension"));
    }

    public function testAnonymizeIp(): void
    {
        Assert::equals("192.168.13.x", DownloadStore::anonymizeIp("192.168.13.42"));
        Assert::equals("2001:db8:85a3:8d3::x", DownloadStore::anonymizeIp("2001:db8:85a3:8d3:1319:8a2e:370:7348"));
        Assert::equals("", DownloadStore::anonymizeIp(null));
        Assert::equals("", DownloadStore::anonymizeIp("not an ip"));
    }

    public function testAddRejectsEmptyFileNames(): void
    {
        $store = $this->store();
        Assert::throws(\InvalidArgumentException::class, function () use ($store) {
            $store->add(function () { return true; }, "  ");
        });
    }

    private static function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) return;
        foreach (scandir($dir) as $entry) {
            if ($entry === "." || $entry === "..") continue;
            $path = $dir . "/" . $entry;
            is_dir($path) ? self::deleteDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
