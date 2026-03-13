<?php

namespace Shaack\Tests;

use Shaack\Htpasswd;

class HtpasswdTest
{
    private string $tmpFile;

    public function setUp(): void
    {
        $this->tmpFile = sys_get_temp_dir() . "/test_htpasswd_" . uniqid();
    }

    public function tearDown(): void
    {
        if (file_exists($this->tmpFile)) {
            unlink($this->tmpFile);
        }
    }

    public function testConstructWithMissingFile(): void
    {
        $htpasswd = new Htpasswd($this->tmpFile);
        Assert::true($htpasswd->isEmpty());
        Assert::equals([], $htpasswd->getUsers());
    }

    public function testAddUser(): void
    {
        $htpasswd = new Htpasswd($this->tmpFile);
        $htpasswd->addUser("admin", "password123");
        Assert::false($htpasswd->isEmpty());
        Assert::equals(["admin"], $htpasswd->getUsers());
        Assert::true(file_exists($this->tmpFile));
    }

    public function testAddUserDuplicate(): void
    {
        $htpasswd = new Htpasswd($this->tmpFile);
        $htpasswd->addUser("admin", "password123");
        Assert::throws(\InvalidArgumentException::class, function () use ($htpasswd) {
            $htpasswd->addUser("admin", "other");
        });
    }

    public function testAddMultipleUsers(): void
    {
        $htpasswd = new Htpasswd($this->tmpFile);
        $htpasswd->addUser("alice", "password123");
        $htpasswd->addUser("bob", "password456");
        Assert::count(2, $htpasswd->getUsers());
        Assert::equals(["alice", "bob"], $htpasswd->getUsers());
    }

    public function testValidatePassword(): void
    {
        $htpasswd = new Htpasswd($this->tmpFile);
        $htpasswd->addUser("admin", "secret99");
        Assert::true($htpasswd->validate("admin", "secret99"));
        Assert::false($htpasswd->validate("admin", "wrong"));
        Assert::false($htpasswd->validate("nonexistent", "secret99"));
    }

    public function testChangePassword(): void
    {
        $htpasswd = new Htpasswd($this->tmpFile);
        $htpasswd->addUser("admin", "oldpass12");
        $htpasswd->changePassword("admin", "newpass34");
        Assert::false($htpasswd->validate("admin", "oldpass12"));
        Assert::true($htpasswd->validate("admin", "newpass34"));
    }

    public function testChangePasswordNonexistent(): void
    {
        $htpasswd = new Htpasswd($this->tmpFile);
        Assert::throws(\InvalidArgumentException::class, function () use ($htpasswd) {
            $htpasswd->changePassword("ghost", "password");
        });
    }

    public function testDeleteUser(): void
    {
        $htpasswd = new Htpasswd($this->tmpFile);
        $htpasswd->addUser("alice", "password123");
        $htpasswd->addUser("bob", "password456");
        $htpasswd->deleteUser("alice");
        Assert::equals(["bob"], $htpasswd->getUsers());
        Assert::false($htpasswd->validate("alice", "password123"));
    }

    public function testDeleteUserNonexistent(): void
    {
        $htpasswd = new Htpasswd($this->tmpFile);
        Assert::throws(\InvalidArgumentException::class, function () use ($htpasswd) {
            $htpasswd->deleteUser("ghost");
        });
    }

    public function testPersistence(): void
    {
        $htpasswd = new Htpasswd($this->tmpFile);
        $htpasswd->addUser("admin", "password123");
        $checksum1 = $htpasswd->getChecksum();

        // Reload from file
        $htpasswd2 = new Htpasswd($this->tmpFile);
        Assert::equals(["admin"], $htpasswd2->getUsers());
        Assert::true($htpasswd2->validate("admin", "password123"));
        Assert::equals($checksum1, $htpasswd2->getChecksum());
    }

    public function testChecksumChangesOnModification(): void
    {
        $htpasswd = new Htpasswd($this->tmpFile);
        $htpasswd->addUser("admin", "password123");
        $checksum1 = $htpasswd->getChecksum();

        $htpasswd->addUser("bob", "password456");
        $checksum2 = $htpasswd->getChecksum();
        Assert::true($checksum1 !== $checksum2, "Checksum should change after adding user");
    }

    public function testParseExistingFile(): void
    {
        file_put_contents($this->tmpFile, "admin:\$apr1\$test\$hash\n");
        $htpasswd = new Htpasswd($this->tmpFile);
        Assert::equals(["admin"], $htpasswd->getUsers());
        Assert::false($htpasswd->isEmpty());
    }

    public function testCommentsIgnored(): void
    {
        file_put_contents($this->tmpFile, "# comment\nadmin:\$apr1\$test\$hash\n");
        $htpasswd = new Htpasswd($this->tmpFile);
        // Comment line is skipped, only admin parsed
        Assert::false(in_array("# comment", $htpasswd->getUsers()));
    }
}
