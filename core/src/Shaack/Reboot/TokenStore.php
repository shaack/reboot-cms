<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

namespace Shaack\Reboot;

/**
 * One-time tokens with a lifetime, bound to a subject such as a user name or
 * an e-mail address. Used for "Stay signed in" cookies and login links sent by
 * mail.
 *
 * A token is `selector:validator`. The selector finds the entry, the validator
 * is compared against its SHA-256 hash, so a copy of the file is worthless
 * without the token itself. Every token is good for one use. The file should
 * live in `local/`, outside the web root.
 */
class TokenStore
{
    private string $file;
    private int $lifetime;

    /**
     * @param string $file the JSON file holding the tokens
     * @param int $lifetime seconds a token stays valid
     */
    public function __construct(string $file, int $lifetime)
    {
        $this->file = $file;
        $this->lifetime = $lifetime;
    }

    public function getLifetime(): int
    {
        return $this->lifetime;
    }

    /**
     * Issue a token for a subject. Returns the raw token for the cookie or link.
     */
    public function create(string $subject, ?int $now = null): string
    {
        $now = $now ?? time();
        $selector = bin2hex(random_bytes(9));
        $validator = bin2hex(random_bytes(32));
        $this->update(function (array $tokens) use ($selector, $validator, $subject, $now) {
            $tokens = self::withoutExpired($tokens, $now);
            $tokens[$selector] = [
                'subject' => $subject,
                'hash' => hash('sha256', $validator),
                'expires' => $now + $this->lifetime,
            ];
            return $tokens;
        });
        return $selector . ':' . $validator;
    }

    /**
     * Redeem a token: returns the subject if the token is valid and not
     * expired, null otherwise. The token is deleted either way, a valid one is
     * meant to be replaced by a fresh one or to be used exactly once.
     */
    public function consume(?string $rawToken, ?int $now = null): ?string
    {
        $now = $now ?? time();
        $parts = explode(':', (string)$rawToken, 2);
        if (count($parts) !== 2 || !ctype_xdigit($parts[0]) || !ctype_xdigit($parts[1])) {
            return null;
        }
        [$selector, $validator] = $parts;
        $subject = null;
        $this->update(function (array $tokens) use ($selector, $validator, $now, &$subject) {
            $tokens = self::withoutExpired($tokens, $now);
            $entry = $tokens[$selector] ?? null;
            if ($entry !== null) {
                unset($tokens[$selector]);
                if (hash_equals($entry['hash'], hash('sha256', $validator))) {
                    $subject = $entry['subject'];
                }
            }
            return $tokens;
        });
        return $subject;
    }

    /**
     * Forget every token of a subject, for a password change, a deletion or a
     * disabled account.
     */
    public function deleteForSubject(string $subject): void
    {
        $this->update(function (array $tokens) use ($subject) {
            return array_filter($tokens, fn($entry) => $entry['subject'] !== $subject);
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
        if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new \RuntimeException('Cannot create ' . $dir);
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
        @chmod($this->file, 0600);
    }
}
