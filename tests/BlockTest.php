<?php

namespace Shaack\Tests;

require_once __DIR__ . "/TestHelper.php";

use Shaack\Logger;
use Shaack\Reboot\Block;

class BlockTest
{
    private function createBlock(string $name = "test", string $content = "", array $config = []): Block
    {
        $site = TestHelper::createSiteStub(__DIR__ . "/../site", "");
        return new Block($site, $name, $content, $config);
    }

    // --- getName / getConfig ---

    public function testGetName(): void
    {
        $block = $this->createBlock("hero");
        Assert::equals("hero", $block->getName());
    }

    public function testGetConfig(): void
    {
        $block = $this->createBlock("test", "", ["color" => "red"]);
        Assert::equals(["color" => "red"], $block->getConfig());
    }

    public function testGetConfigEmpty(): void
    {
        $block = $this->createBlock("test");
        Assert::equals([], $block->getConfig());
    }

    // --- content() ---

    public function testContentReturnsHtml(): void
    {
        $block = $this->createBlock("test", "Hello **world**");
        $html = $block->content();
        Assert::contains("<strong>world</strong>", $html);
        Assert::contains("<p>", $html);
    }

    public function testContentEmptyString(): void
    {
        $block = $this->createBlock("test", "");
        Assert::equals("", $block->content());
    }

    // --- xpath() ---

    public function testXpathParagraph(): void
    {
        $block = $this->createBlock("test", "Hello world");
        $node = $block->xpath("//p");
        Assert::notNull($node);
        Assert::true($node instanceof \DOMNode);
        Assert::contains("Hello world", $node->textContent);
    }

    public function testXpathMultipleParagraphs(): void
    {
        $block = $this->createBlock("test", "Paragraph one\n\nParagraph two\n\nParagraph three");
        $nodeList = $block->xpath("//p");
        Assert::true($nodeList instanceof \DOMNodeList);
        Assert::equals(3, $nodeList->length);
    }

    public function testXpathHeading(): void
    {
        $block = $this->createBlock("test", "# Main Title");
        $node = $block->xpath("//h1");
        Assert::notNull($node);
        Assert::equals("Main Title", $node->textContent);
    }

    public function testXpathLink(): void
    {
        $block = $this->createBlock("test", "[Click here](https://example.com)");
        $href = $block->xpath("//a/@href");
        Assert::notNull($href);
        Assert::equals("https://example.com", $block->nodeHtml($href));
    }

    public function testXpathImage(): void
    {
        $block = $this->createBlock("test", "![Alt text](image.jpg)");
        $src = $block->xpath("//img/@src");
        Assert::notNull($src);
        Assert::equals("image.jpg", $block->nodeHtml($src));
    }

    public function testXpathNoMatch(): void
    {
        $block = $this->createBlock("test", "Just a paragraph");
        $result = $block->xpath("//h1");
        Assert::true($result instanceof \DOMNodeList);
        Assert::equals(0, $result->length);
    }

    // --- part() syntax ---

    public function testXpathPart1(): void
    {
        $block = $this->createBlock("test", "Part one\n\n---\n\nPart two");
        $node = $block->xpath("//*[part(1)]");
        Assert::notNull($node);
        Assert::contains("Part one", $block->nodeHtml($node));
    }

    public function testXpathPart2(): void
    {
        $block = $this->createBlock("test", "Part one\n\n---\n\nPart two");
        $node = $block->xpath("//*[part(2)]");
        Assert::notNull($node);
        Assert::contains("Part two", $block->nodeHtml($node));
    }

    public function testXpathPartWithSpecificElement(): void
    {
        $block = $this->createBlock("test", "# Title\n\nIntro\n\n---\n\n## Subtitle\n\nBody");
        $node = $block->xpath("//h2[part(2)]");
        Assert::notNull($node);
        Assert::equals("Subtitle", $node->textContent);
    }

    public function testXpathThreeParts(): void
    {
        $block = $this->createBlock("test", "One\n\n---\n\nTwo\n\n---\n\nThree");
        $part1 = $block->xpath("//p[part(1)]");
        $part3 = $block->xpath("//p[part(3)]");
        Assert::contains("One", $block->nodeHtml($part1));
        Assert::contains("Three", $block->nodeHtml($part3));
    }

