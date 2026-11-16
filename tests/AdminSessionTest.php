<?php

namespace Shaack\Tests;

use Shaack\Htpasswd;
use Shaack\Reboot\AdminSession;

class AdminSessionTest
{
    private string $tmpDir;
    private array $session;

    public function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . "/test_admin_session_" . uniqid();
        mkdir($this->tmpDir . "/local", 0755, true);
        $htpasswd = new Htpasswd($this->tmpDir . "/local/.htpasswd");
        $htpasswd->addUser("admin", "secret");
        $htpasswd->addUser("editor", "secret");
        file_put_contents($this->tmpDir . "/local/roles.yml", "editor: editor\n");
        $this->session = [];
    }

    public function tearDown(): void
    {
        foreach (glob($this->tmpDir . "/local/{,.}*", GLOB_BRACE) as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        rmdir($this->tmpDir . "/local");
        rmdir($this->tmpDir);
    }

    private function login(string $user): void
    {
        $this->session['user'] = $user;
        $this->session['checksum'] = AdminSession::checksum(new Htpasswd($this->tmpDir . "/local/.htpasswd"));
    }

    public function testNoLogin(): void
    {
        Assert::null(AdminSession::getAdmin($this->tmpDir, $this->session));
    }

    public function testAdminLogin(): void
    {
        $this->login("admin");
        Assert::equals("admin", AdminSession::getAdmin($this->tmpDir, $this->session));
    }

    public function testEditorIsNoAdmin(): void
    {
        $this->login("editor");
        Assert::null(AdminSession::getAdmin($this->tmpDir, $this->session));
    }

    public function testUnknownUser(): void
    {
        $this->login("nobody");
        Assert::null(AdminSession::getAdmin($this->tmpDir, $this->session));
    }

    public function testStaleChecksum(): void
    {
        $this->login("admin");
        $this->session['checksum'] = "stale";
        Assert::null(AdminSession::getAdmin($this->tmpDir, $this->session));
    }

    public function testImpersonatingAdminCounts(): void
    {
        $this->login("admin");
        $this->session['impersonator'] = "admin";
        $this->session['user'] = "editor";
        Assert::equals("admin", AdminSession::getAdmin($this->tmpDir, $this->session));
    }

    public function testRestoreFromRememberCookie(): void
    {
        $token = AdminSession::rememberStore($this->tmpDir)->create("admin");
        $_COOKIE[AdminSession::REMEMBER_COOKIE] = $token;
        $_SESSION = [];
        try {
            Assert::equals("admin", AdminSession::restoreFromRememberCookie($this->tmpDir, ""));
            Assert::equals("admin", AdminSession::getAdmin($this->tmpDir, $_SESSION));
            Assert::true(AdminSession::hasValidLogin($this->tmpDir));
            Assert::true($_COOKIE[AdminSession::REMEMBER_COOKIE] !== $token, "the token is replaced");
            Assert::null(AdminSession::rememberStore($this->tmpDir)->consume($token), "the old token is used up");
            Assert::true(AdminSession::hasResumeHint($this->tmpDir), "the site gets the hint");
        } finally {
            $_COOKIE = [];
            $_SESSION = [];
        }
    }

    public function testRestoreRejectsUnknownToken(): void
    {
        $_COOKIE[AdminSession::REMEMBER_COOKIE] = "nonsense";
        $_COOKIE[AdminSession::RESUME_HINT_COOKIE] = "hint";
        $_SESSION = [];
        try {
            Assert::null(AdminSession::restoreFromRememberCookie($this->tmpDir, ""));
            Assert::false(isset($_COOKIE[AdminSession::REMEMBER_COOKIE]), "the cookie is cleared");
            Assert::false(isset($_COOKIE[AdminSession::RESUME_HINT_COOKIE]), "the hint is cleared");
            Assert::false(isset($_SESSION['user']));
        } finally {
            $_COOKIE = [];
            $_SESSION = [];
        }
    }

    public function testRestoreRejectsDisabledUser(): void
    {
        file_put_contents($this->tmpDir . "/local/roles.yml", "editor: editor\nadmin: disabled\n");
        $_COOKIE[AdminSession::REMEMBER_COOKIE] = AdminSession::rememberStore($this->tmpDir)->create("admin");
        $_SESSION = [];
        try {
            Assert::null(AdminSession::restoreFromRememberCookie($this->tmpDir, ""));
        } finally {
            $_COOKIE = [];
            $_SESSION = [];
        }
    }

    public function testRestoredEditorIsNoAdmin(): void
    {
        $_COOKIE[AdminSession::REMEMBER_COOKIE] = AdminSession::rememberStore($this->tmpDir)->create("editor");
        $_SESSION = [];
        try {
            Assert::equals("editor", AdminSession::restoreFromRememberCookie($this->tmpDir, ""));
            Assert::null(AdminSession::getAdmin($this->tmpDir, $_SESSION));
            Assert::true(AdminSession::hasValidLogin($this->tmpDir), "a valid login, no further restore");
        } finally {
            $_COOKIE = [];
            $_SESSION = [];
        }
    }

    public function testStaleLoginIsNotValid(): void
    {
        $_SESSION = ['user' => 'admin', 'checksum' => 'stale'];
        try {
            Assert::false(AdminSession::hasValidLogin($this->tmpDir));
        } finally {
            $_SESSION = [];
        }
    }

    public function testResumeHintBelongsToItsInstallation(): void
    {
        try {
            AdminSession::issueRememberCookie($this->tmpDir, "", "admin");
            Assert::true(AdminSession::hasResumeHint($this->tmpDir));
            Assert::false(AdminSession::hasResumeHint($this->tmpDir . "/other"), "another installation on the domain");
        } finally {
            $_COOKIE = [];
        }
    }

    public function testResumeUrl(): void
    {
        Assert::equals("/admin/resume?next=%2Fcustomers%2FFUZ", AdminSession::resumeUrl("", "/customers/FUZ"));
        Assert::equals("/demo/admin/resume?next=%2Fdemo%2Fa", AdminSession::resumeUrl("/demo", "/demo/a"));
    }

    public function testSafeNext(): void
    {
        Assert::equals("/customers/FUZ?a=1", AdminSession::safeNext("", "/customers/FUZ?a=1"));
        Assert::equals("/", AdminSession::safeNext("", "//evil.example"));
        Assert::equals("/", AdminSession::safeNext("", "/\\evil.example"));
        Assert::equals("/", AdminSession::safeNext("", "https://evil.example"));
        Assert::equals("/demo/", AdminSession::safeNext("/demo", "/a\nLocation: x"));
        Assert::equals("/demo/", AdminSession::safeNext("/demo", null));
    }
}
