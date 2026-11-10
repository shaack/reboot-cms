<?php

namespace Shaack\Tests;

require_once __DIR__ . "/TestHelper.php";

use Shaack\Reboot\AddOns\Admin\DataEntryHandler;
use Shaack\Reboot\AddOns\Admin\FieldForm;
use Shaack\Reboot\AddOns\Admin\LanguageSwitch;
use Shaack\Reboot\AddOns\Admin\PageActionHandler;
use Shaack\Reboot\AddOns\Admin\PageTreeHelper;
use Shaack\Reboot\AddOns\Admin\StructuredPageHandler;
use Shaack\Reboot\Blueprint;
use Shaack\Reboot\Page;
use Shaack\Reboot\Reboot;
use Shaack\Reboot\Request;
use Shaack\Reboot\Site;
use Shaack\Utils\FileSystemUtils;
use Symfony\Component\Yaml\Yaml;

/**
 * Multilingual sites: Markdown pages with a file per language next to each
 * other, `about.md` and `about.de.md`, structured pages with a value per
 * language in one file, and how the admin edits both.
 */
class MultilingualTest
{
    private string $tmpDir;
    private string $pagesDir;
    private Site $site;
    private Page $page;

    public function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . "/test_multilingual_" . uniqid();
        foreach (["pages/de", "pages/blog", "blocks/intro", "blocks/gallery", "history"] as $dir) {
            mkdir($this->tmpDir . "/" . $dir, 0755, true);
        }
        $this->pagesDir = realpath($this->tmpDir . "/pages");
        file_put_contents($this->tmpDir . "/blocks/text.php", '<div><?= $block->content() ?></div>');
        file_put_contents($this->tmpDir . "/blocks/intro/intro.yml",
            "fields:\n  heading: {type: text, required: true}\n  text: {type: markdown}\n  image: {type: image, shared: true}\n  dark: {type: boolean}\n");
        file_put_contents($this->tmpDir . "/blocks/intro/intro.php",
            '<h1><?= $block->get("heading") ?></h1><?= $block->html("text") ?><img src="<?= $block->get("image") ?>"><?= $block->get("dark") ? "dark" : "" ?>');
        file_put_contents($this->tmpDir . "/blocks/gallery/gallery.yml",
            "fields:\n  items:\n    type: list\n    fields:\n      src: {type: image, shared: true}\n      caption: {type: text}\n");
        file_put_contents($this->tmpDir . "/blocks/gallery/gallery.php",
            '<?php foreach ($block->items("items") as $item) { ?><figure><?= $item->get("src") ?>|<?= $item->get("caption") ?></figure><?php } ?>');

