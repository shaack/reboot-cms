<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

namespace Shaack\Reboot\AddOns\Mcp;

use Shaack\Utils\FileSystemUtils;

/**
 * The files agents store in `web/media`, through the tool upload_media or an
 * upload address, see Uploads. Media are addressed by their path below
 * `web/media`, `/blog/2026/team.jpg`.
 */
class Media
{
    /** What may be stored. No SVG and no HTML, they could carry scripts. */
    public const EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'pdf', 'mp4', 'webm', 'mp3', 'zip', 'txt', 'csv'];
    public const MAX_BYTES = 20 * 1024 * 1024;

    private string $root;
    private string $baseWebPath;

    public function __construct(string $baseFsPath, string $baseWebPath)
    {
        $this->root = rtrim($baseFsPath, '/') . '/web/media';
        $this->baseWebPath = rtrim($baseWebPath, '/');
    }

    /**
     * Throws when the token may not store media or the path is no media file.
     */
    public static function check(array $token, string $path): void
    {
        if (!in_array('/', $token['paths'] ?? [], true)) {
            throw new \InvalidArgumentException("Only a token with the path / may upload media");
        }
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (!preg_match('#^(/[A-Za-z0-9_-][A-Za-z0-9._-]*)+$#', $path) || str_contains($path, '..')
            || !in_array($extension, self::EXTENSIONS, true)) {
            throw new \InvalidArgumentException("Invalid media path '$path', allowed are " . implode(', ', self::EXTENSIONS));
        }
    }

    /**
     * Throws when the file exists and `$md5` is not the md5 of its current content.
     */
    public function checkReplace(string $path, string $md5): void
    {
        $file = $this->root . $path;
        if (is_file($file) && md5_file($file) !== $md5) {
            throw new \InvalidArgumentException("'$path' exists. Pass the md5 of its current content to replace it");
        }
    }

    /**
     * Stores the file and returns its path, address, md5 and size.
     */
    public function store(array $token, string $path, string $content, string $md5): array
    {
        self::check($token, $path);
        if ($content === '') {
            throw new \InvalidArgumentException("The file is empty");
        }
        if (strlen($content) > self::MAX_BYTES) {
            throw new \InvalidArgumentException("The file is larger than " . (self::MAX_BYTES >> 20) . " MB");
        }
        $root = realpath($this->root);
        if ($root === false) {
            throw new \RuntimeException("There is no folder web/media");
        }
        $file = $root . $path;
        $parent = dirname($file);
        if (!is_dir($parent) && !@mkdir($parent, 0755, true) && !is_dir($parent)) {
            throw new \RuntimeException("Could not create the folder of $path");
        }
        if (!FileSystemUtils::isInside(realpath($parent), $root)) {
            throw new \InvalidArgumentException("Invalid media path '$path'");
        }
        $this->checkReplace($path, $md5);
        FileSystemUtils::writeAtomic($file, $content);
        return ['path' => $path, 'url' => $this->baseWebPath . '/media' . $path, 'md5' => md5($content), 'size' => strlen($content)];
    }
}
