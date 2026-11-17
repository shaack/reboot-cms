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
            "/site/pages/index.yml" => "title: {en: Home, de: Start}\nblocks:\n  - type: hero\n    heading: Welcome\n    items:\n      - {title: One}\n",
            "/site/pages/about.md" => "---\ntitle: About\n---\n# About\n",
            "/site/pages/about.de.md" => "---\ntitle: Über uns\n---\n# Über uns\n",
            "/site/pages/contact.php" => "<?php echo 'code';",
            "/site/pages/customers/acme/index.md" => "# Acme\n",
            "/site/pages/customers/beta/index.md" => "# Beta\n",
            "/site/blocks/text.php" => "<?php",
            "/site/blocks/hero/hero.php" => "<?php",
            "/site/blocks/hero/hero.yml" => "title: Hero\nfields:\n  heading: {type: text}\n  image: {type: image}\n  items: {type: list, fields: {title: {type: text}}}\n",
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

    private function fails(callable $fn, string $contains = ""): void
    {
        try {
            $fn();
        } catch (\InvalidArgumentException $e) {
            if ($contains !== "") {
                Assert::contains($contains, $e->getMessage());
            }
            return;
        }
        Assert::true(false, "expected an error" . ($contains ? " with '$contains'" : ""));
    }

    public function testWritePageWithConflictCheckAndHistory(): void
    {
        $tools = $this->tools(["/"], "write");
        $created = $tools->call("write_page", ["path" => "/news/first.md", "content" => "# First\r\n"]);
        Assert::true($created["created"]);
        Assert::equals("# First\n", file_get_contents($this->tmpDir . "/site/pages/news/first.md"));
        $this->fails(fn() => $tools->call("write_page", ["path" => "/news/first.md", "content" => "# Again\n"]), "Read it first");
        $this->fails(fn() => $tools->call("write_page", ["path" => "/news/first.md", "content" => "# Again\n", "md5" => "stale"]), "Conflict");
        $written = $tools->call("write_page", ["path" => "/news/first.md", "content" => "# Second\n", "md5" => md5("# First\n")]);
        Assert::false($written["created"]);
        $history = glob($this->tmpDir . "/local/history/pages/news/first/*.md");
        Assert::equals(1, count($history));
        Assert::equals("# First\n", file_get_contents($history[0]));
        Assert::contains('"action":"changed"', file_get_contents($this->tmpDir . "/local/mcp/changes.log"));
    }

    public function testWriteNeedsWriteAccessAndPath(): void
    {
        $call = $this->rpc($this->tools(), "tools/call", ["name" => "write_page", "arguments" => ["path" => "/x.md", "content" => "x"]]);
        Assert::true($call["result"]["isError"]);
        Assert::false(in_array("write_page", array_column($this->rpc($this->tools(), "tools/list")["result"]["tools"], "name"), true));
        $tools = $this->tools(["/customers/acme"], "write");
        $this->fails(fn() => $tools->call("write_page", ["path" => "/customers/beta/index.md", "content" => "x", "md5" => md5("# Beta\n")]), "may not");
        $this->fails(fn() => $tools->call("write_page", ["path" => "/customers/acme/x.php", "content" => "<?php"]), "Invalid page path");
        $tools->call("write_page", ["path" => "/customers/acme/index.md", "content" => "# Acme 2\n", "md5" => md5("# Acme\n")]);
        Assert::equals("# Acme 2\n", file_get_contents($this->tmpDir . "/site/pages/customers/acme/index.md"));
    }

    public function testWriteStructuredPage(): void
    {
        $tools = $this->tools(["/"], "write");
        $this->fails(fn() => $tools->call("write_page", ["path" => "/new.yml", "content" => "title: [unclosed"]), "YAML");
        $result = $tools->call("write_page", ["path" => "/new.yml", "content" => "title: New\nblocks:\n  - {type: nonsense}\n"]);
        Assert::equals(["Block 0: there is no block type 'nonsense'"], $result["problems"]);
    }

    public function testDeletePage(): void
    {
        $tools = $this->tools(["/"], "write");
        $this->fails(fn() => $tools->call("delete_page", ["path" => "/about.md", "md5" => "stale"]), "Conflict");
        $tools->call("delete_page", ["path" => "/about.md", "md5" => md5("---\ntitle: About\n---\n# About\n")]);
        Assert::false(is_file($this->tmpDir . "/site/pages/about.md"));
        Assert::equals(1, count(glob($this->tmpDir . "/local/history/pages/about/*.md")));
    }

    public function testMarkdownTranslation(): void
    {
        $tools = $this->tools(["/"], "write");
        $tools->call("write_page", ["path" => "/team.md", "content" => "# Team\n"]);
        $byPath = [];
        foreach ($tools->call("list_translations", [])["translations"] as $entry) {
            $byPath[$entry["path"]] = $entry["status"];
        }
        Assert::equals("missing", $byPath["/team.md"]);
        Assert::equals("unknown", $byPath["/about.md"]);
        Assert::false(isset($byPath["/about.de.md"]), "a translation is no source");

        $read = $tools->call("read_translation", ["path" => "/team.md", "language" => "de"]);
        Assert::null($read["target"]);
        $tools->call("write_translation", ["path" => "/team.md", "language" => "de", "content" => "# Das Team\n", "sourceMd5" => $read["sourceMd5"]]);
        Assert::equals("# Das Team\n", file_get_contents($this->tmpDir . "/site/pages/team.de.md"));
        Assert::equals("current", $tools->call("read_translation", ["path" => "/team.md", "language" => "de"])["status"]);

        $tools->call("write_page", ["path" => "/team.md", "content" => "# Our team\n", "md5" => md5("# Team\n")]);
        $read = $tools->call("read_translation", ["path" => "/team.md", "language" => "de"]);
        Assert::equals("outdated", $read["status"]);
        Assert::equals("# Team\n", $read["previousSource"]);
        $this->fails(fn() => $tools->call("write_translation", ["path" => "/team.md", "language" => "de", "content" => "# Unser Team\n",
            "sourceMd5" => md5("# Team\n"), "targetMd5" => $read["targetMd5"]]), "source");
        $this->fails(fn() => $tools->call("read_translation", ["path" => "/team.md", "language" => "fr"]), "no language");
    }

    public function testStructuredTranslation(): void
    {
        $tools = $this->tools(["/"], "write");
        $read = $tools->call("read_translation", ["path" => "/index.yml", "language" => "de"]);
        $fields = array_column($read["fields"], null, "pointer");
        Assert::equals(["title", "blocks.0.heading", "blocks.0.items.0.title"], array_keys($fields));
        Assert::equals("unknown", $fields["title"]["status"]);
        Assert::equals("missing", $fields["blocks.0.heading"]["status"]);
        $this->fails(fn() => $tools->call("write_translation", ["path" => "/index.yml", "language" => "de", "md5" => $read["md5"],
            "fields" => ["blocks.0.type" => "x"]]), "no text field");
        $tools->call("write_translation", ["path" => "/index.yml", "language" => "de", "md5" => $read["md5"],
            "fields" => ["blocks.0.heading" => "Willkommen", "blocks.0.items.0.title" => "Eins"]]);
        $data = \Symfony\Component\Yaml\Yaml::parseFile($this->tmpDir . "/site/pages/index.yml");
        Assert::equals(["en" => "Welcome", "de" => "Willkommen"], $data["blocks"][0]["heading"]);
        Assert::equals(["en" => "One", "de" => "Eins"], $data["blocks"][0]["items"][0]["title"]);
        Assert::equals(["en" => "Home", "de" => "Start"], $data["title"]);
        $fields = array_column($tools->call("read_translation", ["path" => "/index.yml", "language" => "de"])["fields"], null, "pointer");
        Assert::equals("current", $fields["blocks.0.heading"]["status"]);
    }

    public function testUploadMedia(): void
    {
        $this->fails(fn() => $this->tools(["/blog"], "write")->call("upload_media", ["path" => "/a.png", "base64" => base64_encode("x")]), "path /");
        $tools = $this->tools(["/"], "write");
        $this->fails(fn() => $tools->call("upload_media", ["path" => "/a.svg", "base64" => base64_encode("<svg/>")]), "Invalid media path");
        $this->fails(fn() => $tools->call("upload_media", ["path" => "/../a.png", "base64" => base64_encode("x")]), "Invalid media path");
        $result = $tools->call("upload_media", ["path" => "/new/b.png", "base64" => base64_encode("png")]);
        Assert::equals("/media/new/b.png", $result["url"]);
        $this->fails(fn() => $tools->call("upload_media", ["path" => "/new/b.png", "base64" => base64_encode("other")]), "exists");
        $tools->call("upload_media", ["path" => "/new/b.png", "base64" => base64_encode("other"), "md5" => md5("png")]);
        Assert::equals("other", file_get_contents($this->tmpDir . "/web/media/new/b.png"));
    }
}
