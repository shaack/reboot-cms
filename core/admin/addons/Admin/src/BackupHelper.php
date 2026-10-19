<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

namespace Shaack\Reboot\AddOns\Admin;

use Shaack\Logger;
use Shaack\Reboot\Reboot;

/**
 * Creates and manages backups of the content, which is `site/` with pages,
 * blocks and configuration, plus the uploaded files in `web/media/`.
 *
 * The archives are written to `local/backups/`, outside the document root, so
 * they are not reachable over the web. Downloads therefore go through the admin
 * page, which checks the login first.
 *
 * Not included are the CMS itself (`core/`, `vendor/`, `web/admin/`), which
 * comes from the release, and `local/` with the configuration, user accounts
 * and this very backup folder.
 *
 * The zip is written with PharData from the core extension `phar`, which the
 * Updater needs anyway, so the optional extension `zip` is not required. With
 * `zlib` the entries are deflated, without it they are stored as they are.
 */
class BackupHelper
{
    /** Keeps a backup from being mistaken for another file in the folder. */
    private const NAME_PATTERN = '/^Backup_[A-Za-z0-9._-]+_\d{4}-\d{2}-\d{2}_\d{4}\.zip$/';

    /** The directories that go into an archive, relative to the project root. */
    private const SOURCES = ['site', 'web/media'];

    /**
     * The backup folder, created on first use.
     */
    public static function directory(Reboot $reboot): string
    {
        $directory = $reboot->getBaseFsPath() . '/local/backups';
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException('Could not create the backup folder local/backups');
        }
        return $directory;
    }

    /**
     * The file name of a new backup, "Backup_<host>_<date>_<time>.zip".
     */
    public static function fileName(string $host): string
    {
        $host = preg_replace('/[^A-Za-z0-9.-]/', '-', $host);
        $host = trim((string)$host, '-.');
        return 'Backup_' . ($host === '' ? 'site' : $host) . '_' . date('Y-m-d_Hi') . '.zip';
    }

    /**
     * Writes a new archive and returns its file name. An archive of the same
     * minute is replaced.
     *
     * @throws \RuntimeException if there is nothing to back up or the archive cannot be written
     */
    public static function create(Reboot $reboot, string $host): string
    {
        $fileName = self::fileName($host);
        $target = self::directory($reboot) . '/' . $fileName;
        self::remove($target);

        try {
            $archive = new \PharData($target);
            // One call for all files. Adding them one by one rewrites the
            // archive on every call, which takes minutes on a few thousand files.
            $fileCount = count($archive->buildFromIterator(self::files($reboot->getBaseFsPath())));
            if ($fileCount === 0) {
                throw new \RuntimeException('There is nothing to back up, site/ and web/media/ are empty');
            }
            if (\Phar::canCompress(\Phar::GZ)) {
                $archive->compressFiles(\Phar::GZ);
            }
        } catch (\RuntimeException $e) {
            self::remove($target);
            throw $e;
        } catch (\Throwable $e) {
            self::remove($target);
            throw new \RuntimeException('Could not write the archive ' . $fileName . ': ' . $e->getMessage(), 0, $e);
        } finally {
            // PharData writes on every change, releasing the object closes the file
            unset($archive);
        }
        Logger::info("Backup written: $fileName ($fileCount files)");
        return $fileName;
    }

    /**
     * The files of all sources, the path inside the archive as key. Directory
     * entries are left out on purpose, PharData writes them broken into a
     * deflated zip, and they are implied by the file paths anyway.
     *
     * @return \Generator<string, string>
     */
    private static function files(string $basePath): \Generator
    {
        foreach (self::SOURCES as $source) {
            $sourcePath = $basePath . '/' . $source;
            if (!is_dir($sourcePath)) {
                continue;
            }
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($sourcePath, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $item) {
                /** @var \SplFileInfo $item */
                $name = $item->getFilename();
                // AppleDouble leftovers from a macOS tar are noise in an archive
                if (str_starts_with($name, '._') || $name === '.DS_Store') {
                    continue;
                }
                if (!$item->isFile() || !$item->isReadable()) {
                    continue;
                }
                yield $source . '/' . substr($item->getPathname(), strlen($sourcePath) + 1) => $item->getPathname();
            }
        }
    }

    /**
     * Removes an archive file, also from the cache that phar keeps per process.
     * A plain unlink would leave the cached instance behind, and the next
     * PharData on the same path would append to the old content.
     */
    private static function remove(string $path): void
    {
        if (!is_file($path)) {
            return;
        }
        try {
            \Phar::unlinkArchive($path);
        } catch (\Throwable) {
            @unlink($path);
        }
    }

    /**
     * All backups, newest first.
     *
     * @return array<array{name: string, size: int, modified: int}>
     */
    public static function all(Reboot $reboot): array
    {
        $directory = self::directory($reboot);
        $backups = [];
        foreach (scandir($directory) ?: [] as $entry) {
            if (!preg_match(self::NAME_PATTERN, $entry)) {
                continue;
            }
            $path = $directory . '/' . $entry;
            $backups[] = [
                'name' => $entry,
                'size' => (int)filesize($path),
                'modified' => (int)filemtime($path),
            ];
        }
        usort($backups, fn($a, $b) => $b['modified'] <=> $a['modified']);
        return $backups;
    }

    /**
     * The path of an existing backup. The name has to match the pattern, so a
     * crafted parameter cannot reach a file outside the backup folder.
     */
    public static function path(Reboot $reboot, string $name): string
    {
        if (!preg_match(self::NAME_PATTERN, $name)) {
            throw new \RuntimeException('Unknown backup');
        }
        $path = self::directory($reboot) . '/' . $name;
        if (!is_file($path)) {
            throw new \RuntimeException('Unknown backup');
        }
        return $path;
    }

    public static function delete(Reboot $reboot, string $name): string
    {
        $path = self::path($reboot, $name);
        if (!unlink($path)) {
            throw new \RuntimeException('Could not delete ' . $name);
        }
        Logger::info("Backup deleted: $name");
        return $name;
    }

    /**
     * Sends a backup to the browser and ends the request.
     */
    public static function send(Reboot $reboot, string $name): never
    {
        $path = self::path($reboot, $name);
        // The page renders inside nested output buffers (site, page, block),
        // all of them have to go or their content ends up in front of the file
        while (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: no-store');
        readfile($path);
        exit;
    }

    public static function formatFileSize(int $bytes): string
    {
        return MediaHelper::formatFileSize($bytes);
    }
}
