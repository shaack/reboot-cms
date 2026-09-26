<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

namespace Shaack\Reboot\Downloads;

/**
 * Stores files that are handed out as protected downloads, together with their
 * metadata. Everything lives in `local/downloads`, which is outside the web
 * root and additionally protected by `local/.htaccess`, so a file is only ever
 * delivered by PHP after the password was accepted.
 *
 * Layout:
 *
 *   local/downloads/index.json   metadata, keyed by token
 *   local/downloads/files/<token> the payload, stored without an extension
 *
 * An entry has these keys:
 *
 *   token            16 hex characters, the public name of the download
 *   fileName         the real name, only sent in the Content-Disposition header
 *   extension        cosmetic extension of the public link, may be empty
 *   label            optional note for the recipient, shown on the form
 *   size             size in bytes
 *   password         the six digit password, readable in the admin list
 *   createdAt        upload time as a unix timestamp
 *   firstDownloadAt  time of the first accepted download, or null
 *   downloads        list of ['time' => int, 'ip' => string] entries
 *   failedAttempts   number of wrong passwords entered so far
 *   fileDeleted      true once the payload was removed after expiry
 */
class DownloadStore
{
    /** A link is valid for one week after the upload. */
    public const DEFAULT_LINK_LIFETIME = 604800;
    /** After the first download the link only stays valid for one hour. */
    public const DEFAULT_DOWNLOAD_WINDOW = 3600;
    /** Expired entries stay in the list for 30 days, so the log remains readable. */
    public const DEFAULT_LOG_RETENTION = 2592000;
    /** After this many wrong passwords a token is locked for good. */
    public const MAX_FAILED_ATTEMPTS = 10;

    private string $dir;
    private string $filesDir;
    private string $indexFile;
    private int $linkLifetime;
    private int $downloadWindow;
    private int $logRetention;

    /**
     * @param string $baseFsPath the CMS root in the file system
     * @param array $config the `downloads` section of the site config
     */
    public function __construct(string $baseFsPath, array $config = [])
    {
        $this->dir = rtrim($baseFsPath, '/') . '/local/downloads';
        $this->filesDir = $this->dir . '/files';
        $this->indexFile = $this->dir . '/index.json';
        $this->linkLifetime = (int)($config['linkLifetime'] ?? self::DEFAULT_LINK_LIFETIME);
        $this->downloadWindow = (int)($config['downloadWindow'] ?? self::DEFAULT_DOWNLOAD_WINDOW);
        $this->logRetention = (int)($config['logRetention'] ?? self::DEFAULT_LOG_RETENTION);
    }

    // Public API

    /**
     * All entries, newest upload first.
     * @return array<int, array>
     */
    public function getEntries(): array
    {
        $entries = array_values($this->readIndex());
        usort($entries, function ($a, $b) {
            return $b['createdAt'] <=> $a['createdAt'];
        });
        return $entries;
    }

    /**
     * A single entry or null, if the token is unknown.
     */
    public function find(string $token): ?array
    {
        if (!self::isValidToken($token)) {
            return null;
        }
        $index = $this->readIndex();
        return $index[$token] ?? null;
    }

    /**
     * Adds a file to the store and returns the created entry.
     *
     * The caller provides the move as a callable, so the upload handling stays
     * in the admin page (`move_uploaded_file`) and tests can copy a fixture.
     *
     * @param callable $move receives the target path, returns true on success
     * @param string $fileName the real file name, never part of the link
     * @param string|null $label optional note shown to the recipient
     */
    public function add(callable $move, string $fileName, ?string $label = null): array
    {
        $this->ensureDirs();
        $fileName = trim(basename(str_replace('\\', '/', $fileName)));
        if ($fileName === '' || $fileName === '.' || $fileName === '..') {
            throw new \InvalidArgumentException('Invalid file name');
        }
        $token = $this->createToken();
        $targetPath = $this->getFilePath($token);
        if (!$move($targetPath)) {
            throw new \RuntimeException('Could not store the uploaded file');
        }
        @chmod($targetPath, 0600);
        $entry = [
            'token' => $token,
            'fileName' => $fileName,
            'extension' => self::extensionOf($fileName),
            'label' => ($label !== null && trim($label) !== '') ? trim($label) : null,
            'size' => (int)filesize($targetPath),
            'password' => (string)random_int(100000, 999999),
            'createdAt' => time(),
            'firstDownloadAt' => null,
            'downloads' => [],
            'failedAttempts' => 0,
            'fileDeleted' => false,
        ];
        $this->withIndex(function (array &$index) use ($entry) {
            $index[$entry['token']] = $entry;
            return true;
        });
        return $entry;
    }

