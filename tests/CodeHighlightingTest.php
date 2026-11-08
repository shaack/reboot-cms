<?php

namespace Shaack\Tests;

use Shaack\Reboot\AddOns\CodeHighlighting\Highlighter;

class CodeHighlightingTest
{
    public function testKeywordsStringsNumbersAndCalls(): void
    {
        $html = Highlighter::highlight('function add(a) { return a + 2.5 + "x"; }', "c");
        Assert::contains('<span class="tok-keyword">function</span>', $html);
        Assert::contains('<span class="tok-function">add</span>(a)', $html);
        Assert::contains('<span class="tok-number">2.5</span>', $html);
        Assert::contains('<span class="tok-string">"x"</span>', $html);
    }

    public function testACommentMarkerInAStringOrUrlIsNoComment(): void
    {
        $html = Highlighter::highlight('const url = "http://example.com"; // note', "c");
        Assert::contains('<span class="tok-string">"http://example.com"</span>', $html);
        Assert::contains('<span class="tok-comment">// note</span>', $html);
        $shell = Highlighter::highlight("git clone https://github.com/a/b.git", "shell");
        Assert::false(str_contains($shell, "tok-comment"), "a URL in a shell line");
    }

    public function testTheLanguageDecidesWhatAHashIs(): void
    {
        Assert::contains('<span class="tok-comment"># hi</span>', Highlighter::highlight("x = 1  # hi", "hash"));
        Assert::false(str_contains(Highlighter::highlight("color=#fff", "shell"), "tok-comment"), "# inside a word in a shell");
        Assert::false(str_contains(Highlighter::highlight("a = b # c", "c"), "tok-comment"), "# in JavaScript");
    }

    public function testShellApostropheAndVariables(): void
    {
        $html = Highlighter::highlight("echo don't \$HOME", "shell");
        Assert::contains("don't", $html, "a lone apostrophe starts no string");
        Assert::contains('<span class="tok-variable">$HOME</span>', $html);
    }

    public function testShellCommandsAtTheStartOfACommand(): void
    {
        $html = Highlighter::highlight("git clone https://x.org/a.git\n./run.sh && cd a | tee b\nFOO=1 make \\\n  install", "shell");
        Assert::contains('<span class="tok-function">git</span> clone', $html);
        Assert::contains('<span class="tok-function">./run.sh</span> &amp;&amp; <span class="tok-function">cd</span> a | <span class="tok-function">tee</span> b', $html);
        Assert::contains("\nFOO=", $html, "an assignment is no command");
        Assert::contains("\n  install", $html, "a continued line starts no command");
        Assert::contains('<span class="tok-keyword">if</span>', Highlighter::highlight("if true; then", "shell"));
    }

    public function testSqlKeywordsInAnyCase(): void
    {
        $html = Highlighter::highlight("SELECT name from users -- c", "sql");
        Assert::contains('<span class="tok-keyword">SELECT</span>', $html);
        Assert::contains('<span class="tok-keyword">from</span>', $html);
        Assert::contains('<span class="tok-comment">-- c</span>', $html);
    }

    public function testMarkupTagsAttributesAndComments(): void
    {
        $html = Highlighter::highlight('<!-- c --><a href="x.html">A &amp; B</a>', "markup");
        Assert::contains('<span class="tok-comment">&lt;!-- c --&gt;</span>', $html);
        Assert::contains('<span class="tok-tag">&lt;a</span> <span class="tok-attr">href</span>=<span class="tok-string">"x.html"</span>', $html);
        Assert::contains("A &amp;amp; B", $html, "the text is escaped");
    }

    public function testCssPropertiesButNoSelectors(): void
    {
        $html = Highlighter::highlight("@media (min-width: 40em) { a:hover { color: #fff; } }", "css");
        Assert::contains('<span class="tok-keyword">@media</span>', $html);
        Assert::contains('<span class="tok-property">color</span>: <span class="tok-number">#fff</span>', $html);
        Assert::contains("{ a:hover {", $html, "a selector with a pseudo class stays plain");
    }

    public function testAPhpTemplateHasHtmlTagsAttributesAndComments(): void
    {
        $html = Highlighter::highlight('<!-- all from part 1 --><div class="row"><?= $x ?>', "php");
        Assert::contains('<span class="tok-comment">&lt;!-- all from part 1 --&gt;</span>', $html);
        Assert::contains('<span class="tok-tag">&lt;div</span> <span class="tok-attr">class</span>=<span class="tok-string">"row"</span>', $html);
        Assert::contains('<span class="tok-variable">$x</span>', $html);
    }

    public function testYamlKeysAreProperties(): void
    {
        $html = Highlighter::highlight("title: Seminars # a\nfields:\n  price: {type: number, min: 0, url: a#b}", "yaml");
        Assert::contains('<span class="tok-property">title</span>: Seminars <span class="tok-comment"># a</span>', $html);
        Assert::contains('<span class="tok-property">fields</span>:', $html);
        Assert::contains('{<span class="tok-property">type</span>: number, <span class="tok-property">min</span>: <span class="tok-number">0</span>', $html);
        Assert::contains("a#b}", $html, "a # inside a value is no comment");
    }

    public function testPageGetsTheStyleOnceAndKeepsTheLanguageClass(): void
    {
        $page = "<html><head></head><body>"
            . '<pre><code class="language-php">&lt;?php echo "a &amp; b";</code></pre>'
            . "<pre><code>return 1;</code></pre></body></html>";
        $html = Highlighter::highlightPage($page);
        Assert::equals(1, substr_count($html, '<style id="code-highlighting">'));
        Assert::true(strpos($html, "<style") < strpos($html, "</head>"), "in the head");
        Assert::contains('<pre><code class="language-php">&lt;?php <span class="tok-keyword">echo</span> <span class="tok-string">"a &amp; b"</span>;</code></pre>', $html);
        Assert::contains('<pre><code><span class="tok-keyword">return</span>', $html, "a block without language");
    }

    public function testMarkdownCommentsHeadingsAndSeparators(): void
    {
        $html = Highlighter::highlight("<!-- hero -->\n\n# Headline\n\nA **b** c #1\n\n---\n\n[x](y)", "markdown");
        Assert::contains('<span class="tok-comment">&lt;!-- hero --&gt;</span>', $html);
        Assert::contains('<span class="tok-keyword"># Headline</span>', $html);
        Assert::contains('<span class="tok-tag">---</span>', $html);
        Assert::contains("A **b** c #1\n", $html, "the text stays plain");
        Assert::contains('<span class="tok-string">[x](y)</span>', $html);
        Assert::contains('<span class="tok-string">![Alt](/media/a.jpg)</span>', Highlighter::highlight("![Alt](/media/a.jpg)", "markdown"));
    }

    public function testPlainTextAndBlocksWithMarkupStay(): void
    {
        $page = '<pre><code class="language-text">return 1;</code></pre><pre><code><b>return</b> 1;</code></pre>';
        Assert::equals($page, Highlighter::highlightPage($page));
    }
}
