<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

namespace Shaack\Reboot;

use Shaack\Logger;

/**
 * Updates core/, web/admin/ and vendor/ from GitHub, within one major version.
 *
 * Every major version has its own branches, so an installation never gets the
 * next major version by accident and the older one can still get security
 * fixes. Reboot CMS 3 lives in `distrib` (stable) and `main` (unstable), later
 * versions in `distrib-<major>` and `main-<major>`. When `distrib-<major + 1>`
 * exists, the next major version is published, getNextMajor() tells the admin,
 * which links to its upgrade notes `docs/upgrade-to-<major>.md`.
 */
class Updater
{
    private string $baseFsPath;
    private string $repo;
    private string $branch;

    public function __construct(string $baseFsPath, string $repo = "shaack/reboot-cms", string $branch = "distrib")
    {
        $this->baseFsPath = $baseFsPath;
        $this->repo = $repo;
        $this->branch = $branch;
    }

    /**
     * The major version of a version like `3.21.0`, 0 if there is none.
     */
    public static function majorOf(?string $version): int
    {
        return (int)explode(".", (string)$version)[0];
    }

    /**
     * The branches an installation of `$version` updates from.
     * @return array ['stable' => branch, 'unstable' => branch]
     */
    public static function branches(?string $version): array
    {
        $major = self::majorOf($version);
        if ($major <= 3) {
            return ["stable" => "distrib", "unstable" => "main"];
        }
        return ["stable" => "distrib-" . $major, "unstable" => "main-" . $major];
    }

    /**
     * Fetches the remote version from the composer.json in the branch via the GitHub API (no CDN caching).
     */
    public function getRemoteVersion(): ?string
    {
        return $this->fetchVersion($this->branch);
    }

    /**
     * The next major version if it is published, with the link to its upgrade
     * notes. It is never installed by this updater.
     * @return ?array ['version' => "4.0.0", 'notes' => URL]
     */
    public function getNextMajor(): ?array
    {
        $major = self::majorOf($this->getLocalVersion()) + 1;
        $branch = self::branches($major . ".0.0")["stable"];
        $version = $this->fetchVersion($branch);
        if ($version === null || self::majorOf($version) !== $major) {
            return null;
        }
        return [
            "version" => $version,
            "notes" => "https://github.com/" . $this->repo . "/blob/" . $branch . "/docs/upgrade-to-" . $major . ".md",
        ];
    }

    private function fetchVersion(string $branch): ?string
    {
        $url = "https://api.github.com/repos/" . $this->repo . "/contents/composer.json?ref=" . rawurlencode($branch);
        $context = stream_context_create([
            "http" => [
                "header" => "User-Agent: reboot-cms-updater\r\nAccept: application/vnd.github.v3.raw\r\n",
            ]
        ]);
        $json = @file_get_contents($url, false, $context);
        if ($json === false) {
            return null;
        }
        $data = json_decode($json, true);
        return $data["version"] ?? null;
    }

    /**
     * Fetches the latest commit SHA for the current branch from GitHub.
     */
    public function getRemoteCommitSha(): ?string
    {
        $url = "https://api.github.com/repos/" . $this->repo . "/commits/" . $this->branch;
        $context = stream_context_create([
            "http" => [
                "header" => "User-Agent: reboot-cms-updater\r\nAccept: application/vnd.github.v3+json\r\n",
            ]
        ]);
        $json = @file_get_contents($url, false, $context);
        if ($json === false) {
            return null;
        }
        $data = json_decode($json, true);
        return $data["sha"] ?? null;
    }

    /**
     * Gets the locally stored commit SHA for a branch.
     */
    public function getLocalCommitSha(): ?string
    {
        $path = $this->baseFsPath . "/local/update-commit-" . $this->branch . ".sha";
        if (!file_exists($path)) {
            return null;
        }
        return trim(file_get_contents($path));
    }

    /**
     * Saves the commit SHA after a successful update.
     */
    private function saveLocalCommitSha(string $sha): void
    {
        $path = $this->baseFsPath . "/local/update-commit-" . $this->branch . ".sha";
        file_put_contents($path, $sha);
    }

    /**
     * Gets the local version from composer.json.
     */
    public function getLocalVersion(): ?string
    {
        $path = $this->baseFsPath . "/composer.json";
        if (!file_exists($path)) {
            return null;
        }
        $data = json_decode(file_get_contents($path), true);
        return $data["version"] ?? null;
    }

