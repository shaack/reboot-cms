<?php

namespace Shaack\Tests;

use Shaack\Reboot\AddOns\Admin\DataEntryHandler;
use Shaack\Reboot\Blueprint;

class DataEntryHandlerTest
{
    private string $tmpDir;
    private DataEntryHandler $handler;

    public function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . "/test_data_entry_" . uniqid();
        mkdir($this->tmpDir . "/data/seminars", 0755, true);
        $blueprint = new Blueprint("seminars", ["fields" => [
            "title" => ["type" => "text", "required" => true],
            "date" => ["type" => "date"],
            "time" => ["type" => "text", "default" => "10:00-17:00"],
            "price" => ["type" => "number"],
            "booked-out" => ["type" => "boolean"],
            "teaser" => ["type" => "textarea"],
            "body" => ["type" => "markdown"],
        ]]);
        $this->handler = new DataEntryHandler($blueprint, $this->tmpDir . "/data", $this->tmpDir . "/history", 5);
    }

    public function tearDown(): void
    {
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->tmpDir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($this->tmpDir);
    }

    private function content(string $slug): string
    {
        return file_get_contents($this->tmpDir . "/data/seminars/" . $slug . ".md");
    }

    public function testNewEntryGetsItsNameFromDateAndTitle(): void
    {
        $slug = $this->handler->save(null, ["title" => "Fotografie für Einsteiger", "date" => "2027-03-12"], false);
        Assert::equals("2027-03-12-fotografie-fuer-einsteiger", $slug);
        $again = $this->handler->save(null, ["title" => "Fotografie für Einsteiger", "date" => "2027-03-12"], false);
        Assert::equals("2027-03-12-fotografie-fuer-einsteiger-2", $again);
    }

    public function testFileIsWrittenInBlueprintOrderWithoutEmptyFields(): void
    {
        $slug = $this->handler->save(null, [
            "price" => "290", "title" => "Basics", "date" => "2027-03-12", "time" => "",
            "booked-out" => "0", "teaser" => "Line one\r\nLine two", "body" => "Some **text**.\r\n",
        ], false);
        Assert::equals("---\ntitle: Basics\ndate: 2027-03-12\nprice: 290\nteaser: |-\n  Line one\n  Line two\n---\n\nSome **text**.\n", $this->content($slug));
    }

    public function testTextIsQuotedOnlyWhenNeeded(): void
    {
        $slug = $this->handler->save(null, ["title" => "Photo basics, a day", "time" => "09:30-16:00", "teaser" => "Note: yes"], false);
        Assert::equals("---\ntitle: Photo basics, a day\ntime: 09:30-16:00\nteaser: 'Note: yes'\n---\n", $this->content($slug));
        $slug = $this->handler->save(null, ["title" => "true"], false);
        Assert::contains("title: 'true'", $this->content($slug));
    }

    public function testValuesAreTyped(): void
    {
        $values = $this->handler->fromInput(["title" => " A ", "price" => "12.5", "booked-out" => "1", "date" => "soon"]);
        Assert::equals("A", $values["title"]);
        Assert::equals(12.5, $values["price"]);
        Assert::true($values["booked-out"]);
        Assert::equals("soon", $values["date"], "a wrong value stays for the validation");
        Assert::equals("free", $this->handler->fromInput(["price" => "free"])["price"]);
    }

    public function testUnknownValuesAndDraftAreKept(): void
    {
        file_put_contents($this->tmpDir . "/data/seminars/old.md", "---\ntitle: Old\nspeaker: Ann\n---\n\nText\n");
        $this->handler->save("old", ["title" => "New", "body" => "Text"], true);
        Assert::equals("---\ntitle: New\ndraft: true\nspeaker: Ann\n---\n\nText\n", $this->content("old"));
        $this->handler->save("old", ["title" => "New", "body" => "Text"], false);
        Assert::false(str_contains($this->content("old"), "draft"));
    }

    public function testReadGivesTheFileValuesWithoutDefaults(): void
    {
        file_put_contents($this->tmpDir . "/data/seminars/e.md", "---\ntitle: E\ndate: 2027-01-02\n---\n\nBody\n");
        [$values, $body] = $this->handler->read("e");
        Assert::equals(["title" => "E", "date" => "2027-01-02"], $values);
        Assert::equals("Body", $body);
    }

    public function testSaveAndDeleteKeepTheOldVersion(): void
    {
        $slug = $this->handler->save(null, ["title" => "Keep", "date" => "2027-01-02"], false);
        $this->handler->save($slug, ["title" => "Kept", "date" => "2027-01-02"], false);
        Assert::equals(1, count(glob($this->tmpDir . "/history/seminars/" . $slug . "/*.md")));
        $this->handler->delete($slug);
        Assert::null($this->handler->file($slug));
        Assert::true(count(glob($this->tmpDir . "/history/seminars/" . $slug . "/*.md")) >= 1);
    }

    public function testSlugCannotLeaveTheCollection(): void
    {
        Assert::null($this->handler->file("../../etc/passwd"));
        Assert::throws(\InvalidArgumentException::class, fn() => $this->handler->delete("../x"));
    }
}
