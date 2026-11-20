<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

namespace Shaack\Reboot\AddOns\Mcp;

use Shaack\Utils\FileSystemUtils;

/**
 * Upload addresses for media. The tool create_upload_url makes one for a file
 * in `web/media`, the agent sends the file there with a plain HTTP PUT, for
 * example `curl -T team.jpg <url>`. So the file does not pass through the
 * agent as base64 text, which costs it its context.
 *
 * An address holds a random id and works once, for 15 minutes, and only for
 * its path. `local/mcp/uploads.json` keeps the sha256 of the id, the path,
 * the md5 of the file it may replace and the token it was made for:
 *
 *   path       the file below web/media
 *   md5        the md5 of the file it replaces, empty for a new file
 *   tokenId    the token that made it, the upload fails once it is revoked
 *   expiresAt  unix timestamp
 */
class Uploads
{
    public const TTL = 15 * 60;

    private string $dir;
    private string $file;

    public function __construct(string $baseFsPath)
    {
        $this->dir = rtrim($baseFsPath, '/') . '/local/mcp';
        $this->file = $this->dir . '/uploads.json';
    }

    /**
     * A new upload address, returns its id and when it expires.
     */
    public function create(string $path, string $md5, string $tokenId, ?int $now = null): array
    {
        $now = $now ?? time();
        $id = bin2hex(random_bytes(24));
        $expiresAt = $now + self::TTL;
        $this->withUploads(function (array &$uploads) use ($id, $path, $md5, $tokenId, $expiresAt, $now) {
            self::dropExpired($uploads, $now);
            $uploads[hash('sha256', $id)] = ['path' => $path, 'md5' => $md5, 'tokenId' => $tokenId, 'expiresAt' => $expiresAt];
            return true;
        });
        return ['id' => $id, 'expiresAt' => $expiresAt];
    }

    /**
     * The upload of the id, null for an unknown or expired one. It is used up
     * with this, a second request with the same id gets null.
     */
    public function redeem(string $id, ?int $now = null): ?array
    {
        $now = $now ?? time();
        if (!preg_match('/^[0-9a-f]{48}$/', $id) || !is_file($this->file)) {
            return null;
        }
        $hash = hash('sha256', $id);
        $upload = null;
        $this->withUploads(function (array &$uploads) use ($hash, $now, &$upload) {
            $changed = self::dropExpired($uploads, $now);
            if (!isset($uploads[$hash])) {
                return $changed;
            }
            $upload = $uploads[$hash];
            unset($uploads[$hash]);
            return true;
        });
        return $upload;
    }

    private static function dropExpired(array &$uploads, int $now): bool
    {
        $count = count($uploads);
        $uploads = array_filter($uploads, fn($upload) => is_array($upload) && ($upload['expiresAt'] ?? 0) > $now);
        return count($uploads) !== $count;
    }

    private function withUploads(callable $fn): void
    {
        if (!is_dir($this->dir) && !@mkdir($this->dir, 0700, true) && !is_dir($this->dir)) {
            throw new \RuntimeException('Could not create ' . $this->dir);
        }
        FileSystemUtils::updateJson($this->file, $fn);
    }
}
