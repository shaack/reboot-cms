<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

namespace Shaack\Reboot\AddOns\Authentication;

/**
 * Tokens for "Stay signed in". A token is `selector:validator`, the selector
 * finds the entry, the validator is compared against its SHA-256 hash, so a
 * copy of the file is worthless without the cookies. Each token is good for
 * one use, the AddOn issues a fresh one every time it logs a user in from the
 * cookie. The file lives in `local/`, outside the web root.
 */
class RememberMeStore
{
    public const LIFETIME = 14 * 24 * 60 * 60; // two weeks

    private string $file;

    public function __construct(string $baseFsPath)
    {
        $this->file = rtrim($baseFsPath, '/') . '/local/remember-tokens.json';
    }

    /**
     * Issue a token for a user. Returns the raw token for the cookie.
     */
    public function create(string $username, ?int $now = null): string
    {
        $now = $now ?? time();
        $selector = bin2hex(random_bytes(9));
        $validator = bin2hex(random_bytes(32));
        $this->update(function (array $tokens) use ($selector, $validator, $username, $now) {
            $tokens = self::withoutExpired($tokens, $now);
            $tokens[$selector] = [
                'user' => $username,
                'hash' => hash('sha256', $validator),
                'expires' => $now + self::LIFETIME,
            ];
            return $tokens;
        });
        return $selector . ':' . $validator;
    }

    /**
     * Redeem a token: returns the user if the token is valid and not expired,
     * null otherwise. The token is deleted either way, a valid one is meant
     * to be replaced by a fresh one.
     */
    public function consume(?string $rawToken, ?int $now = null): ?string
    {
        $now = $now ?? time();
        $parts = explode(':', (string)$rawToken, 2);
        if (count($parts) !== 2 || !ctype_xdigit($parts[0]) || !ctype_xdigit($parts[1])) {
            return null;
        }
        [$selector, $validator] = $parts;
        $user = null;
        $this->update(function (array $tokens) use ($selector, $validator, $now, &$user) {
            $tokens = self::withoutExpired($tokens, $now);
            $entry = $tokens[$selector] ?? null;
            if ($entry !== null) {
                unset($tokens[$selector]);
                if (hash_equals($entry['hash'], hash('sha256', $validator))) {
                    $user = $entry['user'];
                }
            }
            return $tokens;
        });
        return $user;
    }

    /**
     * Forget every token of a user, for a password change, a deletion or a
     * disabled account.
     */
    public function deleteForUser(string $username): void
    {
        $this->update(function (array $tokens) use ($username) {
            return array_filter($tokens, fn($entry) => $entry['user'] !== $username);
        });
    }

    /**
     * Forget one token, for an explicit logout.
     */
    public function delete(?string $rawToken): void
    {
        $selector = explode(':', (string)$rawToken, 2)[0];
        if ($selector === '') {
            return;
        }
        $this->update(function (array $tokens) use ($selector) {
            unset($tokens[$selector]);
            return $tokens;
        });
    }

    /**
     * The number of valid tokens, for tests and the health page.
     */
    public function count(?int $now = null): int
    {
        return count(self::withoutExpired($this->read(), $now ?? time()));
    }

    // Internals

    private static function withoutExpired(array $tokens, int $now): array
    {
        return array_filter($tokens, fn($entry) => ($entry['expires'] ?? 0) > $now);
    }

    private function read(): array
    {
        if (!is_file($this->file)) {
            return [];
        }
        $data = json_decode((string)file_get_contents($this->file), true);
        return is_array($data) ? $data : [];
    }

    /**
     * Read, modify and write the file under an exclusive lock.
     */
    private function update(callable $modify): void
    {
        $dir = dirname($this->file);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $handle = fopen($this->file, 'c+');
        if ($handle === false) {
            throw new \RuntimeException('Cannot open ' . $this->file);
        }
        try {
            flock($handle, LOCK_EX);
            $data = json_decode((string)stream_get_contents($handle), true);
            $tokens = $modify(is_array($data) ? $data : []);
            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, json_encode($tokens, JSON_PRETTY_PRINT));
            fflush($handle);
            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }
    }
}
