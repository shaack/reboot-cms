<?php

namespace Shaack\Tests;

use Shaack\Reboot\AddOns\ProtectedAreas\AccessControl;
use Shaack\Reboot\AddOns\ProtectedAreas\LoginThrottle;
use Shaack\Reboot\AddOns\ProtectedAreas\UserStore;

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
        $store->addUser('acme', 'secret-password', ['/customers/acme', 'customers/acme/', '']);
        Assert::true($store->hasUser('acme'));
        Assert::true($store->verify('acme', 'secret-password'));
        Assert::false($store->verify('acme', 'wrong-password'));
        Assert::false($store->verify('nobody', 'secret-password'));
        Assert::equals(['/customers/acme'], $store->getPaths('acme'));
        $users = $store->getUsers();
        Assert::equals(['acme'], array_keys($users));
        Assert::false(array_key_exists('passwordHash', $users['acme']));
        Assert::null($users['acme']['lastLoginAt']);
        Assert::true(is_file($this->tmpDir . '/local/protected-areas/users.json'));
    }

    public function testPasswordIsStoredHashed(): void
    {
        $store = new UserStore($this->tmpDir);
        $store->addUser('acme', 'secret-password', []);
        $raw = file_get_contents($this->tmpDir . '/local/protected-areas/users.json');
        Assert::false(str_contains($raw, 'secret-password'));
        Assert::true(str_contains($raw, '"passwordHash"'));
    }

    public function testChangePasswordPathsAndDelete(): void
    {
        $store = new UserStore($this->tmpDir);
        $store->addUser('acme', 'secret-password', ['/customers/acme']);
        $store->setPassword('acme', 'another-password');
        Assert::true($store->verify('acme', 'another-password'));
        Assert::false($store->verify('acme', 'secret-password'));
        $store->setPaths('acme', ['/customers/acme', '/customers/shared']);
        Assert::equals(['/customers/acme', '/customers/shared'], $store->getPaths('acme'));
        $store->registerLogin('acme');
        Assert::true(is_int($store->getUsers()['acme']['lastLoginAt']));
        $store->deleteUser('acme');
        Assert::false($store->hasUser('acme'));
        Assert::equals([], $store->getPaths('acme'));
    }

    public function testRejectsInvalidInput(): void
    {
        $store = new UserStore($this->tmpDir);
        $this->expectException(fn() => $store->addUser('', 'secret-password', []));
        $this->expectException(fn() => $store->addUser('../x', 'secret-password', []));
        $this->expectException(fn() => $store->addUser('acme', 'short', []));
        $store->addUser('acme', 'secret-password', []);
        $this->expectException(fn() => $store->addUser('acme', 'secret-password', []), 'duplicate user');
        $this->expectException(fn() => $store->setPassword('nobody', 'secret-password'), 'unknown user');
        $this->expectException(fn() => $store->setPaths('nobody', []), 'unknown user');
        $this->expectException(fn() => $store->deleteUser('nobody'), 'unknown user');
        Assert::true(UserStore::isValidUsername('john.doe@example.com'));
        Assert::false(UserStore::isValidUsername('.hidden'));
        Assert::false(UserStore::isValidUsername('with space'));
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