    /**
     * Checks the password of a token and counts wrong attempts.
     *
     * Returns true only for a token that exists, is neither expired nor locked
     * and whose password matches. The comparison is constant time, wrong
     * attempts are counted and lock the token after MAX_FAILED_ATTEMPTS.
     */
    public function verifyPassword(string $token, string $password): bool
    {
        $entry = $this->find($token);
        if (!$entry || $this->isExpired($entry) || $this->isLocked($entry)) {
            return false;
        }
        $password = preg_replace('/\D/', '', $password);
        if ($password !== '' && hash_equals($entry['password'], $password)) {
            return true;
        }
        $this->withIndex(function (array &$index) use ($token) {
            if (isset($index[$token])) {
                $index[$token]['failedAttempts'] = ($index[$token]['failedAttempts'] ?? 0) + 1;
                return true;
            }
            return false;
        });
        return false;
    }

    /**
     * Records a download. The first one starts the short download window.
     *
     * @param string|null $ip the visitor's IP, stored anonymized
     */
    public function registerDownload(string $token, ?string $ip = null): void
    {
        $this->withIndex(function (array &$index) use ($token, $ip) {
            if (!isset($index[$token])) {
                return false;
            }
            $now = time();
            if (empty($index[$token]['firstDownloadAt'])) {
                $index[$token]['firstDownloadAt'] = $now;
            }
            $index[$token]['downloads'][] = [
                'time' => $now,
                'ip' => self::anonymizeIp($ip),
            ];
            return true;
        });
    }

    /**
     * Removes an entry and its file.
     */
    public function delete(string $token): bool
    {
        if (!self::isValidToken($token)) {
            return false;
        }
        $deleted = false;
        $this->withIndex(function (array &$index) use ($token, &$deleted) {
            if (!isset($index[$token])) {
                return false;
            }
            unset($index[$token]);
            $deleted = true;
            return true;
        });
        $path = $this->getFilePath($token);
        if (is_file($path)) {
            @unlink($path);
        }
        return $deleted;
    }

    /**
     * Deletes the files of expired links and drops entries whose log retention
     * has passed. Called on every request that touches a download link and
     * whenever the admin list is shown, so no cron job is needed.
     *
     * @return int the number of files deleted
     */
    public function purge(): int
    {
        if (!is_file($this->indexFile)) {
            return 0;
        }
        $filesDeleted = 0;
        $tokensToForget = [];
        $this->withIndex(function (array &$index) use (&$filesDeleted, &$tokensToForget) {
            $changed = false;
            $now = time();
            foreach ($index as $token => $entry) {
                if (!$this->isExpired($entry, $now)) {
                    continue;
                }
                $path = $this->getFilePath($token);
                if (is_file($path)) {
                    @unlink($path);
                    $filesDeleted++;
                }
                if (empty($entry['fileDeleted'])) {
                    $index[$token]['fileDeleted'] = true;
                    $changed = true;
                }
                if ($this->getExpiresAt($entry) + $this->logRetention <= $now) {
                    unset($index[$token]);
                    $tokensToForget[] = $token;
                    $changed = true;
                }
            }
            return $changed;
        });
        return $filesDeleted;
    }

    /**
     * The moment a link stops working. That is one week after the upload, or
     * one hour after the first download, whichever comes first.
     */
    public function getExpiresAt(array $entry): int
    {
        $hardExpiry = $entry['createdAt'] + $this->linkLifetime;
        if (!empty($entry['firstDownloadAt'])) {
            return min($hardExpiry, $entry['firstDownloadAt'] + $this->downloadWindow);
        }
        return $hardExpiry;
    }

    public function isExpired(array $entry, ?int $now = null): bool
    {
        return $this->getExpiresAt($entry) <= ($now ?? time());
    }

