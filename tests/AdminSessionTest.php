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
}
