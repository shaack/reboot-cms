<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

namespace Shaack\Reboot\AddOns\ProtectedAreas;

/**
 * Slows down password guessing: after MAX_ATTEMPTS failed logins from one IP
 * address within WINDOW seconds, further attempts from that address are
 * refused until the window has passed. Attempts are kept in
 * `local/protected-areas/login-attempts.json`.
 */
class LoginThrottle
{
    public const MAX_ATTEMPTS = 5;
    public const WINDOW = 900; // 15 minutes

    private string $file;

    /**
     * @param string $fileName the counter file in `local/protected-areas/`, one per kind of attempt
     */
    public function __construct(string $baseFsPath, string $fileName = 'login-attempts.json')
    {
        $this->file = rtrim($baseFsPath, '/') . '/local/protected-areas/' . $fileName;
    }

    public function isBlocked(string $ip, ?int $now = null): bool
    {
        $now = $now ?? time();
        $count = 0;
        foreach ($this->read() as $attempt) {
            if (($attempt['ip'] ?? '') === $ip && ($attempt['time'] ?? 0) > $now - self::WINDOW) {
                $count++;
            }
        }
        return $count >= self::MAX_ATTEMPTS;
    }

    public function registerFailure(string $ip, ?int $now = null): void
    {
        $now = $now ?? time();
        $attempts = $this->prune($this->read(), $now);
        $attempts[] = ['ip' => $ip, 'time' => $now];
        $this->write($attempts);
    }

    public function clear(string $ip, ?int $now = null): void
    {
        $now = $now ?? time();
        $attempts = array_values(array_filter($this->prune($this->read(), $now), function ($attempt) use ($ip) {
            return ($attempt['ip'] ?? '') !== $ip;
        }));
        $this->write($attempts);
    }

    private function prune(array $attempts, int $now): array
    {
        return array_values(array_filter($attempts, function ($attempt) use ($now) {
            return ($attempt['time'] ?? 0) > $now - self::WINDOW;
        }));
    }

    private function read(): array
    {
        if (!is_file($this->file)) {
            return [];
        }
        $decoded = json_decode((string)file_get_contents($this->file), true);
        return is_array($decoded) ? $decoded : [];
    }

    private function write(array $attempts): void
    {
        $dir = dirname($this->file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }
        file_put_contents($this->file, json_encode($attempts), LOCK_EX);
        @chmod($this->file, 0600);
    }
}
