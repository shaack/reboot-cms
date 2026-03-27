<?php

namespace Shaack\Tests;

require_once __DIR__ . "/TestHelper.php";

use Shaack\Reboot\Page;
use Shaack\Reboot\Reboot;

class PageTest
{
    private string $tmpDir;
    private Page $page;

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

        $site = TestHelper::createSiteStub($this->tmpDir, "");
        $this->page = new Page($reboot, $site);
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
}