    /**
     * Downloads the tarball from GitHub, extracts core/ and web/admin/,
     * and replaces the local copies.
     */
    public function update(): void
    {
        $tarballUrl = "https://api.github.com/repos/" . $this->repo . "/tarball/" . $this->branch;
        $tmpDir = sys_get_temp_dir() . "/reboot-cms-update-" . uniqid();
        $tmpFile = $tmpDir . ".tar.gz";

        try {
            // Download tarball
            Logger::info("Updater: downloading tarball from " . $tarballUrl);
            $context = stream_context_create([
                "http" => [
                    "header" => "User-Agent: reboot-cms-updater\r\n",
                    "follow_location" => true,
                ]
            ]);
            $data = file_get_contents($tarballUrl, false, $context);
            if ($data === false) {
                throw new \RuntimeException("Failed to download update from GitHub");
            }
            file_put_contents($tmpFile, $data);

            // Extract tarball
            $phar = new \PharData($tmpFile);
            mkdir($tmpDir, 0755, true);
            $phar->extractTo($tmpDir);

            // GitHub tarballs contain a top-level directory like "shaack-reboot-cms-abc1234"
            $extractedDirs = glob($tmpDir . "/*", GLOB_ONLYDIR);
            if (empty($extractedDirs)) {
                throw new \RuntimeException("Failed to extract update archive");
            }
            $sourceDir = $extractedDirs[0];

            // never a major update, that needs the upgrade notes and a deliberate step
            $sourceData = json_decode((string)@file_get_contents($sourceDir . "/composer.json"), true);
            $sourceVersion = is_array($sourceData) ? ($sourceData["version"] ?? null) : null;
            $localVersion = $this->getLocalVersion();
            if ($localVersion !== null && self::majorOf($sourceVersion) !== self::majorOf($localVersion)) {
                throw new \RuntimeException("The branch " . $this->branch . " holds version " . $sourceVersion
                    . ", not " . self::majorOf($localVersion) . ".x. A major update is not installed by the updater.");
            }

            // Extract commit SHA from the directory name (e.g. "shaack-reboot-cms-abc1234")
            $dirName = basename($sourceDir);
            if (preg_match('/-([0-9a-f]+)$/', $dirName, $matches)) {
                $this->saveLocalCommitSha($matches[1]);
            }

            // Replace core/
            $sourceCore = $sourceDir . "/core";
            $targetCore = $this->baseFsPath . "/core";
            if (is_dir($sourceCore)) {
                Logger::info("Updater: replacing core/");
                self::deleteDirectory($targetCore);
                self::copyDirectory($sourceCore, $targetCore);
            }

            // Replace web/admin/
            $sourceAdmin = $sourceDir . "/web/admin";
            $targetAdmin = $this->baseFsPath . "/web/admin";
            if (is_dir($sourceAdmin)) {
                Logger::info("Updater: replacing web/admin/");
                self::deleteDirectory($targetAdmin);
                self::copyDirectory($sourceAdmin, $targetAdmin);
            }

            // Replace vendor/
            $sourceVendor = $sourceDir . "/vendor";
            $targetVendor = $this->baseFsPath . "/vendor";
            if (is_dir($sourceVendor)) {
                Logger::info("Updater: replacing vendor/");
                self::deleteDirectory($targetVendor);
                self::copyDirectory($sourceVendor, $targetVendor);
            }

            // Replace root files
            foreach (["README.md", "AGENTS.md", "LICENSE", "run.sh", "test.sh"] as $rootFile) {
                $sourceFile = $sourceDir . "/" . $rootFile;
                $targetFile = $this->baseFsPath . "/" . $rootFile;
                if (file_exists($sourceFile)) {
                    Logger::info("Updater: replacing " . $rootFile);
                    copy($sourceFile, $targetFile);
                    if (str_ends_with($rootFile, ".sh")) {
                        chmod($targetFile, 0755);
                    }
                }
            }

            // Update composer.json version
            $sourceComposer = $sourceDir . "/composer.json";
            if (file_exists($sourceComposer)) {
                $remoteData = json_decode(file_get_contents($sourceComposer), true);
                $localPath = $this->baseFsPath . "/composer.json";
                $localData = json_decode(file_get_contents($localPath), true);
                if (isset($remoteData["version"])) {
                    $localData["version"] = $remoteData["version"];
                }
                if (isset($remoteData["require"])) {
                    $localData["require"] = $remoteData["require"];
                }
                file_put_contents($localPath, json_encode($localData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
            }

            Logger::info("Updater: update complete");
        } finally {
            // Clean up temp files
            if (file_exists($tmpFile)) {
                unlink($tmpFile);
            }
            if (is_dir($tmpDir)) {
                self::deleteDirectory($tmpDir);
            }
        }
    }

    private static function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }
        rmdir($dir);
    }

    private static function copyDirectory(string $source, string $dest): void
    {
        mkdir($dest, 0755, true);
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($items as $item) {
            $targetPath = $dest . "/" . $items->getSubPathname();
            if ($item->isDir()) {
                if (!is_dir($targetPath)) {
                    mkdir($targetPath, 0755, true);
                }
            } else {
                copy($item->getPathname(), $targetPath);
            }
        }
    }
}
