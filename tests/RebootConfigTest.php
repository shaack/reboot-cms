<?php

namespace Shaack\Tests;

use Shaack\Reboot\Reboot;

class RebootConfigTest
{
    private string $tmpDir;

    public function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . "/test_config_" . uniqid();
        mkdir($this->tmpDir . "/local", 0755, true);
        file_put_contents($this->tmpDir . "/local/config.yml",
            "logLevel: 2\neditor:\n  fontSize: '1rem'\n  tools: [ Bold, Italic ]\nhistory:\n  maxVersions: 50\n");
    }

    public function tearDown(): void
    {
        @unlink($this->tmpDir . "/local/config.yml");
        @unlink($this->tmpDir . "/local/config.local.yml");
        @rmdir($this->tmpDir . "/local");
        @rmdir($this->tmpDir);
    }

    public function testWithoutLocalFileTheShippedConfigIsUsedAsIs(): void
    {
        $overrides = ['stale'];
        $config = Reboot::loadLocalConfig($this->tmpDir, $overrides);
        Assert::equals(2, $config['logLevel']);
        Assert::equals(50, $config['history']['maxVersions']);
        Assert::equals([], $overrides);
    }

    public function testLocalFileOverridesAndMergesSections(): void
    {
        file_put_contents($this->tmpDir . "/local/config.local.yml",
            "logLevel: 0\nmail:\n  transport: smtp\n  host: host.containers.internal\neditor:\n  fontSize: '2rem'\n  tools: [ Headings ]\n");
        $config = Reboot::loadLocalConfig($this->tmpDir, $overrides);
        Assert::equals(0, $config['logLevel'], 'scalar overridden');
        Assert::equals('host.containers.internal', $config['mail']['host'], 'new section added');
        Assert::equals('2rem', $config['editor']['fontSize'], 'nested key overridden');
        Assert::equals(['Headings'], $config['editor']['tools'], 'lists are replaced, not merged by index');
        Assert::equals(50, $config['history']['maxVersions'], 'untouched sections survive');
        Assert::equals(['logLevel', 'mail', 'editor'], $overrides);
    }

    public function testEmptyLocalFileChangesNothing(): void
    {
        file_put_contents($this->tmpDir . "/local/config.local.yml", "# nothing yet\n");
        $config = Reboot::loadLocalConfig($this->tmpDir, $overrides);
        Assert::equals(2, $config['logLevel']);
        Assert::equals([], $overrides);
    }

    public function testMergeConfigKeepsSiblingsOfMergedSections(): void
    {
        $merged = Reboot::mergeConfig(
            ['mail' => ['transport' => 'smtp', 'host' => 'a', 'port' => 465], 'logLevel' => 2],
            ['mail' => ['host' => 'b', 'encryption' => 'none']]
        );
        Assert::equals(['transport' => 'smtp', 'host' => 'b', 'port' => 465, 'encryption' => 'none'], $merged['mail']);
        Assert::equals(2, $merged['logLevel']);
    }
}
