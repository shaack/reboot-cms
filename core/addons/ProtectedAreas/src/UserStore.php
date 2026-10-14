<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

namespace Shaack\Reboot\AddOns\ProtectedAreas;

/**
 * The accounts of the protected areas, stored in `local/protected-areas/users.json`.
 * An account is a user name, a password hash and the list of paths the user
 * may enter. Everything the file holds:
 *
 *   passwordHash  password_hash() of the password
 *   paths         the paths the user may enter, e.g. ["/customers/acme"]
 *   createdAt     unix timestamp
 *   lastLoginAt   unix timestamp of the last successful login, or null
 *
 * Writes take an exclusive lock on the file, so two admin requests cannot
 * lose each other's changes.
 */
class UserStore
{
    public const MIN_PASSWORD_LENGTH = 8;

    private string $dir;
    private string $file;

    public function __construct(string $baseFsPath)
    {
        $this->dir = rtrim($baseFsPath, '/') . '/local/protected-areas';
        $this->file = $this->dir . '/users.json';
    }

    /**
     * All accounts keyed by user name, sorted by name, without the password hashes.
     */
    public function getUsers(): array
    {
        $users = $this->read();
        ksort($users, SORT_NATURAL | SORT_FLAG_CASE);
        foreach ($users as &$user) {
            unset($user['passwordHash']);
        }
        return $users;
    }

    public function hasUser(string $username): bool
    {
        return isset($this->read()[$username]);
    }

    /**
     * The paths a user may enter, empty for unknown users.
     * @return string[]
     */
    public function getPaths(string $username): array
    {
        return $this->read()[$username]['paths'] ?? [];
    }

    public function addUser(string $username, string $password, array $paths): void
    {
        self::assertValidUsername($username);
        self::assertValidPassword($password);
        $this->withUsers(function (array &$users) use ($username, $password, $paths) {
            if (isset($users[$username])) {
                throw new \InvalidArgumentException("User '$username' already exists");
            }
            $users[$username] = [
                'passwordHash' => password_hash($password, PASSWORD_DEFAULT),
                'paths' => AccessControl::normalizePaths($paths),
                'createdAt' => time(),
                'lastLoginAt' => null,
            ];
            return true;
        });
    }

    public function setPassword(string $username, string $password): void
    {
        self::assertValidPassword($password);
        $this->withUsers(function (array &$users) use ($username, $password) {
            if (!isset($users[$username])) {
                throw new \InvalidArgumentException("User '$username' does not exist");
            }
            $users[$username]['passwordHash'] = password_hash($password, PASSWORD_DEFAULT);
            return true;
        });
    }

    public function setPaths(string $username, array $paths): void
    {
        $this->withUsers(function (array &$users) use ($username, $paths) {
            if (!isset($users[$username])) {
                throw new \InvalidArgumentException("User '$username' does not exist");
            }
            $users[$username]['paths'] = AccessControl::normalizePaths($paths);
            return true;
        });
    }

    public function deleteUser(string $username): void
    {
        $this->withUsers(function (array &$users) use ($username) {
            if (!isset($users[$username])) {
                throw new \InvalidArgumentException("User '$username' does not exist");
            }
            unset($users[$username]);
            return true;
        });
    }

    /**
     * True if the user exists and the password matches. Takes about the same
     * time for unknown users, so the response does not reveal which names exist.
     */
    public function verify(string $username, string $password): bool
    {
        $users = $this->read();
        $hash = $users[$username]['passwordHash'] ?? null;
        if ($hash === null) {
            // Burn the same time as a real comparison would
            password_verify($password, '$2y$10$usesomesillystringforsalt$');
            return false;
        }
        return password_verify($password, $hash);
    }

    public function registerLogin(string $username): void
    {
        $this->withUsers(function (array &$users) use ($username) {
            if (!isset($users[$username])) {
                return false;
            }
            $users[$username]['lastLoginAt'] = time();
            return true;
        });
    }

    public static function isValidUsername(string $username): bool
    {
        return preg_match('/^[a-zA-Z0-9][a-zA-Z0-9._@-]{0,63}$/', $username) === 1;
    }

    public static function assertValidUsername(string $username): void
    {
        if (!self::isValidUsername($username)) {
            throw new \InvalidArgumentException(
                'User name may contain letters, digits, dots, underscores, hyphens and @, up to 64 characters');
        }
    }

    public static function assertValidPassword(string $password): void
    {
        if (strlen($password) < self::MIN_PASSWORD_LENGTH) {
            throw new \InvalidArgumentException('Password must be at least ' . self::MIN_PASSWORD_LENGTH . ' characters');
        }
    }

    // Internals

    private function read(): array
    {
        if (!is_file($this->file)) {
            return [];
        }
        $decoded = json_decode((string)file_get_contents($this->file), true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Runs `$fn(array &$users)` under an exclusive lock and writes the file
     * back if the callback returns true.
     */
    private function withUsers(callable $fn): void
    {
        if (!is_dir($this->dir) && !@mkdir($this->dir, 0700, true) && !is_dir($this->dir)) {
            throw new \RuntimeException('Could not create ' . $this->dir);
        }
        $handle = fopen($this->file, 'c+');
        if ($handle === false) {
            throw new \RuntimeException('Could not open ' . $this->file);
        }
        try {
            if (!flock($handle, LOCK_EX)) {
                throw new \RuntimeException('Could not lock ' . $this->file);
            }
            $raw = stream_get_contents($handle);
            $users = [];
            if ($raw !== false && trim($raw) !== '') {
                $decoded = json_decode($raw, true);
                $users = is_array($decoded) ? $decoded : [];
            }
            if ($fn($users)) {
                $json = json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                ftruncate($handle, 0);
                rewind($handle);
                fwrite($handle, $json . "\n");
                fflush($handle);
            }
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
        @chmod($this->file, 0600);
    }
}