    public function isLocked(array $entry): bool
    {
        return ($entry['failedAttempts'] ?? 0) >= self::MAX_FAILED_ATTEMPTS;
    }

    /**
     * True if the link is usable right now.
     */
    public function isAvailable(array $entry): bool
    {
        return !$this->isExpired($entry)
            && !$this->isLocked($entry)
            && is_file($this->getFilePath($entry['token']));
    }

    /**
     * The payload's path in the file system.
     */
    public function getFilePath(string $token): string
    {
        if (!self::isValidToken($token)) {
            throw new \InvalidArgumentException('Invalid token');
        }
        return $this->filesDir . '/' . $token;
    }

    /**
     * The public path of a link, relative to the site root, e.g.
     * "/downloads/3ef938314c4d6cfe.pdf".
     */
    public static function buildLinkPath(string $prefix, array $entry): string
    {
        $extension = $entry['extension'] ?? '';
        return rtrim($prefix, '/') . '/' . $entry['token'] . ($extension ? '.' . $extension : '');
    }

    public static function isValidToken(string $token): bool
    {
        return (bool)preg_match('/^[a-f0-9]{16}$/', $token);
    }

    /**
     * Drops the last octet of an IPv4 address and the interface part of an
     * IPv6 address, so the log stays useful without storing a full address.
     */
    public static function anonymizeIp(?string $ip): string
    {
        if (!$ip) {
            return '';
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ip);
            $parts[3] = 'x';
            return implode('.', $parts);
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $parts = explode(':', $ip);
            $parts = array_slice($parts, 0, 4);
            return implode(':', $parts) . '::x';
        }
        return '';
    }

    public static function formatSize(int $bytes): string
    {
        if ($bytes >= 1073741824) return round($bytes / 1073741824, 1) . ' GB';
        if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' MB';
        if ($bytes >= 1024) return round($bytes / 1024, 1) . ' KB';
        return $bytes . ' B';
    }

    /**
     * Extension of a file name, lower case and limited to plain characters.
     * Used for the cosmetic extension of the public link.
     */
    public static function extensionOf(string $fileName): string
    {
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION) ?: '');
        return preg_match('/^[a-z0-9]{1,8}$/', $extension) ? $extension : '';
    }

    // Internals

    private function createToken(): string
    {
        $index = $this->readIndex();
        do {
            $token = bin2hex(random_bytes(8));
        } while (isset($index[$token]) || is_file($this->filesDir . '/' . $token));
        return $token;
    }

    private function readIndex(): array
    {
        if (!is_file($this->indexFile)) {
            return [];
        }
        $raw = @file_get_contents($this->indexFile);
        if ($raw === false || trim($raw) === '') {
            return [];
        }
        $index = json_decode($raw, true);
        return is_array($index) ? $index : [];
    }

    /**
     * Reads the index under an exclusive lock, hands it to the callback by
     * reference and writes it back if the callback returns true. Concurrent
     * requests cannot lose each other's changes this way.
     *
     * @param callable $fn function (array &$index): bool
     */
    private function withIndex(callable $fn): void
    {
        $this->ensureDirs();
        $handle = fopen($this->indexFile, 'c+');
        if ($handle === false) {
            throw new \RuntimeException('Could not open ' . $this->indexFile);
        }
        try {
            if (!flock($handle, LOCK_EX)) {
                throw new \RuntimeException('Could not lock ' . $this->indexFile);
            }
            $raw = stream_get_contents($handle);
            $index = [];
            if ($raw !== false && trim($raw) !== '') {
                $decoded = json_decode($raw, true);
                $index = is_array($decoded) ? $decoded : [];
            }
            $changed = $fn($index);
            if ($changed) {
                $json = json_encode($index, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                ftruncate($handle, 0);
                rewind($handle);
                fwrite($handle, $json . "\n");
                fflush($handle);
            }
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
        @chmod($this->indexFile, 0600);
    }

    private function ensureDirs(): void
    {
        foreach ([$this->dir, $this->filesDir] as $dir) {
            if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
                throw new \RuntimeException('Could not create ' . $dir);
            }
        }
    }
}
