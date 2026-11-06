<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

namespace Shaack\Reboot;

use Shaack\Logger;
use Shaack\Utils\FileSystemUtils;

/**
 * The host names the site is really reached under, for links in mails.
 *
 * The Host header of a request is chosen by the client. A link built from it
 * and mailed to someone else, like a login link, could point to any domain.
 * So the hosts are learned from the admin: every request of a logged in admin
 * user records its host in `local/trusted-hosts.json`, the browser of an admin
 * sends the real one. A visitor cannot add a host.
 */
class TrustedHosts
{
    private string $file;

    public function __construct(string $baseFsPath)
    {
        $this->file = rtrim($baseFsPath, '/') . '/local/trusted-hosts.json';
    }

    /**
     * The host of the current request, lower case and with the port, or null
     * if the header is missing or no valid host name.
     */
    public static function requestHost(): ?string
    {
        $host = strtolower(trim((string)($_SERVER['HTTP_HOST'] ?? '')));
        return preg_match('/^([a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)*|\[[0-9a-f:.]+\])(:\d{1,5})?$/', $host)
            ? $host : null;
    }

    public function isTrusted(?string $host): bool
    {
        if ($host === null || !is_file($this->file)) {
            return false;
        }
        $hosts = json_decode((string)@file_get_contents($this->file), true);
        return is_array($hosts) && in_array($host, $hosts, true);
    }

    /**
     * Records the host of the current request. Call it only for requests of a
     * logged in admin user.
     */
    public function rememberRequestHost(): void
    {
        $host = self::requestHost();
        if ($host === null || $this->isTrusted($host)) {
            return;
        }
        try {
            FileSystemUtils::updateJson($this->file, function (array &$hosts) use ($host) {
                if (in_array($host, $hosts, true)) {
                    return false;
                }
                $hosts[] = $host;
                return true;
            });
        } catch (\RuntimeException $e) {
            Logger::error("TrustedHosts: " . $e->getMessage());
        }
    }
}
