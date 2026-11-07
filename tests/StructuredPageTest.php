<?php

namespace Shaack\Tests;

require_once __DIR__ . "/TestHelper.php";

use Shaack\Reboot\AddOns\Admin\FieldInput;
use Shaack\Reboot\AddOns\Admin\PageConverter;
use Shaack\Reboot\AddOns\Admin\StructuredPageHandler;
use Shaack\Reboot\Blueprint;
use Shaack\Reboot\Page;
use Shaack\Reboot\Reboot;
use Shaack\Reboot\Site;
use Shaack\Reboot\YamlWriter;
use Symfony\Component\Yaml\Yaml;

/**
 * Structured pages: YAML files of blocks with fields, the field types list,
 * blocks and entry, and how the admin reads and writes them.
 */
class StructuredPageTest
{
    private string $tmpDir;
    private Site $site;
    private Page $page;

    public function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . "/test_structured_" . uniqid();
        foreach (["pages", "blocks", "blueprints", "data/seminars"] as $dir) {
            mkdir($this->tmpDir . "/" . $dir, 0755, true);
        }
        $this->block("intro", "title: Intro\nfields:\n  heading: {type: text, required: true}\n  text: {type: markdown}\n  tone: {type: text, default: calm}\n",
            '<h1><?= $block->get("heading") ?></h1><?= $block->html("text") ?><i><?= $block->get("tone") ?></i>');
        $this->block("cards", "fields:\n  cards:\n    type: list\n    min: 1\n    fields:\n      caption: {type: text, required: true}\n      day: {type: date}\n",
            '<?php foreach ($block->items("cards") as $card) { ?><b><?= $card->get("caption") ?>|<?= $card->get("day") ?></b><?php } ?>');
        $this->block("focus", "fields:\n  seminar: {type: entry, collection: seminars}\n",
            '<?php $s = $block->entry("seminar"); ?><em><?= $s ? $s->get("title") : "none" ?></em>');
        file_put_contents($this->tmpDir . "/blueprints/seminars.yml", "fields:\n  title: {type: text, required: true}\n");
        file_put_contents($this->tmpDir . "/data/seminars/basics.md", "---\ntitle: Basics\n---\n");

        $rebootRef = new \ReflectionClass(Reboot::class);
        $reboot = $rebootRef->newInstanceWithoutConstructor();
        $rebootRef->getProperty('baseFsPath')->setValue($reboot, $this->tmpDir);
        $rebootRef->getProperty('baseWebPath')->setValue($reboot, "");
        $rebootRef->getProperty('config')->setValue($reboot, ["logLevel" => 2]);
        $this->site = TestHelper::createSiteStub($this->tmpDir, "");
        $this->page = new Page($reboot, $this->site);
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

    /**
     * A block type of structured pages, a folder with template and blueprint.
     */
    private function block(string $name, string $blueprint, string $template): void
    {
        mkdir($this->tmpDir . "/blocks/" . $name);
        file_put_contents($this->tmpDir . "/blocks/" . $name . "/" . $name . ".yml", $blueprint);
        file_put_contents($this->tmpDir . "/blocks/" . $name . "/" . $name . ".php", $template);
    }

    private function writePage(string $name, string $yaml): string
    {
        $file = $this->tmpDir . "/pages/" . $name . ".yml";
        file_put_contents($file, $yaml);
        return $file;
    }

    public function testBlocksRenderWithTheirFields(): void
    {
        $this->writePage("about", "title: About\nblocks:\n  - type: intro\n    heading: Hello <you>\n    text: Some **bold**\n  - type: intro\n    disabled: true\n    heading: Off\n");
        $html = $this->page->render("/about");
        Assert::contains("<h1>Hello &lt;you&gt;</h1>", $html);
        Assert::contains("<strong>bold</strong>", $html);
        Assert::contains("<i>calm</i>", $html, "the default of a field applies");
        Assert::false(str_contains($html, "Off"), "a block switched off is not shown");
        Assert::equals("About", $this->page->getConfig()["title"]);
        Assert::false(isset($this->page->getConfig()["blocks"]), "the blocks are no config of the page");
    }

    public function testListItemsAndEntries(): void
    {
        $this->writePage("list", "title: L\nblocks:\n  - type: cards\n    cards:\n      - caption: One\n        day: 2027-03-12\n      - caption: Two\n  - type: focus\n    seminar: basics\n  - type: focus\n    seminar: gone\n");
        $html = $this->page->render("/list");
        Assert::contains("<b>One|2027-03-12</b><b>Two|</b>", $html, "a date in a list item is read as text");
        Assert::contains("<em>Basics</em>", $html);
        Assert::contains("<em>none</em>", $html, "an entry that is gone gives null");
    }

