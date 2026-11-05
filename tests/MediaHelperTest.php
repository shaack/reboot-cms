<?php

namespace Shaack\Tests;

use Shaack\Reboot\AddOns\Admin\MediaHelper;

class MediaHelperTest
{
    private string $file;

    public function setUp(): void
    {
        $this->file = tempnam(sys_get_temp_dir(), "test_media_");
    }

    public function tearDown(): void
    {
        @unlink($this->file);
    }

    private function active(string $content, string $extension = "svg"): bool
    {
        file_put_contents($this->file, $content);
        return MediaHelper::hasActiveContent($this->file, $extension);
    }

    public function testPlainSvgIsAccepted(): void
    {
        Assert::false($this->active('<svg xmlns="http://www.w3.org/2000/svg"><rect width="10" height="10" fill="red"/><text>Click on time = now</text></svg>'));
        Assert::false(MediaHelper::hasActiveContent(__DIR__ . "/../web/media/dummy.svg", "svg"), "the shipped dummy.svg");
    }

    public function testScriptsAndHandlersAreRejected(): void
    {
        Assert::true($this->active('<svg><script>alert(1)</script></svg>'));
        Assert::true($this->active('<svg><svg:script>alert(1)</svg:script></svg>'));
        Assert::true($this->active('<svg onload="alert(1)"></svg>'));
        Assert::true($this->active("<svg><rect\nONCLICK = 'x'/></svg>"));
        Assert::true($this->active('<svg><a href="javascript:alert(1)">x</a></svg>'));
        Assert::true($this->active('<svg><a href="&#106;avascript:alert(1)">x</a></svg>'), "entity encoded");
        Assert::true($this->active('<svg><foreignObject><iframe src="x"/></foreignObject></svg>'));
        Assert::true($this->active('<?xml-stylesheet type="text/xsl" href="x.xsl"?><r/>', "xml"));
        Assert::true($this->active('<r><html:script xmlns:html="http://www.w3.org/1999/xhtml">x</html:script></r>', "xml"));
    }

    public function testOtherExtensionsAreNeverActive(): void
    {
        Assert::false($this->active('<script>alert(1)</script>', "txt"));
        Assert::false($this->active('<svg onload="x"/>', "png"));
    }

    public function testShippedHtaccessIsBuiltFromTheConstants(): void
    {
        Assert::equals(
            MediaHelper::HTACCESS_NO_PHP . "\n\n" . MediaHelper::HTACCESS_SANDBOX . "\n",
            file_get_contents(__DIR__ . "/../web/media/.htaccess")
        );
    }

    public function testAddSandboxAppendsToAnExistingFile(): void
    {
        $old = "# Prevent PHP execution\n<FilesMatch \"\\.php$\">\n    Deny from all\n</FilesMatch>\n# custom rule\n";
        file_put_contents($this->file, $old);
        Assert::false(MediaHelper::hasSandbox($this->file));
        MediaHelper::addSandbox($this->file);
        Assert::true(MediaHelper::hasSandbox($this->file));
        Assert::true(str_starts_with(file_get_contents($this->file), $old), "the old content is kept");
        $once = file_get_contents($this->file);
        MediaHelper::addSandbox($this->file);
        Assert::equals($once, file_get_contents($this->file), "a second call changes nothing");
    }

    public function testAddSandboxCreatesAMissingFile(): void
    {
        unlink($this->file);
        MediaHelper::addSandbox($this->file);
        Assert::contains("Require all denied", file_get_contents($this->file));
        Assert::true(MediaHelper::hasSandbox($this->file));
    }

    public function testCollectStats(): void
    {
        $dir = sys_get_temp_dir() . "/test_mediastats_" . uniqid();
        mkdir("$dir/photos/2026", 0755, true);
        file_put_contents("$dir/a.jpg", str_repeat("x", 300));
        file_put_contents("$dir/photos/b.PNG", str_repeat("x", 500));
        file_put_contents("$dir/photos/2026/c.pdf", str_repeat("x", 100));
        file_put_contents("$dir/photos/notes.xyz", "x");
        file_put_contents("$dir/.htaccess", str_repeat("x", 1000));
        try {
            $stats = MediaHelper::collectStats($dir, 2);
            Assert::equals(4, $stats['files'], "the dotfile is left out");
            Assert::equals(2, $stats['folders']);
            Assert::equals(901, $stats['size']);
            Assert::equals(['count' => 2, 'size' => 800], $stats['types']['Images']);
            Assert::equals(['count' => 1, 'size' => 100], $stats['types']['Documents']);
            Assert::equals(['count' => 1, 'size' => 1], $stats['types']['Other']);
            Assert::equals(['Images', 'Documents', 'Other'], array_keys($stats['types']), "largest type first");
            Assert::equals([['path' => 'photos/b.PNG', 'size' => 500], ['path' => 'a.jpg', 'size' => 300]], $stats['largest']);
            Assert::equals(0, MediaHelper::collectStats("$dir/missing")['files']);
        } finally {
            exec("rm -rf " . escapeshellarg($dir));
        }
    }

    public function testFormatFileSize(): void
    {
        Assert::equals("512 B", MediaHelper::formatFileSize(512));
        Assert::equals("1.5 KB", MediaHelper::formatFileSize(1536));
        Assert::equals("2.5 GB", MediaHelper::formatFileSize(2684354560));
    }
}
