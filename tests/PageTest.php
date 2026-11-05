<?php

namespace Shaack\Tests;

require_once __DIR__ . "/TestHelper.php";

use Shaack\Reboot\AddOn;
use Shaack\Reboot\Page;
use Shaack\Reboot\Reboot;
use Shaack\Reboot\Request;
use Shaack\Reboot\Site;

class PageTest
{
    private string $tmpDir;
    private Page $page;
    private Site $site;

    public function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . "/test_page_" . uniqid();
        mkdir($this->tmpDir . "/pages", 0755, true);
        mkdir($this->tmpDir . "/blocks", 0755, true);

        // Create a minimal text block template
        file_put_contents($this->tmpDir . "/blocks/text.php", '<?php echo $block->content(); ?>');

        // Create Reboot stub
        $rebootRef = new \ReflectionClass(Reboot::class);
        $reboot = $rebootRef->newInstanceWithoutConstructor();
        $rebootRef->getProperty('baseFsPath')->setValue($reboot, $this->tmpDir);
        $rebootRef->getProperty('baseWebPath')->setValue($reboot, "");
        $rebootRef->getProperty('config')->setValue($reboot, ["logLevel" => 2]);

        $this->site = TestHelper::createSiteStub($this->tmpDir, "");
        $this->page = new Page($reboot, $this->site);
    }

    /**
     * Registers an AddOn stub that brings the given pages (`path => file`).
     */
    private function addPagesAddOn(array $pages): void
    {
        $addOn = (new \ReflectionClass(PagesAddOnStub::class))->newInstanceWithoutConstructor();
        $addOn->pages = $pages;
        (new \ReflectionClass(Site::class))->getProperty('addOns')->setValue($this->site, ['Stub' => $addOn]);
    }

    public function tearDown(): void
    {
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
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }
        rmdir($dir);
    }

    // --- AddOn pages ---

    public function testRendersPageRegisteredByAddOn(): void
    {
        file_put_contents($this->tmpDir . "/addon-page.php", '<?php echo "from addon " . $request->getPath();');
        $this->addPagesAddOn(["/tools/addon-page" => $this->tmpDir . "/addon-page.php"]);
        $request = new Request($this->site, "", "/tools/addon-page", []);
        Assert::equals("from addon /tools/addon-page", $this->page->render($request));
    }

    public function testPagesFolderWinsOverAddOnPage(): void
    {
        file_put_contents($this->tmpDir . "/pages/hello.md", "from pages");
        file_put_contents($this->tmpDir . "/addon-page.php", '<?php echo "from addon";');
        $this->addPagesAddOn(["/hello" => $this->tmpDir . "/addon-page.php"]);
        $request = new Request($this->site, "", "/hello", []);
        Assert::contains("from pages", $this->page->render($request));
    }

    public function testAddOnPageWithMissingFileIs404(): void
    {
        $this->addPagesAddOn(["/gone" => $this->tmpDir . "/does-not-exist.php"]);
        $request = new Request($this->site, "", "/gone", []);
        Assert::equals("", $this->page->render($request)); // no custom 404 page, empty response
    }

    // --- Markdown rendering ---

    public function testRenderSimpleMarkdown(): void
    {
        file_put_contents($this->tmpDir . "/pages/hello.md", "Hello **world**");
        $html = $this->page->render("/hello");
        Assert::contains("<strong>world</strong>", $html);
    }

    public function testRenderIndex(): void
    {
        file_put_contents($this->tmpDir . "/pages/index.md", "# Home");
        // Directory path should resolve to index
        mkdir($this->tmpDir . "/pages/section", 0755, true);
        file_put_contents($this->tmpDir . "/pages/section/index.md", "# Section Home");
        $html = $this->page->render("/section");
        Assert::contains("Section Home", $html);
    }

    public function testRender404ForMissingPage(): void
    {
        $html = $this->page->render("/nonexistent");
        // Should return empty string (no custom 404 page exists)
        Assert::equals("", $html);
    }

    // --- Frontmatter ---

    public function testFrontmatterParsed(): void
    {
        $content = "---\ntitle: My Page\nauthor: Test\n---\n\nPage content here";
        file_put_contents($this->tmpDir . "/pages/fm.md", $content);
        $this->page->render("/fm");
        $config = $this->page->getConfig();
        Assert::equals("My Page", $config["title"]);
        Assert::equals("Test", $config["author"]);
    }

    public function testFrontmatterNotInOutput(): void
    {
        $content = "---\ntitle: My Page\n---\n\nVisible content";
        file_put_contents($this->tmpDir . "/pages/fm2.md", $content);
        $html = $this->page->render("/fm2");
        Assert::contains("Visible content", $html);
        // Frontmatter should not appear in rendered output
        $noTitle = !str_contains($html, "title: My Page");
        Assert::true($noTitle, "Frontmatter should not appear in output");
    }

    public function testNoFrontmatter(): void
    {
        file_put_contents($this->tmpDir . "/pages/nofm.md", "Just content");
        $this->page->render("/nofm");
        Assert::equals([], $this->page->getConfig());
    }

    // --- Blocks ---

    public function testBlockDetection(): void
    {
        // Create a simple block template
        file_put_contents(
            $this->tmpDir . "/blocks/hero.php",
            '<?php echo "<div class=\"hero\">" . $block->content() . "</div>"; ?>'
        );
        $content = "<!-- hero -->\n# Welcome\n\nThis is the hero block";
        file_put_contents($this->tmpDir . "/pages/blocks.md", $content);
        $html = $this->page->render("/blocks");
        Assert::contains("hero", $html);
        Assert::contains("Welcome", $html);
    }

    public function testMultipleBlocks(): void
    {
        file_put_contents(
            $this->tmpDir . "/blocks/header.php",
            '<?php echo "<header>" . $block->content() . "</header>"; ?>'
        );
        file_put_contents(
            $this->tmpDir . "/blocks/footer.php",
            '<?php echo "<footer>" . $block->content() . "</footer>"; ?>'
        );
        $content = "<!-- header -->\n# Title\n<!-- footer -->\nFooter text";
        file_put_contents($this->tmpDir . "/pages/multi.md", $content);
        $html = $this->page->render("/multi");
        Assert::contains("<header>", $html);
        Assert::contains("<footer>", $html);
    }

    public function testBlockWithConfig(): void
    {
        file_put_contents(
            $this->tmpDir . "/blocks/banner.php",
            '<?php echo $block->getConfig()["color"] ?? "none"; ?>'
        );
        $content = "<!-- banner:\n  color: blue -->\nBanner content";
        file_put_contents($this->tmpDir . "/pages/cfg.md", $content);
        $html = $this->page->render("/cfg");
        Assert::contains("blue", $html);
    }

    public function testEmptyPageReturnsEmpty(): void
    {
        file_put_contents($this->tmpDir . "/pages/empty.md", "");
        $html = $this->page->render("/empty");
        Assert::equals("", $html);
    }

    public function testUnknownBlockShowsError(): void
    {
        $content = "<!-- nonexistent-block -->\nSome content";
        file_put_contents($this->tmpDir . "/pages/unknown.md", $content);
        $html = $this->page->render("/unknown");
        Assert::contains("Block not found", $html);
    }

    // --- Path traversal protection ---

    public function testPathTraversalBlocked(): void
    {
        $html = $this->page->render("/../../../etc/passwd");
        // Should get 404, not file content
        Assert::equals("", $html);
    }

    // --- Code blocks should not be parsed as CMS blocks ---

    public function testFencedCodeBlockNotParsedAsBlock(): void
    {
        $content = "```\n<!-- not-a-block -->\ncode here\n```";
        file_put_contents($this->tmpDir . "/pages/code.md", $content);
        $html = $this->page->render("/code");
        // Should render as code, not try to find a "not-a-block" block template
        // The text block should handle it as plain markdown
        $noBlockError = !str_contains($html, "Block not found");
        Assert::true($noBlockError, "Fenced code blocks should not be parsed as CMS blocks");
    }

    // --- Comments, frontmatter and other input that must not take a page down ---

    public function testPlainCommentsDoNotBreakThePage(): void
    {
        foreach (["<!-- TODO: fix: now -->", "<!-- 123 -->", "<!-- -->", "<!-- note: just text -->"] as $i => $comment) {
            file_put_contents($this->tmpDir . "/pages/c$i.md", "<!-- text -->\n\nBefore\n\n$comment\n\nAfter");
            $html = $this->page->render("/c$i");
            Assert::contains("Before", $html, $comment);
            Assert::contains("After", $html, "content after $comment stays in the block before it");
            Assert::false(str_contains($html, "Block not found"), "$comment is no block");
        }
    }

    public function testPageWithOnlyAPlainCommentRendersAsMarkdown(): void
    {
        file_put_contents($this->tmpDir . "/pages/c.md", "<!-- TODO: fix: now -->\n\nHello");
        Assert::contains("Hello", $this->page->render("/c"));
    }

    public function testBlockWithConfigMapping(): void
    {
        file_put_contents($this->tmpDir . "/blocks/cfgblock.php", '<?= $block->getConfig()["mode"] ?? "none" ?>');
        file_put_contents($this->tmpDir . "/pages/b.md", "<!-- cfgblock: {mode: dark} -->\n\nx\n\n<!-- cfgblock: -->\n\ny");
        Assert::equals("darknone", $this->page->render("/b"));
    }

    public function testLeadingHorizontalRuleIsNoFrontmatter(): void
    {
        file_put_contents($this->tmpDir . "/pages/hr.md", "---\n\nText\n\n---\n\nmore");
        $html = $this->page->render("/hr");
        Assert::contains("Text", $html);
        Assert::contains("more", $html);
        Assert::equals([], $this->page->getConfig());
    }

    public function testInvalidOrScalarFrontmatterDoesNotThrow(): void
    {
        file_put_contents($this->tmpDir . "/pages/bad.md", "---\ntitle: a: b\n---\n\nBody");
        Assert::contains("Body", $this->page->render("/bad"));
        Assert::equals([], $this->page->getConfig());
        file_put_contents($this->tmpDir . "/pages/scalar.md", "---\njust text\n---\n\nBody");
        Assert::contains("Body", $this->page->render("/scalar"));
        Assert::equals([], $this->page->getConfig());
    }

    public function testFrontmatterWithWindowsLineEnds(): void
    {
        file_put_contents($this->tmpDir . "/pages/crlf.md", "---\r\ntitle: Windows\r\n---\r\n\r\nBody");
        $html = $this->page->render("/crlf");
        Assert::equals("Windows", $this->page->getConfig()["title"] ?? null);
        Assert::false(str_contains($html, "title:"), "the frontmatter is not rendered as text");
    }

    public function testFrontmatterDelimiterMustBeALine(): void
    {
        file_put_contents($this->tmpDir . "/pages/fm3.md", "---\ntitle: a---b\n---\n\nBody");
        $this->page->render("/fm3");
        Assert::equals("a---b", $this->page->getConfig()["title"]);
    }

    public function testMissingBlockLeavesNoOutputBuffer(): void
    {
        $level = ob_get_level();
        file_put_contents($this->tmpDir . "/pages/missing.md", "<!-- nonexistent-block -->\n\nx");
        Assert::contains("Block not found", $this->page->render("/missing"));
        Assert::equals($level, ob_get_level());
    }

    // --- meta() ---

    public function testMetaEscapesStringsByDefault(): void
    {
        file_put_contents($this->tmpDir . "/pages/meta.md", "---\ntitle: Tom & \"Jerry\" <b>\nhide-nav: true\ntags: [a, b]\n---\n\nBody");
        $this->page->render("/meta");
        Assert::equals("Tom &amp; &quot;Jerry&quot; &lt;b&gt;", $this->page->meta("title"));
        Assert::equals('Tom & "Jerry" <b>', $this->page->meta("title", null, false));
        Assert::equals(true, $this->page->meta("hide-nav"), "non-strings come back unchanged");
        Assert::equals(["a", "b"], $this->page->meta("tags"));
    }

    public function testMetaDefault(): void
    {
        file_put_contents($this->tmpDir . "/pages/nometa.md", "Body");
        $this->page->render("/nometa");
        Assert::null($this->page->meta("title"));
        Assert::equals("A &amp; B", $this->page->meta("title", "A & B"));
        Assert::equals(false, $this->page->meta("hide-nav", false));
    }

    // --- Preview, 404 and paths ---

    public function testPreviewContentIsRenderedWithoutTouchingTheFile(): void
    {
        $file = $this->tmpDir . "/pages/draft.md";
        file_put_contents($file, "Published");
        $this->page->setPreviewContent($file, "Draft");
        Assert::contains("Draft", $this->page->render("/draft"));
        Assert::equals("Published", file_get_contents($file));
    }

    public function testPreviewMarksTheSourceLineOfEveryBlock(): void
    {
        $file = $this->tmpDir . "/pages/lines.md";
        file_put_contents($file, "Published");
        // line 0 is empty, the frontmatter takes lines 1-3, the blocks start at 5 and 9
        $draft = "\n---\ntitle: x\n---\n\n<!-- text -->\n\nA\n\n<!-- text -->\n\nB\n";
        $this->page->setPreviewContent($file, $draft);
        $html = $this->page->render("/lines");
        preg_match_all('/<!--reboot-preview-line:(\d+)(:end)?-->/', $html, $m);
        Assert::equals(["5", "9", "12"], $m[1]);
        Assert::equals(["", "", ":end"], $m[2]);
    }

    public function testPreviewMarksAFlatPageAndCountsWindowsLineEnds(): void
    {
        $file = $this->tmpDir . "/pages/flat.md";
        file_put_contents($file, "Published");
        $this->page->setPreviewContent($file, "---\r\ntitle: x\r\n---\r\n\r\nText");
        preg_match_all('/<!--reboot-preview-line:(\d+)/', $this->page->render("/flat"), $m);
        Assert::equals(["2", "5"], $m[1]);
    }

    public function testNoPreviewMarkersOutsideThePreview(): void
    {
        file_put_contents($this->tmpDir . "/pages/plain.md", "<!-- text -->\n\nA");
        Assert::false(str_contains($this->page->render("/plain"), "reboot-preview-line"));
    }

    public function testCustom404PhpPageGetsTheRequest(): void
    {
        file_put_contents($this->tmpDir . "/pages/404.php", '<?php echo "Not here: " . $request->getPath();');
        $request = new Request($this->site, "", "/missing", []);
        Assert::equals("Not here: /missing", $this->page->render($request));
    }

    public function testInvalidRequestPathIs404(): void
    {
        file_put_contents($this->tmpDir . "/pages/about.md", "About");
        $request = new Request($this->site, "", "/about%00", []);
        Assert::equals("", $this->page->render($request));
    }

    public function testSiblingFolderWithSamePrefixIsNotServed(): void
    {
        mkdir($this->tmpDir . "/pages-old", 0755, true);
        file_put_contents($this->tmpDir . "/pages-old/secret.md", "Secret");
        Assert::equals("", $this->page->render("/../pages-old/secret"));
    }
}

class PagesAddOnStub extends AddOn
{
    public array $pages = [];

    public function getPages(): array
    {
        return $this->pages;
    }
}