    // --- nodeHtml() ---

    public function testNodeHtmlWithDOMText(): void
    {
        $block = $this->createBlock("test", "Simple text");
        $node = $block->xpath("//p/text()");
        $html = $block->nodeHtml($node);
        Assert::equals("Simple text", $html);
    }

    public function testNodeHtmlWithDOMNodeList(): void
    {
        $block = $this->createBlock("test", "First\n\nSecond");
        $nodes = $block->xpath("//p");
        $html = $block->nodeHtml($nodes);
        Assert::contains("First", $html);
        Assert::contains("Second", $html);
    }

    public function testNodeHtmlNoDescent(): void
    {
        $block = $this->createBlock("test", "**Bold** and normal");
        $node = $block->xpath("//p");
        $html = $block->nodeHtml($node, false);
        Assert::contains("<strong>Bold</strong>", $html);
        Assert::contains("and normal", $html);
    }

    public function testNodeHtmlWithEmptyListAndNull(): void
    {
        $block = $this->createBlock("test", "");
        $nodes = $block->xpath("//h3[part(2)]");
        Assert::true($nodes instanceof \DOMNodeList);
        Assert::equals("", $block->nodeHtml($nodes));
        Assert::equals("", $block->nodeHtml($nodes->item(2)), "item() beyond the list gives null");
        Assert::equals("", $block->nodeHtml(null, false));
    }

    public function testFaultyTemplateRendersErrorBoxInsteadOfDying(): void
    {
        $dir = sys_get_temp_dir() . "/test_block_" . uniqid();
        mkdir($dir . "/blocks", 0755, true);
        file_put_contents($dir . "/blocks/broken.php", '<?php echo "before"; throw new \RuntimeException("boom"); ?>');
        file_put_contents($dir . "/blocks/fine.php", '<p>fine</p>');
        $level = Logger::getLevel();
        Logger::setLevel(3);
        try {
            $site = TestHelper::createSiteStub($dir, "");
            $html = (new Block($site, "broken", ""))->render(null);
            Assert::contains("Block error in \"broken\"", $html);
            Assert::contains("RuntimeException: boom in broken.php on line 1", $html);
            Assert::false(str_contains($html, "before"), "partial output is discarded");
            Assert::equals("<p>fine</p>", (new Block($site, "fine", ""))->render(null), "output buffering is intact afterwards");
        } finally {
            Logger::setLevel($level);
            array_map('unlink', glob($dir . "/blocks/*.php"));
            rmdir($dir . "/blocks");
            rmdir($dir);
        }
    }

    // --- Validation ---

    public function testValidationRequired(): void
    {
        Block::resetAllValidationErrors();
        $block = $this->createBlock("test", "Just text");
        $block->xpath("//h1", ["required" => true, "description" => "heading"]);
        Assert::equals(1, count($block->getValidationErrors()));
        Assert::contains("missing required", $block->getValidationErrors()[0]);
    }

    public function testValidationRequiredPasses(): void
    {
        Block::resetAllValidationErrors();
        $block = $this->createBlock("test", "# Title");
        $block->xpath("//h1", ["required" => true, "description" => "heading"]);
        Assert::equals(0, count($block->getValidationErrors()));
    }

    public function testValidationMin(): void
    {
        Block::resetAllValidationErrors();
        $block = $this->createBlock("test", "One paragraph");
        $block->xpath("//p", ["min" => 3, "description" => "paragraphs"]);
        Assert::equals(1, count($block->getValidationErrors()));
        Assert::contains("at least 3", $block->getValidationErrors()[0]);
    }

    public function testValidationMax(): void
    {
        Block::resetAllValidationErrors();
        $block = $this->createBlock("test", "A\n\nB\n\nC");
        $block->xpath("//p", ["max" => 1, "description" => "paragraphs"]);
        Assert::equals(1, count($block->getValidationErrors()));
        Assert::contains("at most 1", $block->getValidationErrors()[0]);
    }

