<?php

namespace Shaack\Tests;

use Shaack\Reboot\AddOns\ProtectedAreas\AccessControl;
use Shaack\Reboot\AddOns\ProtectedAreas\LoginThrottle;
use Shaack\Reboot\AddOns\ProtectedAreas\UserStore;
use Shaack\Reboot\TokenStore;

class ProtectedAreasTest
{
    private string $tmpDir;

    public function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . "/test_protected_" . uniqid();
        mkdir($this->tmpDir . "/local", 0755, true);
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

    // --- AccessControl ---

    public function testNormalizePath(): void
    {
        Assert::equals('/', AccessControl::normalizePath('/'));
        Assert::equals('/', AccessControl::normalizePath(''));
        Assert::equals('/customers', AccessControl::normalizePath('customers/'));
        Assert::equals('/customers/acme', AccessControl::normalizePath('//customers//acme/'));
    }

    public function testNormalizePathsDropsEmptyAndDuplicates(): void
    {
        Assert::equals(['/a', '/b'], AccessControl::normalizePaths(['/a', '', ' ', 'b/', '/a/']));
        Assert::equals(['/'], AccessControl::normalizePaths(['/']));
    }

    public function testIsProtected(): void
    {
        $areas = ['/customers', '/intern'];
        Assert::true(AccessControl::isProtected('/customers', $areas));
        Assert::true(AccessControl::isProtected('/customers/acme/status', $areas));
        Assert::true(AccessControl::isProtected('/intern', $areas));
        Assert::false(AccessControl::isProtected('/customers-public', $areas));
        Assert::false(AccessControl::isProtected('/', $areas));
        Assert::false(AccessControl::isProtected('/about', $areas));
        Assert::false(AccessControl::isProtected('/about', []));
    }

    public function testCanAccess(): void
    {
        Assert::true(AccessControl::canAccess('/customers/acme', ['/customers/acme']));
        Assert::true(AccessControl::canAccess('/customers/acme/status', ['/customers/acme']));
        Assert::false(AccessControl::canAccess('/customers/other', ['/customers/acme']));
        Assert::false(AccessControl::canAccess('/customers/acme-corp', ['/customers/acme']));
        Assert::false(AccessControl::canAccess('/customers', ['/customers/acme']));
        Assert::true(AccessControl::canAccess('/anything/at/all', ['/']));
        Assert::false(AccessControl::canAccess('/customers/acme', []));
    }

    public function testSanitizeNext(): void
    {
        Assert::equals('/customers/acme', AccessControl::sanitizeNext('/customers/acme', '/'));
        Assert::equals('/a?x=1', AccessControl::sanitizeNext('/a?x=1', '/'));
        Assert::equals('/', AccessControl::sanitizeNext(null, '/'));
        Assert::equals('/', AccessControl::sanitizeNext('', '/'));
        Assert::equals('/', AccessControl::sanitizeNext('https://evil.example/', '/'));
        Assert::equals('/', AccessControl::sanitizeNext('//evil.example/', '/'));
        Assert::equals('/', AccessControl::sanitizeNext('/x://evil', '/'));
        Assert::equals('/', AccessControl::sanitizeNext("/a\r\nLocation: x", '/'));
        Assert::equals('/', AccessControl::sanitizeNext('relative', '/'));
        Assert::equals('/de', AccessControl::sanitizeNext(['array'], '/de'));
    }

    // --- UserStore ---

    public function testAddVerifyAndListUsers(): void
    {
        $store = new UserStore($this->tmpDir);
        $store->addUser(' Acme@Example.com ', 'secret-password', ['/customers/acme', 'customers/acme/', '']);
        Assert::true($store->hasUser('acme@example.com'));
        Assert::true($store->hasUser('ACME@example.com'), 'addresses are not case sensitive');
        Assert::true($store->verify('acme@example.com', 'secret-password'));
        Assert::true($store->verify('Acme@Example.com', 'secret-password'));
        Assert::false($store->verify('acme@example.com', 'wrong-password'));
        Assert::false($store->verify('nobody@example.com', 'secret-password'));
        Assert::equals(['/customers/acme'], $store->getPaths('acme@example.com'));
        $users = $store->getUsers();
        Assert::equals(['acme@example.com'], array_keys($users), 'stored trimmed and in lower case');
        Assert::false(array_key_exists('passwordHash', $users['acme@example.com']));
        Assert::null($users['acme@example.com']['lastLoginAt']);
        Assert::true(is_file($this->tmpDir . '/local/protected-areas/users.json'));
    }

    public function testPasswordIsStoredHashed(): void
    {
        $store = new UserStore($this->tmpDir);
        $store->addUser('acme@example.com', 'secret-password', []);
        $raw = file_get_contents($this->tmpDir . '/local/protected-areas/users.json');
        Assert::false(str_contains($raw, 'secret-password'));
        Assert::true(str_contains($raw, '"passwordHash"'));
    }

