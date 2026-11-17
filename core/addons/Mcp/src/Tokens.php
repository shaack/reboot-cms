<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

namespace Shaack\Reboot\AddOns\Mcp;

use Shaack\Utils\FileSystemUtils;

/**
 * The access tokens of the MCP endpoint, stored in `local/mcp/tokens.json`.
 * A token is shown once when it is created, the file only keeps its SHA-256
 * hash. Everything the file holds, keyed by a short id:
 *
 *   name        what the token is for, "Claude Code" or "Customer tasks"
 *   hash        sha256 of the token
 *   access      "read" or "write"
 *   paths       the page paths the token may touch, "/" for all, "/blog" for
 *               everything below `site/pages/blog/`
 *   createdAt   unix timestamp
 *   lastUsedAt  unix timestamp of the last request, or null
 */
class Tokens
{
    public const ACCESS = ["read", "write"];
    private const PREFIX = "rbt_";

    private string $dir;
    private string $file;

    public function __construct(string $baseFsPath)
    {
        $this->dir = rtrim($baseFsPath, '/') . '/local/mcp';
        $this->file = $this->dir . '/tokens.json';
    }

    /**
     * All tokens keyed by id, without their hashes, the newest first.
     */
    public function getTokens(): array
    {
        $tokens = $this->read();
        foreach ($tokens as &$token) {
            unset($token['hash']);
        }
        uasort($tokens, fn($a, $b) => ($b['createdAt'] ?? 0) <=> ($a['createdAt'] ?? 0));
        return $tokens;
    }

    /**
     * Creates a token and returns it. It cannot be read again later.
     * @param string[] $paths
     */
    public function create(string $name, string $access, array $paths): string
    {
        $name = trim($name);
        if ($name === '') {
            throw new \InvalidArgumentException("The token needs a name");
        }
        if (!in_array($access, self::ACCESS, true)) {
            throw new \InvalidArgumentException("Unknown access '$access'");
        }
        $paths = self::normalizePaths($paths);
        if (!$paths) {
            throw new \InvalidArgumentException("The token needs at least one path, / for all pages");
        }
        $token = self::PREFIX . bin2hex(random_bytes(24));
        $this->withTokens(function (array &$tokens) use ($name, $access, $paths, $token) {
            do {
                $id = bin2hex(random_bytes(4));
            } while (isset($tokens[$id]));
            $tokens[$id] = [
                'name' => $name,
                'hash' => hash('sha256', $token),
                'access' => $access,
                'paths' => $paths,
                'createdAt' => time(),
                'lastUsedAt' => null,
            ];
            return true;
        });
        return $token;
    }

    public function revoke(string $id): void
    {
        $this->withTokens(function (array &$tokens) use ($id) {
            if (!isset($tokens[$id])) {
                throw new \InvalidArgumentException("Unknown token");
            }
            unset($tokens[$id]);
            return true;
        });
    }

    /**
     * The token entry with its id, null for an unknown token. Records the use,
     * at most once a minute, so a busy agent does not rewrite the file all the time.
     */
    public function authenticate(string $token, ?int $now = null): ?array
    {
        if (!str_starts_with($token, self::PREFIX)) {
            return null;
        }
        $now = $now ?? time();
        $hash = hash('sha256', $token);
        foreach ($this->read() as $id => $entry) {
            if (!hash_equals((string)($entry['hash'] ?? ''), $hash)) {
                continue;
            }
            if (($entry['lastUsedAt'] ?? 0) < $now - 60) {
                $this->withTokens(function (array &$tokens) use ($id, $now) {
                    if (!isset($tokens[$id])) {
                        return false;
                    }
                    $tokens[$id]['lastUsedAt'] = $now;
                    return true;
                });
            }
            unset($entry['hash']);
            return ['id' => (string)$id] + $entry;
        }
        return null;
    }

    /**
     * Whether `$path`, a page path like `/blog/first-post.md`, lies below one of the paths.
     * @param string[] $allowed
     */
    public static function allows(array $allowed, string $path): bool
    {
        foreach ($allowed as $prefix) {
            $prefix = rtrim($prefix, '/');
            if ($prefix === '' || $path === $prefix || str_starts_with($path, $prefix . '/')) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return string[] absolute, without trailing slash, `/` for all, no duplicates
     */
    public static function normalizePaths(array $paths): array
    {
        $result = [];
        foreach ($paths as $path) {
            $path = trim((string)$path);
            if ($path === '') {
                continue;
            }
            if (str_contains($path, '..') || str_contains($path, '\\')) {
                throw new \InvalidArgumentException("Invalid path '$path'");
            }
            $path = '/' . trim(preg_replace('#/+#', '/', $path), '/');
            if (!in_array($path, $result, true)) {
                $result[] = $path;
            }
        }
        return $result;
    }

    private function read(): array
    {
        if (!is_file($this->file)) {
            return [];
        }
        $decoded = json_decode((string)file_get_contents($this->file), true);
        return is_array($decoded) ? $decoded : [];
    }

    private function withTokens(callable $fn): void
    {
        if (!is_dir($this->dir) && !@mkdir($this->dir, 0700, true) && !is_dir($this->dir)) {
            throw new \RuntimeException('Could not create ' . $this->dir);
        }
        FileSystemUtils::updateJson($this->file, $fn);
    }
}