    public function testMarkdownPageWinsOverStructuredPage(): void
    {
        $this->writePage("both", "title: Y\nblocks: []\n");
        file_put_contents($this->tmpDir . "/pages/both.md", "Markdown here");
        file_put_contents($this->tmpDir . "/blocks/text.php", '<?= $block->content() ?>');
        Assert::contains("Markdown here", $this->page->render("/both"));
    }

    public function testInvalidYamlRendersNothing(): void
    {
        $this->writePage("broken", "title: [unclosed\n");
        Assert::equals("", $this->page->render("/broken"));
    }

    public function testValidationOfListsBlocksAndEntries(): void
    {
        $blueprint = Blueprint::forPage($this->site);
        $values = $blueprint->normalize(Yaml::parse("title: T\nblocks:\n  - type: cards\n    cards:\n      - day: \"2027-02-30\"\n  - type: nope\n  - type: intro\n  - heading: no type\n  - type: nope\n    disabled: true\n"));
        Assert::equals([
            "blocks.0.cards.0.caption" => "Cards: Cards 1: Caption is required",
            "blocks.0.cards.0.day" => "Cards: Cards 1: Day must be a date like 2027-03-12",
            "blocks.1" => "Block 2: there is no block type 'nope' with a blueprint, site/blocks/nope/nope.yml",
            "blocks.2.heading" => "Intro: Heading is required",
            "blocks.3" => "Block 4 has no type",
        ], $blueprint->validate($values));
        $list = new Blueprint("x", ["fields" => ["items" => ["type" => "list", "min" => 2, "fields" => ["a" => []]]]]);
        Assert::equals(["items" => "Items needs at least 2 items"], $list->validate(["items" => [["a" => "1"]]]));
    }

    public function testPageBlueprintAndBlockTypes(): void
    {
        Assert::equals(["title", "description", "blocks"], array_keys(Blueprint::forPage($this->site)->getFields()));
        Assert::equals(["cards", "focus", "intro"], array_keys(Blueprint::blockTypes($this->site)));
        file_put_contents($this->tmpDir . "/blueprints/page.yml", "fields:\n  title: {type: text}\n  body: {type: blocks}\n");
        Assert::equals(["title", "body"], array_keys(Blueprint::forPage($this->site)->getFields()));
        Assert::equals(["seminars"], array_keys(Blueprint::all($this->site)), "page.yml is no collection");
    }

    public function testBlockTypesAreFilesOrFolders(): void
    {
        file_put_contents($this->tmpDir . "/blocks/text.php", "flat");
        file_put_contents($this->tmpDir . "/blocks/intro.php", "flat intro");
        file_put_contents($this->tmpDir . "/blocks/_helpers.php", "<?php");
        mkdir($this->tmpDir . "/blocks/empty");
        Assert::equals(["cards", "focus", "intro", "text"], \Shaack\Reboot\Block::names($this->site), "no helpers, no folder without template");
        Assert::equals($this->tmpDir . "/blocks/intro/intro.php", \Shaack\Reboot\Block::templateFile($this->site, "intro"), "the folder wins");
        Assert::equals($this->tmpDir . "/blocks/text.php", \Shaack\Reboot\Block::templateFile($this->site, "text"));
        Assert::null(\Shaack\Reboot\Block::templateFile($this->site, "../pages/x"));
        Assert::null(Blueprint::forBlock($this->site, "text"), "a single file is a Markdown block");
    }

    public function testFormInputBecomesCleanYaml(): void
    {
        $blueprint = Blueprint::forPage($this->site);
        $values = FieldInput::fromInput($blueprint, [
            "title" => " About ",
            "description" => "",
            "blocks" => [
                "n2" => ["_type" => "cards", "_disabled" => "0", "_extra" => "", "cards" => [
                    "5" => ["caption" => "Second", "day" => "2027-03-12"],
                    "1" => ["caption" => "", "day" => ""],
                    "0" => ["caption" => "Third", "day" => ""],
                ]],
                "0" => ["_type" => "intro", "_disabled" => "1", "_extra" => '{"inverse":true,"heading":"ignored"}', "heading" => "Hi", "text" => "Line\r\nTwo", "tone" => ""],
                "x" => ["_type" => "../evil"],
            ],
        ]);
        $yaml = YamlWriter::dump(FieldInput::clean($blueprint, $values));
        Assert::equals("title: About\nblocks:\n  - type: cards\n    cards:\n      - caption: Second\n        day: 2027-03-12\n      - caption: Third\n"
            . "  - type: intro\n    disabled: true\n    heading: Hi\n    text: |-\n      Line\n      Two\n    inverse: true\n", $yaml,
            "posted order is kept, empty items and fields go, unknown values of a block stay");
    }

