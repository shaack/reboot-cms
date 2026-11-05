<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

namespace Shaack\Reboot\AddOns\Admin;

class MediaHelper
{
    /** Blocks PHP in the media folder, the first part of the shipped `web/media/.htaccess` */
    public const HTACCESS_NO_PHP = <<<'HTACCESS'
# Prevent PHP execution in media directory
<FilesMatch "\.(php|phtml|php3|php4|php5|php7|php8|phps|phar)$">
    <IfModule mod_authz_core.c>
        Require all denied
    </IfModule>
    <IfModule !mod_authz_core.c>
        Order Deny,Allow
        Deny from all
    </IfModule>
</FilesMatch>
HTACCESS;

    /** The sandbox for active file types, the second part of the shipped `web/media/.htaccess` */
    public const HTACCESS_SANDBOX = <<<'HTACCESS'
<IfModule mod_headers.c>
    Header set X-Content-Type-Options "nosniff"
    # An SVG or XML file with a script, opened directly, would run on the origin
    # of the site and its admin. The sandbox does not affect <img> embedding.
    # Not for all files, Chrome refuses to show a sandboxed PDF.
    <FilesMatch "\.(svgz?|xml|xsl|html?|xhtml)$">
        Header set Content-Security-Policy "sandbox; default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'"
    </FilesMatch>
</IfModule>
HTACCESS;

    public static function hasSandbox(string $htaccessFile): bool
    {
        return is_file($htaccessFile) && str_contains((string)file_get_contents($htaccessFile), 'sandbox');
    }

    /**
     * Adds the sandbox to the `.htaccess` of the media folder. An existing file
     * keeps its content, the block is appended. A missing file is created like
     * the shipped one. The Updater does not touch `web/`, so older
     * installations get the sandbox this way.
     */
    public static function addSandbox(string $htaccessFile): void
    {
        if (self::hasSandbox($htaccessFile)) {
            return;
        }
        if (is_file($htaccessFile)) {
            $content = (string)file_get_contents($htaccessFile);
            $content = rtrim($content) . "\n\n" . self::HTACCESS_SANDBOX . "\n";
        } else {
            $content = self::HTACCESS_NO_PHP . "\n\n" . self::HTACCESS_SANDBOX . "\n";
        }
        if (@file_put_contents($htaccessFile, $content, LOCK_EX) === false) {
            throw new \RuntimeException("Could not write " . basename(dirname($htaccessFile)) . "/.htaccess, check the permissions");
        }
    }

    public static function formatFileSize(int $bytes): string
    {
        if ($bytes >= 1048576) return round($bytes / 1048576, 1) . " MB";
        if ($bytes >= 1024) return round($bytes / 1024, 1) . " KB";
        return $bytes . " B";
    }

    public static function isImageType(string $mimeType): bool
    {
        return str_starts_with($mimeType, 'image/');
    }

    /**
     * True if an SVG or XML file can run code when opened directly. The browser
     * runs it on the origin of the site and its admin, so such files are not
     * accepted as media. Other extensions are never active, the server sends
     * them with their own content type.
     */
    public static function hasActiveContent(string $fsPath, string $extension): bool
    {
        if (!in_array(strtolower($extension), ['svg', 'xml'], true)) {
            return false;
        }
        $content = @file_get_contents($fsPath);
        if ($content === false) {
            return true;
        }
        // entities would hide a "javascript:" in an attribute
        $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5 | ENT_XML1, 'UTF-8');
        return (bool)preg_match(
            '/<\s*(\w+:)?(script|foreignObject|iframe|embed|object|handler)\b'
            . '|\son\w+\s*=|javascript\s*:|<\?xml-stylesheet/i',
            $content
        );
    }
}
