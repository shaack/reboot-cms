<?php

namespace Shaack\Tests;

use Shaack\Reboot\AddOns\Mcp\Server;
use Shaack\Reboot\AddOns\Mcp\Tokens;
use Shaack\Reboot\AddOns\Mcp\Tools;
use Shaack\Reboot\Site;

class McpTest
{
    private string $tmpDir;
    private Site $site;

    public function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . "/test_mcp_" . uniqid();
        foreach (["/local", "/site/pages/customers/acme", "/site/pages/customers/beta", "/site/blocks/hero", "/web/media/products"] as $dir) {
            mkdir($this->tmpDir . $dir, 0755, true);
        }
        $files = [
            "/composer.json" => '{"version": "4.5.0"}',
            "/site/pages/index.yml" => "title: {en: Home, de: Start}\nblocks: []\n",
            "/site/pages/about.md" => "---\ntitle: About\n---\n# About\n",
            "/site/pages/about.de.md" => "---\ntitle: Über uns\n---\n# Über uns\n",
            "/site/pages/contact.php" => "<?php echo 'code';",
            "/site/pages/customers/acme/index.md" => "# Acme\n",
            "/site/pages/customers/beta/index.md" => "# Beta\n",
            "/site/blocks/text.php" => "<?php",
            "/site/blocks/hero/hero.php" => "<?php",
            "/site/blocks/hero/hero.yml" => "title: Hero\nfields:\n  heading: {type: text}\n  items: {type: list, fields: {title: {type: text}}}\n",
            "/site/agents.md" => "Write short sentences.",
            "/web/media/products/a.jpg" => "jpg",
            "/local/secret.txt" => "secret",
        ];
        foreach ($files as $path => $content) {
            file_put_contents($this->tmpDir . $path, $content);
        }
        $this->site = TestHelper::createSiteStub($this->tmpDir . "/site", "", ["languages" => ["en", "de"]]);
    }

    public function tearDown(): void
    {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->tmpDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->tmpDir);
    }

    private function tools(array $paths = ["/"], string $access = "read"): Tools
    {
        return new Tools($this->site, $this->tmpDir, "", ["id" => "x", "name" => "Test", "access" => $access, "paths" => $paths]);
    }

    private function rpc(Tools $tools, string $method, array $params = []): array
    {
        return (new Server($tools, "4.5.0"))->handle(["jsonrpc" => "2.0", "id" => 1, "method" => $method, "params" => $params]);
    }

    public function testTokenIsStoredHashedAndAuthenticates(): void
    {
        $tokens = new Tokens($this->tmpDir);
        $token = $tokens->create("Claude", "read", ["/"]);
        Assert::true(str_starts_with($token, "rbt_"));
        Assert::false(str_contains(file_get_contents($this->tmpDir . "/local/mcp/tokens.json"), $token), "only the hash is stored");
        $entry = $tokens->authenticate($token);
        Assert::equals("Claude", $entry["name"]);
        Assert::false(isset($entry["hash"]));
        Assert::null($tokens->authenticate("rbt_wrong"));
        Assert::null($tokens->authenticate(""));
    }

    public function testRevokedTokenIsRejected(): void
    {
        $tokens = new Tokens($this->tmpDir);
        $token = $tokens->create("Claude", "write", ["/blog"]);
        $id = $tokens->authenticate($token)["id"];
        $tokens->revoke($id);
        Assert::null($tokens->authenticate($token));
        Assert::equals([], $tokens->getTokens());
    }

    public function testTokenNeedsNameAccessAndPath(): void
    {
        $tokens = new Tokens($this->tmpDir);
        foreach ([["", "read", ["/"]], ["A", "admin", ["/"]], ["A", "read", []], ["A", "read", ["/../local"]]] as [$name, $access, $paths]) {
            $failed = false;
            try {
                $tokens->create($name, $access, $paths);
            } catch (\InvalidArgumentException $e) {
                $failed = true;
            }
            Assert::true($failed, "refused: " . json_encode([$name, $access, $paths]));
        }
    }

    public function testAllows(): void
    {
        Assert::true(Tokens::allows(["/"], "/about.md"));
        Assert::true(Tokens::allows(["/customers/acme"], "/customers/acme/index.md"));
        Assert::false(Tokens::allows(["/customers/acme"], "/customers/acme-old/index.md"));
        Assert::false(Tokens::allows(["/customers/acme"], "/about.md"));
        Assert::equals(["/blog", "/"], Tokens::normalizePaths(["blog/", " /blog", "/"]));
    }

    public function testListPagesLeavesOutPhpAndForeignPaths(): void
    {
        $pages = $this->tools()->call("list_pages", [])["pages"];
        $paths = array_column($pages, "path");
        Assert::equals(["/about.de.md", "/about.md", "/customers/acme/index.md", "/customers/beta/index.md", "/index.yml"], $paths);
        $byPath = array_column($pages, null, "path");
        Assert::equals("/de/about", $byPath["/about.de.md"]["url"]);
        Assert::equals("Über uns", $byPath["/about.de.md"]["title"]);
        Assert::equals("Home", $byPath["/index.yml"]["title"]);
        Assert::equals("/customers/acme/", $byPath["/customers/acme/index.md"]["url"]);

        $paths = array_column($this->tools(["/customers/acme"])->call("list_pages", [])["pages"], "path");
        Assert::equals(["/customers/acme/index.md"], $paths);
    }

    public function testReadPage(): void
    {
        $page = $this->tools()->call("read_page", ["path" => "/about.md"]);
        Assert::equals("markdown", $page["format"]);
        Assert::equals(md5($page["content"]), $page["md5"]);
        Assert::contains("# About", $page["content"]);
    }

    public function testReadPageRefusesEverythingElse(): void
    {
        $tools = $this->tools(["/customers/acme"]);
        foreach (["/customers/beta/index.md", "/contact.php", "/../local/secret.txt", "/customers/acme/../beta/index.md", "customers/acme/index.md", "/missing.md"] as $path) {
            $failed = false;
            try {
                $tools->call("read_page", ["path" => $path]);
            } catch (\InvalidArgumentException $e) {
                $failed = true;
            }
            Assert::true($failed, "refused: " . $path);
        }
    }

    public function testBlockTypesAndMedia(): void
    {
        $types = $this->tools()->call("list_block_types", []);
        Assert::equals("hero", $types["structured"][0]["name"]);
        Assert::equals("text", $types["structured"][0]["fields"]["items"]["fields"]["title"]["type"]);
        Assert::equals(["text"], $types["markdown"]);
        $media = $this->tools()->call("list_media", ["folder" => "/products"]);
        Assert::equals("/media/products/a.jpg", $media["items"][0]["url"]);
        $failed = false;
        try {
            $this->tools()->call("list_media", ["folder" => "/../../local"]);
        } catch (\InvalidArgumentException $e) {
            $failed = true;
        }
        Assert::true($failed, "no way out of web/media");
    }

    public function testServerProtocol(): void
    {
        $tools = $this->tools();
        $init = $this->rpc($tools, "initialize", ["protocolVersion" => "2025-06-18"]);
        Assert::equals("2025-06-18", $init["result"]["protocolVersion"]);
        Assert::contains("Write short sentences.", $init["result"]["instructions"]);
        $names = array_column($this->rpc($tools, "tools/list")["result"]["tools"], "name");
        Assert::true(in_array("read_page", $names, true));
        $call = $this->rpc($tools, "tools/call", ["name" => "read_page", "arguments" => ["path" => "/about.md"]]);
        Assert::false($call["result"]["isError"]);
        Assert::equals("/about.md", $call["result"]["structuredContent"]["path"]);
        $call = $this->rpc($tools, "tools/call", ["name" => "read_page", "arguments" => ["path" => "/contact.php"]]);
        Assert::true($call["result"]["isError"]);
        Assert::equals(-32601, $this->rpc($tools, "nonsense")["error"]["code"]);
        Assert::null((new Server($tools, ""))->handle(["jsonrpc" => "2.0", "method" => "notifications/initialized"]));
    }
}