    public function testHandlerKeepsUnknownValuesAndHistory(): void
    {
        $file = $this->writePage("keep", "title: Old\nlayout: wide\nblocks: []\n");
        $handler = new StructuredPageHandler(Blueprint::forPage($this->site), $this->tmpDir . "/pages", $this->tmpDir . "/history", 5);
        Assert::true($handler->save($file, ["title" => "New", "blocks" => [["_type" => "intro", "heading" => "Hi"]]]));
        Assert::equals("title: New\nblocks:\n  - type: intro\n    heading: Hi\nlayout: wide\n", file_get_contents($file));
        Assert::false($handler->save($file, ["title" => "New", "blocks" => [["_type" => "intro", "heading" => "Hi"]]]), "nothing changed");
        Assert::equals(1, count(glob($this->tmpDir . "/history/keep.yml/*")));
        Assert::equals([], $handler->validate($file));
    }

    public function testHandlerRefusesToOverwriteBrokenYaml(): void
    {
        $file = $this->writePage("broken", "title: [unclosed\n");
        $handler = new StructuredPageHandler(Blueprint::forPage($this->site), $this->tmpDir . "/pages", $this->tmpDir . "/history", 5);
        Assert::throws(\RuntimeException::class, fn() => $handler->save($file, ["title" => "x"]));
        Assert::equals("title: [unclosed\n", file_get_contents($file));
    }

    public function testFormValuesTurnTimestampsBackIntoDates(): void
    {
        $values = FieldInput::forForm(Blueprint::forPage($this->site), Yaml::parse("blocks:\n  - type: cards\n    cards:\n      - day: 2027-03-12\n"));
        Assert::equals("2027-03-12", $values["blocks"][0]["cards"][0]["day"]);
    }

    public function testYamlWriterReadsBackTheSame(): void
    {
        $data = ["title" => "Hello, world", "n" => "y", "quoted" => "a: b", "flag" => true, "count" => 3, "price" => 12.5,
            "text" => "Line one\n\n- item\n", "list" => [["a" => "1", "b" => [1, 2]], ["c" => "#tag"]], "empty" => []];
        Assert::equals($data, Yaml::parse(YamlWriter::dump($data)));
        Assert::contains("date: 2027-03-12\n", YamlWriter::dump(["date" => YamlWriter::plain("2027-03-12")]));
    }

    public function testMarkdownBlockInAStructuredPage(): void
    {
        file_put_contents($this->tmpDir . "/blocks/text.php", '<div class="<?= $block->getConfig()["tone"] ?? "" ?>"><?= $block->content() ?></div>');
        $this->writePage("mixed", "title: M\nblocks:\n  - type: text\n    tone: loud\n    markdown: |-\n      ## Plain **Markdown**\n  - type: text\n    markdown: Off\n    disabled: true\n");
        $html = $this->page->render("/mixed");
        Assert::contains('<div class="loud"><h2>Plain <strong>Markdown</strong></h2></div>', $html);
        Assert::false(str_contains($html, "Off"));
        $blueprint = Blueprint::forPage($this->site);
        Assert::equals([], $blueprint->validate($blueprint->normalize(["title" => "M", "blocks" => [["type" => "text", "markdown" => "x"]]])));
        Assert::equals(["blocks.0" => "Block 1: there is no block type 'gone' in site/blocks/"],
            $blueprint->validate(["title" => "M", "blocks" => [["type" => "gone", "markdown" => "x"]]]));
    }

    public function testMarkdownBlockFromTheForm(): void
    {
        $blueprint = Blueprint::forPage($this->site);
        $values = FieldInput::fromInput($blueprint, ["title" => "T", "blocks" => [
            ["_type" => "text", "_extra" => '{"tone":"loud","markdown":"stale"}', "_markdown" => "# New\r\n\r\nText\n"],
        ]]);
        Assert::equals("title: T\nblocks:\n  - type: text\n    tone: loud\n    markdown: |-\n      # New\n\n      Text\n",
            YamlWriter::dump(FieldInput::clean($blueprint, $values)), "the config first, the Markdown last, the posted Markdown wins");
    }

