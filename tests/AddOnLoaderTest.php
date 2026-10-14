<?php

namespace Shaack\Tests;

require_once __DIR__ . "/TestHelper.php";

use Shaack\Logger;
use Shaack\Reboot\AddOn;
use Shaack\Reboot\AddOnLoader;
use Shaack\Reboot\Reboot;
use Shaack\Reboot\Site;

class AddOnLoaderTest
{
    private string $tmpDir;
    private Reboot $reboot;
    private Site $site;
    private int $logLevel;

    public function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . "/test_addonloader_" . uniqid();
        mkdir($this->tmpDir . "/site/addons", 0755, true);
        mkdir($this->tmpDir . "/core/addons", 0755, true);
        $rebootRef = new \ReflectionClass(Reboot::class);
        $this->reboot = $rebootRef->newInstanceWithoutConstructor();
        $rebootRef->getProperty('baseFsPath')->setValue($this->reboot, $this->tmpDir);
        $rebootRef->getProperty('baseWebPath')->setValue($this->reboot, "");
        $rebootRef->getProperty('config')->setValue($this->reboot, []);
        $this->site = TestHelper::createSiteStub($this->tmpDir . "/site", "");
        $this->logLevel = Logger::getLevel();
        Logger::setLevel(3); // the tests provoke logged errors on purpose
    }

    public function tearDown(): void
    {
        Logger::setLevel($this->logLevel);
        if (is_dir($this->tmpDir)) {
            $this->removeDir($this->tmpDir);
        }
    }

    private function removeDir(string $dir): void
    {
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($dir);
    }

    /**
     * Writes an AddOn class. Class names must be unique per process, so every
     * test uses its own.
     */
    private function writeAddOn(string $file, string $name, string $namespace, string $body = ""): void
    {
        @mkdir(dirname($file), 0755, true);
        file_put_contents($file, "<?php\nnamespace $namespace;\nuse Shaack\\Reboot\\AddOn;\nclass $name extends AddOn { $body }\n");
    }

    public function testLoadsFolderAddOnFromSite(): void
    {
        $dir = $this->tmpDir . "/site/addons/FolderOne";
        $this->writeAddOn("$dir/FolderOne.php", "FolderOne", "Shaack\\Reboot\\AddOns");
        $addOn = AddOnLoader::load($this->reboot, $this->site, "FolderOne");
        Assert::true($addOn instanceof AddOn);
        Assert::equals("FolderOne", $addOn->getName());
        Assert::equals($dir, $addOn->getDir());
    }

    public function testLoadsFolderAddOnFromCore(): void
    {
        $dir = $this->tmpDir . "/core/addons/CoreOne";
        $this->writeAddOn("$dir/CoreOne.php", "CoreOne", "Shaack\\Reboot\\AddOns");
        $addOn = AddOnLoader::load($this->reboot, $this->site, "CoreOne");
        Assert::true($addOn instanceof AddOn);
        Assert::equals($dir, $addOn->getDir());
    }

    public function testSiteAddOnOverridesCoreAddOn(): void
    {
        $this->writeAddOn($this->tmpDir . "/core/addons/Shadowed/Shadowed.php", "Shadowed", "Shaack\\Reboot\\AddOns",
            "public function who() { return 'core'; }");
        $this->writeAddOn($this->tmpDir . "/site/addons/Shadowed/Shadowed.php", "Shadowed", "Shaack\\Reboot\\AddOns",
            "public function who() { return 'site'; }");
        $addOn = AddOnLoader::load($this->reboot, $this->site, "Shadowed");
        Assert::equals('site', $addOn->who());
    }

    public function testLoadsDeprecatedSingleFileAddOn(): void
    {
        $this->writeAddOn($this->tmpDir . "/site/addons/LegacyOne.php", "LegacyOne", "Shaack\\Reboot");
        $addOn = AddOnLoader::load($this->reboot, $this->site, "LegacyOne");
        Assert::true($addOn instanceof AddOn);
        Assert::null($addOn->getDir());
        Assert::equals([], $addOn->getAdminPages());
        Assert::equals([], $addOn->getAdminMenu());
    }

    public function testAutoloadsHelperClassesFromSrc(): void
    {
        $dir = $this->tmpDir . "/site/addons/WithSrc";
        $this->writeAddOn("$dir/WithSrc.php", "WithSrc", "Shaack\\Reboot\\AddOns",
            "public function helper() { return new \\Shaack\\Reboot\\AddOns\\WithSrc\\Util\\Helper(); }");
        mkdir("$dir/src/Util", 0755, true);
        file_put_contents("$dir/src/Util/Helper.php", "<?php\nnamespace Shaack\\Reboot\\AddOns\\WithSrc\\Util;\nclass Helper { public \$ok = true; }\n");
        $addOn = AddOnLoader::load($this->reboot, $this->site, "WithSrc");
        Assert::true($addOn->helper()->ok);
    }

    public function testReturnsNullForUnknownAddOn(): void
    {
        Assert::null(AddOnLoader::load($this->reboot, $this->site, "DoesNotExist"));
    }

    public function testRejectsInvalidNames(): void
    {
        Assert::null(AddOnLoader::load($this->reboot, $this->site, "../etc/passwd"));
        Assert::null(AddOnLoader::load($this->reboot, $this->site, "1Numeric"));
        Assert::false(AddOnLoader::isValidName("With-Dash"));
        Assert::true(AddOnLoader::isValidName("Snake_Case2"));
    }

    public function testRejectsClassThatIsNoAddOn(): void
    {
        $dir = $this->tmpDir . "/site/addons/NotAnAddOn";
        mkdir($dir, 0755, true);
        file_put_contents("$dir/NotAnAddOn.php", "<?php\nnamespace Shaack\\Reboot\\AddOns;\nclass NotAnAddOn {}\n");
        Assert::null(AddOnLoader::load($this->reboot, $this->site, "NotAnAddOn"));
    }

    public function testAdminPagesFollowTheFolderConvention(): void
    {
        $dir = $this->tmpDir . "/site/addons/WithAdmin";
        $this->writeAddOn("$dir/WithAdmin.php", "WithAdmin", "Shaack\\Reboot\\AddOns");
        mkdir("$dir/admin/api", 0755, true);
        file_put_contents("$dir/admin/index.php", "index");
        file_put_contents("$dir/admin/log.php", "log");
        file_put_contents("$dir/admin/api/list.php", "list");
        file_put_contents("$dir/admin/notes.txt", "ignored");
        file_put_contents("$dir/admin/bad name.php", "ignored");
        $addOn = AddOnLoader::load($this->reboot, $this->site, "WithAdmin");
        Assert::equals('/withadmin', $addOn->getAdminPath());
        Assert::equals([
            '/withadmin' => "$dir/admin/index.php",
            '/withadmin/api/list' => "$dir/admin/api/list.php",
            '/withadmin/log' => "$dir/admin/log.php",
        ], $addOn->getAdminPages());
        Assert::equals(['WithAdmin' => '/withadmin'], $addOn->getAdminMenu());
    }

    public function testNoMenuEntryWithoutAdminIndex(): void
    {
        $dir = $this->tmpDir . "/site/addons/NoIndex";
        $this->writeAddOn("$dir/NoIndex.php", "NoIndex", "Shaack\\Reboot\\AddOns");
        mkdir("$dir/admin", 0755, true);
        file_put_contents("$dir/admin/log.php", "log");
        $addOn = AddOnLoader::load($this->reboot, $this->site, "NoIndex");
        Assert::equals(['/noindex/log' => "$dir/admin/log.php"], $addOn->getAdminPages());
        Assert::equals([], $addOn->getAdminMenu());
    }

    public function testSiteConstructorLoadsAddOnsViaLoader(): void
    {
        $this->writeAddOn($this->tmpDir . "/site/addons/ViaSite/ViaSite.php", "ViaSite", "Shaack\\Reboot\\AddOns");
        file_put_contents($this->tmpDir . "/site/config.yml", "addons: [ ViaSite, Missing ]\n");
        $site = new Site($this->reboot, "/site", "");
        Assert::equals(['ViaSite'], array_keys($site->getAddOns()));
        Assert::true($site->getAddOn('ViaSite') instanceof AddOn);
    }
}