    public function testChangePasswordPathsAndDelete(): void
    {
        $store = new UserStore($this->tmpDir);
        $store->addUser('acme@example.com', 'secret-password', ['/customers/acme']);
        $store->setPassword('acme@example.com', 'another-password');
        Assert::true($store->verify('acme@example.com', 'another-password'));
        Assert::false($store->verify('acme@example.com', 'secret-password'));
        $store->setPaths('acme@example.com', ['/customers/acme', '/customers/shared']);
        Assert::equals(['/customers/acme', '/customers/shared'], $store->getPaths('acme@example.com'));
        $store->registerLogin('acme@example.com');
        Assert::true(is_int($store->getUsers()['acme@example.com']['lastLoginAt']));
        $store->deleteUser('acme@example.com');
        Assert::false($store->hasUser('acme@example.com'));
        Assert::equals([], $store->getPaths('acme@example.com'));
    }

    public function testRejectsInvalidInput(): void
    {
        $store = new UserStore($this->tmpDir);
        $this->expectException(fn() => $store->addUser('', 'secret-password', []));
        $this->expectException(fn() => $store->addUser('acme', 'secret-password', []), 'not an address');
        $this->expectException(fn() => $store->addUser('../x@example.com', 'secret-password', []));
        $this->expectException(fn() => $store->addUser('acme@example.com', 'short', []));
        $store->addUser('acme@example.com', 'secret-password', []);
        $this->expectException(fn() => $store->addUser('ACME@example.com', 'secret-password', []), 'duplicate account');
        $this->expectException(fn() => $store->setPassword('nobody@example.com', 'secret-password'), 'unknown user');
        $this->expectException(fn() => $store->setPaths('nobody@example.com', []), 'unknown user');
        $this->expectException(fn() => $store->deleteUser('nobody@example.com'), 'unknown user');
        Assert::true(UserStore::isValidEmail('john.doe@example.com'));
        Assert::true(UserStore::isValidEmail(' John.Doe@Example.com '));
        Assert::false(UserStore::isValidEmail('john.doe'));
        Assert::false(UserStore::isValidEmail('john doe@example.com'));
        Assert::false(UserStore::isValidEmail('@example.com'));
        Assert::equals('john@example.com', UserStore::normalizeEmail(' John@Example.COM '));
    }

    // --- LoginThrottle ---

    public function testThrottleBlocksAfterMaxAttemptsWithinWindow(): void
    {
        $throttle = new LoginThrottle($this->tmpDir);
        $now = 1000000;
        for ($i = 0; $i < LoginThrottle::MAX_ATTEMPTS - 1; $i++) {
            $throttle->registerFailure('10.0.0.1', $now);
        }
        Assert::false($throttle->isBlocked('10.0.0.1', $now));
        $throttle->registerFailure('10.0.0.1', $now);
        Assert::true($throttle->isBlocked('10.0.0.1', $now));
        Assert::false($throttle->isBlocked('10.0.0.2', $now), 'other addresses are not affected');
        Assert::false($throttle->isBlocked('10.0.0.1', $now + LoginThrottle::WINDOW + 1), 'the block expires');
        $throttle->clear('10.0.0.1', $now);
        Assert::false($throttle->isBlocked('10.0.0.1', $now), 'a successful login clears the attempts');
    }

    public function testThrottleCountersAreSeparatePerFile(): void
    {
        $logins = new LoginThrottle($this->tmpDir);
        $links = new LoginThrottle($this->tmpDir, 'link-requests.json');
        $now = 1000000;
        for ($i = 0; $i < LoginThrottle::MAX_ATTEMPTS; $i++) {
            $links->registerFailure('10.0.0.1', $now);
        }
        Assert::true($links->isBlocked('10.0.0.1', $now));
        Assert::false($logins->isBlocked('10.0.0.1', $now), 'link requests do not block password logins');
        Assert::true(is_file($this->tmpDir . '/local/protected-areas/link-requests.json'));
    }

    // --- Login link and remember tokens, see TokenStore ---

    public function testLoginLinkTokenIsSingleUseAndShortLived(): void
    {
        $store = new TokenStore($this->tmpDir . '/local/protected-areas/login-links.json', 900);
        $now = 1000000;
        $token = $store->create('alice@example.com', $now);
        Assert::null($store->consume($token, $now + 901), 'expired after 15 minutes');
        $token = $store->create('alice@example.com', $now);
        Assert::equals('alice@example.com', $store->consume($token, $now + 60));
        Assert::null($store->consume($token, $now + 61), 'a link works once');
        $raw = file_get_contents($this->tmpDir . '/local/protected-areas/login-links.json');
        Assert::false(str_contains($raw, explode(':', $token)[1]), 'only the hash is stored');
    }

    private function expectException(callable $fn, string $what = ''): void
    {
        try {
            $fn();
        } catch (\InvalidArgumentException $e) {
            return;
        }
        throw new \RuntimeException("Expected an InvalidArgumentException" . ($what ? " for $what" : ""));
    }
}