        $rebootRef = new \ReflectionClass(Reboot::class);
        $reboot = $rebootRef->newInstanceWithoutConstructor();
        $rebootRef->getProperty('baseFsPath')->setValue($reboot, $this->tmpDir);
        $rebootRef->getProperty('baseWebPath')->setValue($reboot, "");
        $rebootRef->getProperty('config')->setValue($reboot, ["logLevel" => 2]);
        $this->site = TestHelper::createSiteStub($this->tmpDir, "", ["languages" => ["en", "de"], "defaultLanguage" => "en"]);
        $this->page = new Page($reboot, $this->site);
    }

    public function tearDown(): void
    {
        FieldForm::setLanguages([], "");
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->tmpDir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($this->tmpDir);
    }

    private function write(string $file, string $content): void
    {
        file_put_contents($this->pagesDir . "/" . $file, $content);
    }

    private function render(string $path): string
    {
        return $this->page->render(new Request($this->site, "", $path, []));
    }

    // --- Site ---

    public function testLanguagesOfTheSite(): void
    {
        Assert::equals(["en", "de"], $this->site->getLanguages());
        Assert::equals("en", $this->site->getDefaultLanguage());
        Assert::true($this->site->isMultilingual());
        $single = TestHelper::createSiteStub($this->tmpDir);
        Assert::equals([], $single->getLanguages());
        Assert::equals("en", $single->getDefaultLanguage());
        Assert::false($single->isMultilingual());
    }

    public function testSplitAndBuildLanguagePaths(): void
    {
        Assert::equals(["de", "/about", true], $this->site->splitLanguage("/de/about"));
        Assert::equals(["de", "/", true], $this->site->splitLanguage("/de"));
        Assert::equals(["en", "/about", false], $this->site->splitLanguage("/about"));
        Assert::equals(["en", "/design", false], $this->site->splitLanguage("/design"), "only a whole segment is a language");
        Assert::equals("/de/about", $this->site->languagePath("/about", "de"));
        Assert::equals("/de/", $this->site->languagePath("/", "de"));
        Assert::equals("/about", $this->site->languagePath("/about", "en"), "the default language has no prefix");
    }

    public function testAddressOfAPageFile(): void
    {
        Assert::equals(["/about", "en"], $this->site->pageFileAddress("/about.md"));
        Assert::equals(["/about", "de"], $this->site->pageFileAddress("/about.de.md"));
        Assert::equals(["/blog/", "de"], $this->site->pageFileAddress("/blog/index.de.md"));
        Assert::equals(["/about", "en"], $this->site->pageFileAddress("/about.yml"));
        Assert::equals(["/v1.2", "en"], $this->site->pageFileAddress("/v1.2.md"), "a dot that is no language stays in the name");
    }

    public function testLanguageUrls(): void
    {
        $urls = $this->site->languageUrls(new Request($this->site, "", "/de/about", []));
        Assert::equals(["en" => "/about", "de" => "/de/about"], $urls);
    }

    public function testLocalize(): void
    {
        $values = ["title" => ["en" => "Hello", "de" => "Hallo"], "image" => "a.jpg", "items" => [["caption" => ["en" => "One"]]]];
        Assert::equals(["title" => "Hallo", "image" => "a.jpg", "items" => [["caption" => "One"]]], $this->site->localize($values, "de"),
            "a missing language shows the default one");
        Assert::false($this->site->isLanguageMap(["en" => "x", "type" => "y"]));
        Assert::false($this->site->isLanguageMap(["en" => ["nested"]]));
        Assert::false(TestHelper::createSiteStub($this->tmpDir)->isLanguageMap(["en" => "x"]), "a site in one language has no maps per language");
    }

    // --- Markdown pages ---

    public function testMarkdownPagesPerLanguage(): void
    {
        $this->write("about.md", "About us");
        $this->write("about.de.md", "Über uns");
        $this->write("contact.md", "Contact");
        Assert::contains("About us", $this->render("/about"));
        Assert::contains("Über uns", $this->render("/de/about"));
        Assert::equals("de", $this->page->getLanguage());
        Assert::contains("Contact", $this->render("/de/contact"), "a page without a German file shows the default one");
    }

    public function testHomepagePerLanguage(): void
    {
        $this->write("index.md", "Home");
        $this->write("index.de.md", "Start");
        $this->write("blog/index.de.md", "Blog de");
        Assert::contains("Home", $this->render("/"));
        Assert::contains("Start", $this->render("/de"));
        Assert::contains("Start", $this->render("/de/"));
        Assert::contains("Blog de", $this->render("/de/blog"));
    }

    public function testTheDefaultLanguageMayBeNamed(): void
    {
        $this->write("terms.en.md", "Terms");
        Assert::contains("Terms", $this->render("/terms"));
    }

    public function testAVersionHasNoAddressOfItsOwn(): void
    {
        $this->write("about.de.md", "Über uns");
        $this->write("404.md", "Not found");
        $html = $this->render("/about.de");
        Assert::contains("Not found", $html, "about.de.md is only at /de/about");
        Assert::false(str_contains($html, "Über uns"));
    }

    public function testFolderPerLanguageStillWorks(): void
    {
        $this->write("de/old.md", "Alt");
        $this->write("old.md", "Old");
        Assert::contains("Alt", $this->render("/de/old"));
        $this->write("old.de.md", "Neu");
        Assert::contains("Neu", $this->render("/de/old"), "the file next to the page wins over the folder");
    }

    public function testNotFoundPagePerLanguage(): void
    {
        $this->write("404.md", "Not found");
        $this->write("404.de.md", "Nicht gefunden");
        Assert::contains("Nicht gefunden", $this->render("/de/missing"));
        Assert::contains("Not found", $this->render("/missing"));
    }

    // --- Structured pages ---

    public function testStructuredPageInEachLanguage(): void
    {
        $this->write("home.yml", "title: {en: Home, de: Start}\nblocks:\n  - type: intro\n    heading: {en: Welcome, de: Willkommen}\n"
            . "    text:\n      en: Some **text**\n    image: hero.jpg\n    dark: true\n");
        $en = $this->render("/home");
        Assert::contains("<h1>Welcome</h1>", $en);
        Assert::equals("Home", $this->page->getConfig()["title"]);
        $de = $this->render("/de/home");
        Assert::contains("<h1>Willkommen</h1>", $de);
        Assert::contains("<strong>text</strong>", $de, "a field without German shows the default language");
        Assert::contains('<img src="hero.jpg">', $de, "a shared field holds for all languages");
        Assert::contains("dark", $de);
        Assert::equals("Start", $this->page->getConfig()["title"]);
    }

    public function testListItemsAreTheSameInAllLanguages(): void
    {
        $this->write("pics.yml", "title: P\nblocks:\n  - type: gallery\n    items:\n      - src: a.jpg\n        caption: {en: Lake, de: See}\n      - src: b.jpg\n        caption: Hill\n");
        Assert::contains("<figure>a.jpg|See</figure><figure>b.jpg|Hill</figure>", $this->render("/de/pics"));
    }

    public function testMarkdownBlockPerLanguage(): void
    {
        $this->write("mixed.yml", "title: M\nblocks:\n  - type: text\n    markdown:\n      en: \"# English\"\n      de: \"# Deutsch\"\n");
        Assert::contains("<h1>Deutsch</h1>", $this->render("/de/mixed"));
        Assert::contains("<h1>English</h1>", $this->render("/mixed"));
        Assert::contains("<h1>English</h1>", (new Page($this->rebootOf(), TestHelper::createSiteStub($this->tmpDir)))->render("/mixed"),
            "a site without languages shows the default one");
    }

    private function rebootOf(): Reboot
    {
        $ref = new \ReflectionProperty(Page::class, "reboot");
        return $ref->getValue($this->page);
    }

    // --- Admin: the form and saving ---

    private function handler(): StructuredPageHandler
    {
        return new StructuredPageHandler(Blueprint::forPage($this->site), $this->pagesDir, $this->tmpDir . "/history", 10);
    }

    public function testSavingKeepsAValuePerLanguage(): void
    {
        $file = $this->pagesDir . "/save.yml";
        $this->handler()->save($file, [
            "title" => ["en" => "Save", "de" => ""],
            "blocks" => [
                ["_type" => "intro", "heading" => ["en" => "Hi", "de" => "Hallo"], "text" => ["en" => "", "de" => ""], "image" => "x.jpg", "dark" => "0"],
                ["_type" => "text", "_markdown" => ["en" => "# A", "de" => "# B"]],
            ],
        ]);
        $data = Yaml::parseFile($file);
        Assert::equals("Save", $data["title"], "only the default language is a single value");
        Assert::equals(["en" => "Hi", "de" => "Hallo"], $data["blocks"][0]["heading"]);
        Assert::false(isset($data["blocks"][0]["text"]), "a field empty in all languages is left out");
        Assert::equals("x.jpg", $data["blocks"][0]["image"]);
        Assert::equals(["en" => "# A", "de" => "# B"], $data["blocks"][1]["markdown"]);
        Assert::contains("heading:\n      en: Hi\n      de: Hallo", file_get_contents($file), "written in block style, easy to read");
    }

    public function testValidationUsesTheDefaultLanguage(): void
    {
        $this->write("check.yml", "title: C\nblocks:\n  - type: intro\n    heading: {de: Nur deutsch}\n");
        Assert::equals([], $this->handler()->validate($this->pagesDir . "/check.yml"), "the German value shows in English as well");
        $this->write("check.yml", "title: C\nblocks:\n  - type: intro\n    image: x.jpg\n");
        Assert::true(isset($this->handler()->validate($this->pagesDir . "/check.yml")["blocks.0.heading"]));
    }

    public function testFormHasAnInputPerLanguage(): void
    {
        FieldForm::setLanguages($this->site->getLanguages(), "en");
        $blueprint = Blueprint::forBlock($this->site, "intro");
        $html = FieldForm::render($blueprint, ["heading" => ["en" => "Hi", "de" => "Hallo"], "text" => "Old single value", "image" => "x.jpg"]);
        Assert::contains('name="fields[heading][en]"', $html);
        Assert::contains('name="fields[heading][de]"', $html);
        Assert::contains('data-lang="de"', $html);
        Assert::contains('value="Hallo"', $html);
        Assert::contains("Old single value</textarea>", $html, "a single value is the one of the default language");
        Assert::contains('placeholder="Old single value"', $html, "the other language shows it as placeholder");
        Assert::contains('name="fields[image]"', $html, "a shared field has one input");
        Assert::contains('class="field-shared"', $html);
        Assert::equals(1, substr_count($html, 'class="field-shared"'), "only the shared text field has the globe, not the switch");
        FieldForm::setLanguages([], "");
        $single = FieldForm::render($blueprint, ["heading" => ["en" => "Hi", "de" => "Hallo"]]);
        Assert::contains('name="fields[heading]"', $single, "without languages one input");
        Assert::contains('value="Hi"', $single, "a value per language shows the default one");
    }

    // --- Admin: page list and page actions ---

    private function actions(): PageActionHandler
    {
        return new PageActionHandler($this->pagesDir, $this->tmpDir . "/history", 10, $this->site);
    }

    private function request(array $post): Request
    {
        return new Request($this->site, "", "/pages", $post);
    }

    public function testAddTranslationCopiesThePage(): void
    {
        $this->write("about.md", "About us");
        $result = $this->actions()->handle("add_translation", "/about.md", $this->request(["language" => "de"]));
        Assert::equals("/about.de.md", $result["editPageName"]);
        Assert::equals("About us", file_get_contents($this->pagesDir . "/about.de.md"));
        Assert::throws(\InvalidArgumentException::class, fn() => $this->actions()->handle("add_translation", "/about.md", $this->request(["language" => "de"])));
        Assert::throws(\InvalidArgumentException::class, fn() => $this->actions()->handle("add_translation", "/about.md", $this->request(["language" => "fr"])));
    }

    public function testRenameAndMoveTakeTheVersionsAlong(): void
    {
        $this->write("about.md", "About");
        $this->write("about.de.md", "Über");
        $result = $this->actions()->handle("rename_page", "/about.de.md", $this->request(["new_name" => "team"]));
        Assert::equals("/team.de.md", $result["editPageName"]);
        Assert::true(is_file($this->pagesDir . "/team.md") && is_file($this->pagesDir . "/team.de.md"));
        Assert::false(is_file($this->pagesDir . "/about.md") || is_file($this->pagesDir . "/about.de.md"));
        $this->actions()->handle("move_page", "/team.md", $this->request(["destination" => "blog"]));
        Assert::true(is_file($this->pagesDir . "/blog/team.md") && is_file($this->pagesDir . "/blog/team.de.md"));
    }

    public function testRenameStopsIfAVersionNameIsTaken(): void
    {
        $this->write("about.md", "About");
        $this->write("about.de.md", "Über");
        $this->write("team.de.md", "Team");
        Assert::throws(\InvalidArgumentException::class, fn() => $this->actions()->handle("rename_page", "/about.md", $this->request(["new_name" => "team"])));
        Assert::true(is_file($this->pagesDir . "/about.md"), "nothing moved");
    }

    public function testPageListShowsOneEntryWithItsLanguages(): void
    {
        $this->write("about.md", "About");
        $this->write("about.de.md", "Über");
        $this->write("home.yml", "title: H\nblocks: []\n");
        $pages = FileSystemUtils::getFileList($this->pagesDir, true);
        $tree = PageTreeHelper::buildPageTree($pages, $this->pagesDir);
        $editable = false;
        $html = PageTreeHelper::renderTree($tree, "/about.de.md", $editable, "", $this->site);
        Assert::true($editable, "the German version is editable");
        Assert::equals(1, substr_count($html, "data-path='about.md'"));
        Assert::false(str_contains($html, "data-path='about.de.md'"), "the version is no entry of its own");
        Assert::contains("page-tree-language active' data-lang-link='de' href='pages?page=%2Fabout.de.md'", $html);
        Assert::equals(1, substr_count($html, "page-tree-languages"), "a structured page holds all languages, it has no links per language");
    }

    public function testLanguageSwitchOfAMarkdownPage(): void
    {
        $this->write("about.md", "About");
        Assert::equals(["en" => "/about.md", "de" => null], LanguageSwitch::markdownFiles($this->site, $this->pagesDir, "/about.md"));
        $this->write("about.de.md", "Über");
        Assert::equals(["en" => "/about.md", "de" => "/about.de.md"], LanguageSwitch::markdownFiles($this->site, $this->pagesDir, "/about.de.md"));
        Assert::null(LanguageSwitch::markdownFiles($this->site, $this->pagesDir, "/de/old.md"), "a page in the folder per language has no versions");
        $html = LanguageSwitch::render(["en", "de"], "en", ["en" => "/about.md", "de" => null], "en");
        Assert::contains('aria-current="page"', $html);
        Assert::contains("addTranslation(&quot;de&quot;)", $html);
    }

    // --- Data ---

    private function seminars(): void
    {
        mkdir($this->tmpDir . "/blueprints");
        mkdir($this->tmpDir . "/data/seminars", 0755, true);
        file_put_contents($this->tmpDir . "/blueprints/seminars.yml", "title: Seminars\nsort: date\nurl: seminars\nfields:\n"
            . "  title: {type: text, required: true}\n  date: {type: date}\n  price: {type: number}\n  photo: {type: image, shared: true}\n  body: {type: markdown}\n");
        file_put_contents($this->tmpDir . "/blueprints/seminars.php", '<h1><?= $entry->get("title") ?></h1><?= $entry->html("body") ?><?= $entry->get("price") ?>');
        file_put_contents($this->tmpDir . "/data/seminars/basics.yml", "title: {en: Basics, de: Grundlagen}\ndate: 2027-03-12\nprice: 190\n"
            . "body:\n  en: A **day** to start with.\n  de: Ein **Tag** für den Anfang.\n");
        file_put_contents($this->tmpDir . "/data/seminars/advanced.yml", "title: Advanced\ndate: 2027-01-10\nprice: 290\nbody: For those who know.\n");
    }

    public function testEntryInEachLanguage(): void
    {
        $this->seminars();
        $entry = $this->site->collection("seminars")->find("basics");
        Assert::equals("Basics", $entry->get("title"), "the default language before a page is rendered");
        Assert::contains("A <strong>day</strong>", $entry->html("body"));
        $this->site->setLanguage("de");
        Assert::equals("Grundlagen", $entry->get("title"));
        Assert::contains("Ein <strong>Tag</strong>", $entry->html("body"));
        Assert::equals(190, $entry->get("price"), "a number is the same in all languages");
        Assert::equals("Advanced", $this->site->collection("seminars")->find("advanced")->get("title"), "a single value holds for all languages");
        Assert::true($entry->isValid());
    }

    public function testQueriesReadTheDefaultLanguage(): void
    {
        $this->seminars();
        $this->site->setLanguage("de");
        $titles = array_map(fn($entry) => $entry->getSlug(), $this->site->collection("seminars")->sortBy("title")->toArray());
        Assert::equals(["advanced", "basics"], $titles, "sorted by the English titles, Advanced before Basics, in German as well");
        Assert::equals(1, count($this->site->collection("seminars")->where("title", "contains", "basics")));
        Assert::equals(0, count($this->site->collection("seminars")->where("title", "contains", "grundlagen")));
    }

    public function testEntryPageInEachLanguage(): void
    {
        $this->seminars();
        $de = $this->render("/de/seminars/basics");
        Assert::contains("<h1>Grundlagen</h1>", $de);
        Assert::contains("Ein <strong>Tag</strong>", $de);
        $en = $this->render("/seminars/basics");
        Assert::contains("<h1>Basics</h1>", $en);
        Assert::contains("<h1>Advanced</h1>", $this->render("/de/seminars/advanced"), "an entry without German shows the English one");
    }

    public function testSavingAnEntryKeepsAValuePerLanguage(): void
    {
        $this->seminars();
        $blueprint = Blueprint::load($this->site, "seminars");
        $handler = new DataEntryHandler($blueprint, $this->tmpDir . "/data", $this->tmpDir . "/history", 5);
        $values = $handler->read("basics");
        Assert::equals(["en" => "Basics", "de" => "Grundlagen"], $values["title"]);
        Assert::equals(["en" => "A **day** to start with.", "de" => "Ein **Tag** für den Anfang."], $values["body"]);
        $handler->save("basics", [
            "title" => ["en" => "Basics", "de" => "Grundkurs"],
            "date" => "2027-03-12",
            "price" => "190",
            "photo" => "media/a.jpg",
            "body" => ["en" => "A day.", "de" => "Ein Tag."],
        ], false);
        $file = (string)file_get_contents($this->tmpDir . "/data/seminars/basics.yml");
        Assert::contains("title:\n  en: Basics\n  de: Grundkurs", $file);
        Assert::contains("photo: media/a.jpg", $file, "a shared field has one value");
        Assert::contains("body:\n  en: A day.\n  de: Ein Tag.\n", $file);
        $handler->save("advanced", ["title" => ["en" => "Advanced"], "body" => ["en" => "Only English."]], false);
        $file = (string)file_get_contents($this->tmpDir . "/data/seminars/advanced.yml");
        Assert::contains("title: Advanced\nbody: Only English.\n", $file, "only the default language is a single value");
    }

    // --- Pages of a folder ---

    private function posts(): void
    {
        $this->write("blog/index.md", "Blog");
        $this->write("blog/first.md", "---\ntitle: First post\ndate: 2026-01-02\nblock: post\nlinks: \"- [All](blog)\"\n---\n\nHello");
        $this->write("blog/first.de.md", "---\ntitle: Erster Beitrag\n---\n\nHallo");
        $this->write("blog/second.md", "---\ntitle: Second post\ndate: 2026-03-04\n---\n\nMore");
        $this->write("blog/draft.md", "---\ntitle: Draft\ndraft: true\n---\n");
        file_put_contents($this->tmpDir . "/blocks/post.php",
            '<?php $c = $block->getConfig(); ?><h1><?= $c["title"] ?></h1><time><?= $c["date"] ?? "" ?></time><?= $block->content() ?>');
    }

    public function testPagesOfAFolder(): void
    {
        $this->posts();
        $posts = $this->site->pages("blog");
        Assert::equals(["first", "second"], array_map(fn($post) => $post->getSlug(), $posts->toArray()), "no index, no draft, a translation is no page of its own");
        Assert::equals(["second", "first"], array_map(fn($post) => $post->getSlug(), $posts->sortBy("date", "desc")->toArray()));
        $first = $posts->find("first");
        Assert::equals("First post", $first->get("title"));
        Assert::equals("2026-01-02", $first->get("date"), "a date stays text");
        Assert::equals("blog/first", $first->getUrl());
        $this->site->setLanguage("de");
        Assert::equals("Erster Beitrag", $first->get("title"));
        Assert::equals("02.01.2026", $first->date("date", "d.m.Y"), "the date of the default language");
        Assert::equals("de/blog/first", $first->getUrl());
        Assert::equals("Second post", $posts->find("second")->get("title"), "a page without German shows the default one");
        Assert::equals(1, count($posts->where("title", "contains", "first")), "queries read the default language");
    }

    public function testFrontmatterNamesTheBlockOfAPageWithoutBlocks(): void
    {
        $this->posts();
        $en = $this->render("/blog/first");
        Assert::contains("<h1>First post</h1><time>2026-01-02</time>", $en);
        Assert::contains("<p>Hello</p>", $en);
        $de = $this->render("/de/blog/first");
        Assert::contains("<h1>Erster Beitrag</h1><time>2026-01-02</time>", $de, "a translation takes what it does not say from the page of the default language");
        Assert::contains("<p>Hallo</p>", $de);
        Assert::false(str_contains($this->render("/blog/second"), "<h1>"), "without block it is a text block");
    }

    public function testRelativeLinksLeadToThePagesOfTheLanguage(): void
    {
        $html = '<a href="blog/first">a</a><a href="./">b</a><a href="https://x.org">c</a><a href="#top">d</a>'
            . '<a href="de/about">e</a><a href="/about">f</a><a class="x" href=\'seminars?x=1#y\'>g</a><img src="media/x.png">';
        Assert::equals('<a href="de/blog/first">a</a><a href="de/">b</a><a href="https://x.org">c</a><a href="#top">d</a>'
            . '<a href="de/about">e</a><a href="/about">f</a><a class="x" href=\'de/seminars?x=1#y\'>g</a><img src="media/x.png">',
            $this->site->localizeLinks($html, "de"));
        Assert::equals($html, $this->site->localizeLinks($html, "en"), "the default language has no prefix");
        $this->write("about.de.md", "[Blog](blog)");
        Assert::contains('href="de/blog"', $this->render("/de/about"));
    }

    public function testCollectionOfOneLanguage(): void
    {
        $blueprint = new Blueprint("sites", ["shared" => true, "fields" => ["name" => ["type" => "text"], "url" => ["type" => "url"]]]);
        Assert::false(Blueprint::isTranslatable($blueprint->getFields()["name"]));
        Assert::false(Blueprint::isTranslatable($blueprint->getFields()["url"]));
        $other = new Blueprint("seminars", ["fields" => ["title" => ["type" => "text"]]]);
        Assert::true(Blueprint::isTranslatable($other->getFields()["title"]));
        Assert::false($blueprint->hasTranslatableFields(), "the admin shows no language switch for it");
        Assert::true($other->hasTranslatableFields());
    }
}
