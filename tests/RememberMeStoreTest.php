<?php

namespace Shaack\Tests;

use Shaack\Reboot\AddOns\Authentication\RememberMeStore;

class RememberMeStoreTest
{
    private string $tmpDir;
    private RememberMeStore $store;

    public function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . "/test_remember_" . uniqid();
        mkdir($this->tmpDir . "/local", 0755, true);
        $this->store = new RememberMeStore($this->tmpDir);
    }

    public function tearDown(): void
    {
        @unlink($this->tmpDir . "/local/remember-tokens.json");
        @rmdir($this->tmpDir . "/local");
        @rmdir($this->tmpDir);
    }

    public function testTokenIsRedeemedOnce(): void
    {
        $token = $this->store->create("alice");
        Assert::true((bool)preg_match('/^[0-9a-f]{18}:[0-9a-f]{64}$/', $token), "selector:validator in hex");
        Assert::equals(1, $this->store->count());
        Assert::equals("alice", $this->store->consume($token));
        Assert::null($this->store->consume($token), "a token is good for one use");
        Assert::equals(0, $this->store->count());
    }

    public function testFileHoldsOnlyHashes(): void
    {
        $token = $this->store->create("alice");
        $validator = explode(':', $token)[1];
        $file = file_get_contents($this->tmpDir . "/local/remember-tokens.json");
        Assert::false(str_contains($file, $validator), "the validator is not stored in clear");
        Assert::contains(hash('sha256', $validator), $file);
    }

    public function testWrongValidatorIsRejectedAndBurnsTheToken(): void
    {
        $token = $this->store->create("alice");
        [$selector] = explode(':', $token);
        Assert::null($this->store->consume($selector . ':' . str_repeat('0', 64)));
        Assert::null($this->store->consume($token), "a guessed selector invalidates the token");
    }

    public function testMalformedTokensAreRejected(): void
    {
        $this->store->create("alice");
        Assert::null($this->store->consume(null));
        Assert::null($this->store->consume(""));
        Assert::null($this->store->consume("nocolon"));
        Assert::null($this->store->consume("zz:zz"));
        Assert::equals(1, $this->store->count(), "nothing was touched");
    }

    public function testExpiredTokensAreRejectedAndPurged(): void
    {
        $now = 1000000;
        $fresh = $this->store->create("alice", $now);
        $stale = $this->store->create("bob", $now);
        Assert::equals("alice", $this->store->consume($fresh, $now + RememberMeStore::LIFETIME - 1), "valid until the end");
        Assert::null($this->store->consume($stale, $now + RememberMeStore::LIFETIME + 1), "expired");
        Assert::equals(0, $this->store->count($now + RememberMeStore::LIFETIME + 1));
    }

    public function testDeleteForUserKeepsOtherUsers(): void
    {
        $alice1 = $this->store->create("alice");
        $alice2 = $this->store->create("alice");
        $bob = $this->store->create("bob");
        $this->store->deleteForSubject("alice");
        Assert::null($this->store->consume($alice1));
        Assert::null($this->store->consume($alice2));
        Assert::equals("bob", $this->store->consume($bob));
    }

    public function testDeleteSingleToken(): void
    {
        $first = $this->store->create("alice");
        $second = $this->store->create("alice");
        $this->store->delete($first);
        $this->store->delete(null);
        Assert::null($this->store->consume($first));
        Assert::equals("alice", $this->store->consume($second));
    }
}
