<?php

namespace Shaack\Tests;

require_once __DIR__ . "/TestHelper.php";

use Shaack\Reboot\Blueprint;
use Shaack\Reboot\Collection;
use Shaack\Reboot\Site;

class CollectionTest
{
    private string $tmpDir;
    private Site $site;

    public function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . "/test_collection_" . uniqid();
        mkdir($this->tmpDir . "/blueprints", 0755, true);
        mkdir($this->tmpDir . "/data/seminars", 0755, true);
        file_put_contents($this->tmpDir . "/blueprints/seminars.yml", <<<YAML
title: Seminars
sort: date
fields:
  title: {type: text, required: true}
  date: {type: date, required: true}
  location: {type: text}
  price: {type: number, min: 0}
  level: {type: select, options: [Beginner, Advanced], default: Beginner}
  body: {type: markdown, label: Description}
YAML);
        $this->entry("2027-04-02-light", "title: Light\ndate: 2027-04-02\nlocation: Hamburg\nprice: 340\nlevel: Advanced", "About **light**.");
        $this->entry("2027-03-12-basics", "title: Basics\ndate: 2027-03-12\nlocation: Bremen\nprice: 290", "First steps.");
        $this->entry("2020-01-10-past", "title: Past\ndate: 2020-01-10\nlocation: Bremen\nprice: 100", "");
        $this->site = TestHelper::createSiteStub($this->tmpDir, "");
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

    private function entry(string $slug, string $frontmatter, string $body): void
    {
        file_put_contents($this->tmpDir . "/data/seminars/" . $slug . ".md", "---\n" . $frontmatter . "\n---\n\n" . $body . "\n");
    }

    private function titles(Collection $collection): array
    {
        return array_map(fn($entry) => $entry->get("title"), $collection->toArray());
    }

    public function testLoadSortsAsTheBlueprintSays(): void
    {
        Assert::equals(["Past", "Basics", "Light"], $this->titles(Collection::load($this->site, "seminars")));
    }

    public function testDateIsReadAsText(): void
    {
        $entry = Collection::load($this->site, "seminars")->find("2027-03-12-basics");
        Assert::equals("2027-03-12", $entry->get("date"));
        Assert::equals("12.03.2027", $entry->date("date", "d.m.Y"));
    }

    public function testBodyAndDefault(): void
    {
        $entry = Collection::load($this->site, "seminars")->find("2027-04-02-light");
        Assert::equals("<p>About <strong>light</strong>.</p>", $entry->html("body"));
        Assert::equals("Beginner", Collection::load($this->site, "seminars")->find("2027-03-12-basics")->get("level"));
    }

    public function testGetEscapes(): void
    {
        $this->entry("2027-05-01-tags", "title: \"<b>Tags</b>\"\ndate: 2027-05-01", "");
        $entry = Collection::load($this->site, "seminars")->find("2027-05-01-tags");
        Assert::equals("&lt;b&gt;Tags&lt;/b&gt;", $entry->get("title"));
        Assert::equals("<b>Tags</b>", $entry->get("title", null, false));
        Assert::equals(290, Collection::load($this->site, "seminars")->find("2027-03-12-basics")->get("price"));
    }

    public function testWhereToday(): void
    {
        $next = Collection::load($this->site, "seminars")->where("date", ">=", "today");
        Assert::equals(["Basics", "Light"], $this->titles($next));
    }

    public function testWhereOperators(): void
    {
        $seminars = Collection::load($this->site, "seminars");
        Assert::equals(["Past", "Basics"], $this->titles($seminars->where("location", "=", "Bremen")));
        Assert::equals(["Light"], $this->titles($seminars->where("price", ">", 300)));
        Assert::equals(["Light"], $this->titles($seminars->where("body", "contains", "LIGHT")));
        Assert::equals(["Past", "Light"], $this->titles($seminars->where("title", "in", ["Past", "Light"])));
        Assert::throws(\InvalidArgumentException::class, fn() => $seminars->where("title", "~", "x"));
    }

    public function testQueriesReturnNewCollections(): void
    {
        $seminars = Collection::load($this->site, "seminars");
        $seminars->where("location", "=", "Hamburg");
        Assert::equals(3, count($seminars));
    }

