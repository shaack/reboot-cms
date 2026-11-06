<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

namespace Shaack\Reboot;

use Shaack\Utils\FileSystemUtils;

/**
 * Slows down password guessing: after MAX_ATTEMPTS attempts from one IP
 * address within WINDOW seconds, further attempts from that address are
 * refused until the window has passed. A successful login clears the count.
 * Used by the admin login and by ProtectedAreas.
 */
class LoginThrottle
{
    public const MAX_ATTEMPTS = 5;
    public const WINDOW = 900; // 15 minutes

    private string $file;

    /**
     * @param string $file the JSON file with the attempts, in `local/`
     */
    public function __construct(string $file)
    {
        $this->file = $file;
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

    /**
     * Counts an attempt and says whether it may go ahead, in one step under a
     * lock. Checking with isBlocked() and counting afterwards would let
     * parallel requests through, they would all see the same count.
     * A successful login calls clear().
     */
    public function tryAttempt(string $ip, ?int $now = null): bool
    {
        $now = $now ?? time();
        $allowed = false;
        $this->update(function (array $attempts) use ($ip, $now, &$allowed) {
            $attempts = $this->prune($attempts, $now);
            $count = count(array_filter($attempts, fn($attempt) => ($attempt['ip'] ?? '') === $ip));
            if ($count >= self::MAX_ATTEMPTS) {
                return null;
            }
            $allowed = true;
            $attempts[] = ['ip' => $ip, 'time' => $now];
            return $attempts;
        });
        return $allowed;
    }

    public function registerFailure(string $ip, ?int $now = null): void
    {
        $now = $now ?? time();
        $this->update(function (array $attempts) use ($ip, $now) {
            $attempts = $this->prune($attempts, $now);
            $attempts[] = ['ip' => $ip, 'time' => $now];
            return $attempts;
        });
    }

    public function clear(string $ip, ?int $now = null): void
    {
        $now = $now ?? time();
        $this->update(function (array $attempts) use ($ip, $now) {
            return array_values(array_filter($this->prune($attempts, $now), function ($attempt) use ($ip) {
                return ($attempt['ip'] ?? '') !== $ip;
            }));
        });
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

    /**
     * Read, change and write the attempts under a lock. `$modify` returns the
     * new list, or null to leave the file as it is. A damaged file counts as
     * empty, the attempts are only a brake.
     */
    private function update(callable $modify): void
    {
        $dir = dirname($this->file);
        if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new \RuntimeException('Could not create ' . $dir);
        }
        FileSystemUtils::withLock($this->file, function () use ($modify) {
            $attempts = $modify($this->read());
            if ($attempts !== null) {
                FileSystemUtils::writeAtomic($this->file, json_encode($attempts), 0600);
            }
        });
    }
}
