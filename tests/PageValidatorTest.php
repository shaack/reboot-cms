<?php

namespace Shaack\Tests;

require_once __DIR__ . "/TestHelper.php";

use Shaack\Reboot\AddOns\Admin\PageValidator;

class PageValidatorTest
{
    private function validate(string $source): array
    {
        $site = TestHelper::createSiteStub(__DIR__ . "/../site", "");
        return PageValidator::validate($site, $source);
    }

    public function testValidPageHasNoProblems(): void
    {
        Assert::equals([], $this->validate("<!-- text -->\n\n# Title\n\nSome text."));
    }

    public function testPageWithoutBlocksHasNoProblems(): void
    {
        Assert::equals([], $this->validate("# Just Markdown\n\nNo blocks."));
    }

    public function testMissingRequiredFieldHasTheLineOfItsBlock(): void
    {
        $problems = $this->validate("---\ntitle: Test\n---\n\n<!-- text -->\n\nText\n\n<!-- hero -->\n\nNo heading");
        Assert::equals(3, count($problems), "heading, link and label are missing");
        Assert::equals(3, count(array_filter($problems, fn($p) => $p['block'] === 'hero' && $p['line'] === 8)));
        Assert::true(str_contains($problems[0]['message'], "Hero heading"), $problems[0]['message']);
        Assert::false(str_contains($problems[0]['message'], "Block 'hero'"), "the block name is not repeated");
    }

    public function testUnknownBlockIsReported(): void
    {
        $problems = $this->validate("<!-- heor -->\n\n# Title");
        Assert::equals(1, count($problems));
        Assert::equals('heor', $problems[0]['block']);
        Assert::equals(0, $problems[0]['line']);
        Assert::contains("no block 'heor'", $problems[0]['message']);
    }

    public function testDisabledBlockIsNotChecked(): void
    {
        Assert::equals([], $this->validate("<!-- text -->\n\nText\n\n<!-- hero: {disabled: true} -->\n\nNo heading"));
    }

    public function testNoteIsNoBlock(): void
    {
        Assert::equals([], $this->validate("<!-- text -->\n\nText\n\n<!-- TODO: check prices -->"));
    }

    public function testTextBeforeTheFirstBlockIsReported(): void
    {
        $problems = $this->validate("\nLost text\n\n<!-- text -->\n\nText");
        Assert::equals(1, count($problems));
        Assert::equals(null, $problems[0]['block']);
        Assert::equals(1, $problems[0]['line']);
    }
}
