<?php

namespace Shaack\Tests;

use Shaack\Logger;
use Shaack\Reboot\Mailer;

class MailerTest
{
    private string $tmpDir;
    private $serverProcess = null;
    private int $logLevel;

    public function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . "/test_mailer_" . uniqid();
        mkdir($this->tmpDir, 0755, true);
        $this->logLevel = Logger::getLevel();
        Logger::setLevel(3);
    }

    public function tearDown(): void
    {
        Logger::setLevel($this->logLevel);
        if (is_resource($this->serverProcess)) {
            proc_terminate($this->serverProcess);
            proc_close($this->serverProcess);
        }
        array_map('unlink', glob($this->tmpDir . "/*"));
        rmdir($this->tmpDir);
    }

    /**
     * Starts the fake SMTP server and returns its port.
     */
    private function startFakeSmtp(): int
    {
        $portFile = $this->tmpDir . "/port";
        $logFile = $this->tmpDir . "/smtp.log";
        $cmd = [PHP_BINARY, __DIR__ . "/support/fake-smtp.php", $portFile, $logFile];
        $this->serverProcess = proc_open($cmd, [], $pipes);
        for ($i = 0; $i < 100 && !is_file($portFile); $i++) {
            usleep(20000);
        }
        if (!is_file($portFile)) {
            throw new \RuntimeException("fake-smtp did not start");
        }
        return (int)file_get_contents($portFile);
    }

    private function smtpLog(): string
    {
        return (string)file_get_contents($this->tmpDir . "/smtp.log");
    }

    public function testUnconfiguredMailerRefusesToSend(): void
    {
        $mailer = new Mailer([]);
        Assert::false($mailer->isConfigured());
        Assert::equals('not configured', $mailer->describe());
        try {
            $mailer->send('alice@example.com', 'Hi', 'Hello');
            throw new \RuntimeException("expected an exception");
        } catch (\RuntimeException $e) {
            Assert::true(str_contains($e->getMessage(), 'not configured'), $e->getMessage());
        }
    }

    public function testConfigurationErrors(): void
    {
        Assert::equals("mail.transport must be one of smtp, mail, log",
            (new Mailer(['transport' => 'carrier-pigeon', 'from' => 'a@example.com']))->configurationError());
        Assert::equals("mail.from must be a valid e-mail address",
            (new Mailer(['transport' => 'log']))->configurationError());
        Assert::equals("mail.host is required for the smtp transport",
            (new Mailer(['transport' => 'smtp', 'from' => 'a@example.com']))->configurationError());
        Assert::null((new Mailer(['transport' => 'log', 'from' => 'a@example.com']))->configurationError());
        Assert::null((new Mailer(['transport' => 'mail', 'from' => 'a@example.com']))->configurationError());
    }

    public function testLogTransportSendsNothing(): void
    {
        $mailer = new Mailer(['transport' => 'log', 'from' => 'noreply@example.com']);
        Assert::true($mailer->isConfigured());
        $mailer->send('alice@example.com', 'Hi', 'Hello'); // must not throw
        Assert::equals('log only, nothing is sent', $mailer->describe());
    }

    public function testRejectsInvalidRecipient(): void
    {
        $mailer = new Mailer(['transport' => 'log', 'from' => 'noreply@example.com']);
        try {
            $mailer->send('not-an-address', 'Hi', 'Hello');
            throw new \RuntimeException("expected an exception");
        } catch (\RuntimeException $e) {
            Assert::true(str_contains($e->getMessage(), 'Invalid recipient'), $e->getMessage());
        }
    }

    public function testDescribeHidesThePassword(): void
    {
        $mailer = new Mailer([
            'transport' => 'smtp', 'host' => 'mail.example.com', 'port' => 465, 'encryption' => 'ssl',
            'username' => 'bob@example.com', 'password' => 'very-secret', 'from' => 'bob@example.com',
        ]);
        Assert::equals('smtp://bob@example.com@mail.example.com:465 (ssl), from bob@example.com', $mailer->describe());
        Assert::false(str_contains($mailer->describe(), 'very-secret'));
    }

    public function testSendsOverSmtpWithAuthentication(): void
    {
        $port = $this->startFakeSmtp();
        $mailer = new Mailer([
            'transport' => 'smtp', 'host' => '127.0.0.1', 'port' => $port, 'encryption' => 'none',
            'username' => 'bob', 'password' => 'secret',
            'from' => 'noreply@example.com', 'fromName' => 'Example Inc.', 'replyTo' => 'office@example.com',
        ]);
        $mailer->send('alice@example.com', 'Grüße aus Berlin', "Hallo Alice,\nalles Gute.", ['toName' => 'Alice']);
        $log = $this->smtpLog();
        Assert::true(str_contains($log, 'MAIL FROM:<noreply@example.com>'), $log);
        Assert::true(str_contains($log, 'RCPT TO:<alice@example.com>'), $log);
        Assert::true(str_contains($log, base64_encode('bob')), 'user name sent via AUTH LOGIN');
        Assert::true(str_contains($log, base64_encode('secret')), 'password sent via AUTH LOGIN');
        Assert::true(str_contains($log, 'Reply-To: office@example.com'), $log);
        Assert::true(str_contains($log, 'To: Alice <alice@example.com>'), $log);
        Assert::true(stripos($log, 'Subject: =?utf-8?') !== false, 'UTF-8 subject is encoded');
        Assert::true(str_contains($log, 'alles Gute.'), 'body transmitted');
        Assert::true(str_contains($log, 'QUIT'), 'session closed');
    }

    public function testSendsHtmlWithTextAlternative(): void
    {
        $port = $this->startFakeSmtp();
        $mailer = new Mailer([
            'transport' => 'smtp', 'host' => '127.0.0.1', 'port' => $port, 'encryption' => 'none',
            'from' => 'noreply@example.com',
        ]);
        $mailer->send('alice@example.com', 'Login', 'Open https://example.com/x', ['html' => '<p><a href="https://example.com/x">Open</a></p>']);
        $log = $this->smtpLog();
        Assert::true(str_contains($log, 'multipart/alternative'), $log);
        Assert::true(str_contains($log, 'text/plain'), $log);
        Assert::true(str_contains($log, 'text/html'), $log);
        Assert::false(str_contains($log, 'AUTH'), 'no authentication without a user name');
    }

    public function testFailsWhenTheServerIsNotReachable(): void
    {
        $mailer = new Mailer([
            'transport' => 'smtp', 'host' => '127.0.0.1', 'port' => 9, 'encryption' => 'none',
            'from' => 'noreply@example.com',
        ]);
        try {
            $mailer->send('alice@example.com', 'Hi', 'Hello');
            throw new \RuntimeException("expected an exception");
        } catch (\RuntimeException $e) {
            Assert::true(str_contains($e->getMessage(), 'Sending the mail failed'), $e->getMessage());
        }
    }
}
