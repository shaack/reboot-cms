<?php

namespace Shaack\Tests;

use Shaack\Logger;
use Shaack\Reboot\AddOn;
use Shaack\Reboot\AddOns\Admin\AdminMenu;

class AdminMenuTest
{
    private const STRUCTURE = [
        'Pages' => '/pages',
        'Media' => '/media',
        'Admin' => [
            'Site Configuration' => '/config',
            'Update' => '/update',
        ],
    ];

    /**
     * An AddOn instance that returns the given menu, created without running
     * the constructor so no Reboot or Site is needed.
     */
    private function addOn(array $menu): AddOn
    {
        $instance = (new \ReflectionClass(MenuAddOnStub::class))->newInstanceWithoutConstructor();
        $instance->menu = $menu;
        return $instance;
    }

    public function testWithoutAddOnsReturnsStructureUnchanged(): void
    {
        Assert::equals(self::STRUCTURE, AdminMenu::build(self::STRUCTURE, []));
    }

    public function testAddOnWithoutMenuAddsNothing(): void
    {
        $result = AdminMenu::build(self::STRUCTURE, [$this->addOn([])]);
        Assert::equals(self::STRUCTURE, $result);
    }

    public function testFlatEntryIsInsertedBeforeFirstDropdown(): void
    {
        $result = AdminMenu::build(self::STRUCTURE, [$this->addOn(['Downloads' => '/downloads'])]);
        Assert::equals(['Pages', 'Media', 'Downloads', 'Admin'], array_keys($result));
        Assert::equals('/downloads', $result['Downloads']);
    }

    public function testFlatEntryIsAppendedWhenThereIsNoDropdown(): void
    {
        $structure = ['Pages' => '/pages'];
        $result = AdminMenu::build($structure, [$this->addOn(['Downloads' => '/downloads'])]);
        Assert::equals(['Pages' => '/pages', 'Downloads' => '/downloads'], $result);
    }

    public function testNestedEntryExtendsExistingDropdown(): void
    {
        $result = AdminMenu::build(self::STRUCTURE, [$this->addOn(['Admin' => ['Statistics' => '/statistics']])]);
        Assert::equals(['Pages', 'Media', 'Admin'], array_keys($result));
        Assert::equals(['Site Configuration', 'Update', 'Statistics'], array_keys($result['Admin']));
        Assert::equals('/statistics', $result['Admin']['Statistics']);
    }

    public function testNestedEntryOpensNewDropdownAtTheEnd(): void
    {
        $result = AdminMenu::build(self::STRUCTURE, [$this->addOn(['Tools' => ['Backup' => '/backup']])]);
        Assert::equals(['Pages', 'Media', 'Admin', 'Tools'], array_keys($result));
        Assert::equals(['Backup' => '/backup'], $result['Tools']);
    }

    public function testStaticEntriesWin(): void
    {
        $addOn = $this->addOn([
            'Pages' => '/elsewhere',
            'Admin' => ['Update' => '/elsewhere'],
        ]);
        $result = AdminMenu::build(self::STRUCTURE, [$addOn]);
        Assert::equals('/pages', $result['Pages']);
        Assert::equals('/update', $result['Admin']['Update']);
    }

    public function testEntriesOfSeveralAddOnsKeepTheirOrder(): void
    {
        $result = AdminMenu::build(self::STRUCTURE, [
            $this->addOn(['Downloads' => '/downloads']),
            $this->addOn(['Forms' => '/forms', 'Admin' => ['Logs' => '/logs']]),
        ]);
        Assert::equals(['Pages', 'Media', 'Downloads', 'Forms', 'Admin'], array_keys($result));
        Assert::equals('/logs', $result['Admin']['Logs']);
    }

    public function testInvalidEntriesAreSkipped(): void
    {
        $addOn = $this->addOn([
            'NoSlash' => 'downloads',
            'Protocol' => '//evil.example',
            'NotAString' => 42,
            '' => '/empty-label',
            'Mixed' => ['Good' => '/good', 'Bad' => 'bad'],
            'OnlyBad' => ['Bad' => 'bad'],
        ]);
        $level = Logger::getLevel();
        Logger::setLevel(3); // the invalid entries are logged, keep the test output clean
        try {
            $result = AdminMenu::build(self::STRUCTURE, [$addOn]);
        } finally {
            Logger::setLevel($level);
        }
        Assert::equals(['Pages', 'Media', 'Admin', 'Mixed'], array_keys($result));
        Assert::equals(['Good' => '/good'], $result['Mixed']);
    }
}

class MenuAddOnStub extends AddOn
{
    public array $menu = [];

    public function getAdminMenu(): array
    {
        return $this->menu;
    }
}