    public function testSortLimitFirst(): void
    {
        $seminars = Collection::load($this->site, "seminars")->sortBy("price", "desc");
        Assert::equals(["Light", "Basics", "Past"], $this->titles($seminars));
        Assert::equals(["Basics"], $this->titles($seminars->limit(1, 1)));
        Assert::equals("Light", $seminars->first()->get("title"));
        Assert::null($seminars->limit(0)->first());
    }

    public function testEntriesWithoutTheFieldSortLast(): void
    {
        $this->entry("2027-06-01-free", "title: Free\ndate: 2027-06-01", "");
        Assert::equals(["Past", "Basics", "Light", "Free"], $this->titles(Collection::load($this->site, "seminars")->sortBy("price")));
        Assert::equals(["Light", "Basics", "Past", "Free"], $this->titles(Collection::load($this->site, "seminars")->sortBy("price", "desc")));
    }

    public function testInvalidEntryAndDraftAreLeftOutOnTheWebsite(): void
    {
        $this->entry("broken", "title: Broken\ndate: 12.03.2027\nprice: free", "");
        $this->entry("2027-07-01-draft", "title: Draft\ndate: 2027-07-01\ndraft: true", "");
        Assert::equals(3, count(Collection::load($this->site, "seminars")));
        $all = Collection::load($this->site, "seminars", true);
        Assert::equals(5, count($all));
        $broken = $all->find("broken");
        Assert::false($broken->isValid());
        Assert::equals(["date" => "Date must be a date like 2027-03-12", "price" => "Price must be a number"], $broken->getErrors());
        Assert::true($all->find("2027-07-01-draft")->isDraft());
    }

    public function testMissingBlueprintGivesAnEmptyCollection(): void
    {
        $collection = Collection::load($this->site, "courses");
        Assert::equals(0, count($collection));
        Assert::null($collection->getBlueprint());
    }

    public function testNameIsSanitized(): void
    {
        Assert::equals(0, count(Collection::load($this->site, "../blueprints/seminars")));
    }

    public function testSiteReadsACollectionOnce(): void
    {
        $first = $this->site->collection("seminars");
        $this->entry("2027-08-01-late", "title: Late\ndate: 2027-08-01", "");
        Assert::true($first === $this->site->collection("seminars"));
        Assert::equals(3, count($this->site->collection("seminars")));
    }

    public function testBlueprintValidation(): void
    {
        $blueprint = new Blueprint("test", ["fields" => [
            "name" => ["required" => true],
            "count" => ["type" => "number", "max" => 10],
            "active" => ["type" => "boolean"],
            "day" => ["type" => "date"],
            "kind" => ["type" => "select", "options" => ["a", "b"]],
            "odd" => ["type" => "colour"],
        ]]);
        Assert::equals("text", $blueprint->getFields()["odd"]["type"], "an unknown type is text");
        Assert::equals([], $blueprint->validate(["name" => "x", "count" => 3, "active" => false, "day" => "2027-02-28", "kind" => "b"]));
        Assert::equals([
            "name" => "Name is required",
            "count" => "Count must be at most 10",
            "active" => "Active must be true or false",
            "day" => "Day must be a date like 2027-03-12",
            "kind" => "Kind must be one of: a, b",
        ], $blueprint->validate(["count" => 11, "active" => "yes", "day" => "2027-02-30", "kind" => "c"]));
    }

    public function testBlueprintColumns(): void
    {
        Assert::equals(["title", "date", "location", "price"], Collection::load($this->site, "seminars")->getBlueprint()->getColumns());
        $blueprint = new Blueprint("test", ["columns" => ["b", "nope"], "fields" => ["a" => [], "b" => []]]);
        Assert::equals(["b"], $blueprint->getColumns());
    }

    public function testBlueprintSortDesc(): void
    {
        $blueprint = new Blueprint("test", ["sort" => "date desc"]);
        Assert::equals("date", $blueprint->getSortField());
        Assert::equals("desc", $blueprint->getSortOrder());
        Assert::equals("Test", $blueprint->getTitle());
    }

    public function testAllBlueprints(): void
    {
        file_put_contents($this->tmpDir . "/blueprints/authors.yml", "fields:\n  name: {required: true}\n");
        Assert::equals(["authors", "seminars"], array_keys(Blueprint::all($this->site)));
    }
}