    public function testConvertMarkdownPage(): void
    {
        $this->block("intro2", "fields:\n  heading: {type: text}\n  text: {type: markdown}\n  shots:\n    type: list\n    fields:\n      src: {type: image}\n      alt: {type: text}\n  cols:\n    type: list\n    fields:\n      title: {type: text}\n      body: {type: markdown}\n"
            . "convert:\n  from: hero\n  fields:\n    heading: \"/h1[part(1)]\"\n    text: {part: 2}\n    shots:\n      each: \"//li/img\"\n      fields:\n        src: \"@src\"\n        alt: \"@alt\"\n"
            . "    cols:\n      each: part\n      fields:\n        title: \"//h1\"\n        body: {part: 1, strip-heading: true}\n", "");
        $source = "---\ntitle: Home\nauthor: me\n---\n\nLost\n\n<!-- hero: {inverse: true} -->\n\n# Hello\n\n---\n\nSome **text**\n\n- ![A](/a.png)\n- ![B](/b.png)\n\n<!-- text-image: {image-position: left} -->\n\n## Keep\n";
        $result = (new PageConverter($this->site))->convert($source);
        Assert::equals([["from" => "hero", "to" => "intro2"], ["from" => "text-image", "to" => null]], $result["report"]);
        Assert::equals(1, count($result["notes"]), "the text before the first block");
        $blocks = $result["values"]["blocks"];
        Assert::equals("Hello", $blocks[0]["heading"]);
        Assert::equals("Some **text**\n\n- ![A](/a.png)\n- ![B](/b.png)", $blocks[0]["text"]);
        Assert::equals([["src" => "/a.png", "alt" => "A"], ["src" => "/b.png", "alt" => "B"]], $blocks[0]["shots"]);
        Assert::equals("Hello", $blocks[0]["cols"][0]["title"]);
        Assert::false(isset($blocks[0]["cols"][0]["body"]), "the heading line is left out, nothing remains");
        Assert::equals("Some **text**\n\n- ![A](/a.png)\n- ![B](/b.png)", $blocks[0]["cols"][1]["body"]);
        Assert::true($blocks[0]["inverse"], "the config stays with the block");
        Assert::equals(["type" => "text-image", "image-position" => "left", "markdown" => "## Keep"], $blocks[1]);
        Assert::equals(["title", "blocks", "author"], array_keys($result["values"]));
        Assert::equals("me", $result["values"]["author"], "all of the frontmatter is kept");
    }

    public function testConvertPageWithoutBlocks(): void
    {
        $result = (new PageConverter($this->site))->convert("# Just text\n\nMore");
        Assert::equals([["type" => "text", "markdown" => "# Just text\n\nMore"]], $result["values"]["blocks"]);
    }

    public function testConvertWholeBlockWithPartZero(): void
    {
        $this->block("content", "fields:\n  text: {type: markdown}\nconvert:\n  from: text\n  fields:\n    text: {part: 0}\n", "");
        $result = (new PageConverter($this->site))->convert("<!-- text -->\n\n# A\n\n---\n\nB\n");
        Assert::equals([["type" => "content", "text" => "# A\n\n---\n\nB"]], $result["values"]["blocks"], "the separators stay in the text");
    }

    public function testEntriesArePagesWithUrl(): void
    {
        mkdir($this->tmpDir . "/data/blog");
        file_put_contents($this->tmpDir . "/blueprints/blog.yml", "url: blog\nfields:\n  title: {type: text, required: true}\n  body: {type: markdown}\n");
        file_put_contents($this->tmpDir . "/blueprints/blog.php", '<h1><?= $entry->get("title") ?></h1><?= $entry->html("body") ?>');
        file_put_contents($this->tmpDir . "/data/blog/first.md", "---\ntitle: First\n---\n\nHello\n");
        file_put_contents($this->tmpDir . "/data/blog/draft.md", "---\ntitle: Draft\ndraft: true\n---\n");
        Assert::equals("<h1>First</h1><p>Hello</p>", $this->page->render("/blog/first"));
        Assert::equals("First", $this->page->meta("title"), "the fields are the config of the page");
        Assert::equals("blog/first", $this->site->collection("blog")->find("first")->getUrl());
        Assert::equals("", $this->page->render("/blog/draft"), "a draft is no page");
        Assert::equals("", $this->page->render("/blog/nope"));
        Assert::null($this->site->collection("seminars")->first()->getUrl(), "without url the entries are no pages");
    }
}