    public function testValidationAllErrors(): void
    {
        Block::resetAllValidationErrors();
        $block1 = $this->createBlock("block1", "text");
        $block1->xpath("//h1", ["required" => true, "description" => "heading"]);
        $block2 = $this->createBlock("block2", "text");
        $block2->xpath("//h2", ["required" => true, "description" => "subheading"]);
        $allErrors = Block::getAllValidationErrors();
        Assert::equals(2, count($allErrors));
    }

    public function testValidationReset(): void
    {
        Block::resetAllValidationErrors();
        $block = $this->createBlock("test", "text");
        $block->xpath("//h1", ["required" => true]);
        Assert::true(count(Block::getAllValidationErrors()) > 0);
        Block::resetAllValidationErrors();
        Assert::equals(0, count(Block::getAllValidationErrors()));
    }

    // --- generateExample() ---

    public function testGenerateExampleHeading(): void
    {
        $block = $this->createBlock("test", "# Title");
        $block->xpath("//h1", ["description" => "Main title"]);
        $example = $block->generateExample();
        Assert::contains("# Main title", $example);
    }

    public function testGenerateExampleParagraph(): void
    {
        $block = $this->createBlock("test", "Some text");
        $block->xpath("//p", ["description" => "Description"]);
        $example = $block->generateExample();
        Assert::contains("Description", $example);
    }

    public function testGenerateExampleImage(): void
    {
        $block = $this->createBlock("test", "![photo](img.jpg)");
        $block->xpath("//img", ["description" => "Photo"]);
        $example = $block->generateExample();
        Assert::contains("![Photo](image-url)", $example);
    }

    public function testGenerateExampleWithParts(): void
    {
        $block = $this->createBlock("test", "Header\n\n---\n\nBody");
        $block->xpath("//h1[part(1)]", ["description" => "Title"]);
        $block->xpath("//p[part(2)]", ["description" => "Content"]);
        $example = $block->generateExample();
        Assert::contains("---", $example);
    }

    // --- tolerant HTML parsing ---

    /**
     * Collect the PHP diagnostics raised while running $fn.
     */
    private function captureDiagnostics(callable $fn): array
    {
        $messages = [];
        set_error_handler(function ($errno, $errstr) use (&$messages) {
            $messages[] = $errstr;
            return true;
        });
        try {
            $fn();
        } finally {
            restore_error_handler();
        }
        return $messages;
    }

    public function testBareAmpersandInAttributeDoesNotWarn(): void
    {
        $block = null;
        $messages = $this->captureDiagnostics(function () use (&$block) {
            $block = $this->createBlock("test", '<a href="/page?a=1&b=2">Link</a>');
        });
        Assert::equals([], $messages);
        $node = $block->xpath("//a/@href");
        Assert::notNull($node);
        Assert::contains("a=1&b=2", $node->value);
    }

    public function testScriptTagWithQueryStringDoesNotWarn(): void
    {
        $markdown = '<script src="https://example.com/x.php?module=A&action=B"></script>';
        $messages = $this->captureDiagnostics(function () use ($markdown) {
            $this->createBlock("test", $markdown);
        });
        Assert::equals([], $messages);
    }

    public function testUnknownTagDoesNotWarn(): void
    {
        $block = null;
        $messages = $this->captureDiagnostics(function () use (&$block) {
            $block = $this->createBlock("test", "<my-widget>Hello</my-widget>");
        });
        Assert::equals([], $messages);
        Assert::contains("Hello", $block->content());
    }

    public function testContentSurvivesUnparsableMarkup(): void
    {
        $block = $this->createBlock("test", "# Title\n\n<p>Broken <b>markup</p>\n\nTail text");
        $node = $block->xpath("//h1");
        Assert::notNull($node);
        Assert::contains("Title", $node->textContent);
    }

    public function testLibxmlInternalErrorStateIsRestored(): void
    {
        $before = libxml_use_internal_errors(false);
        $this->createBlock("test", '<a href="/x?a=1&b=2">Link</a>');
        $after = libxml_use_internal_errors($before);
        Assert::false($after, "libxml_use_internal_errors must be restored to its previous value");
    }
}
