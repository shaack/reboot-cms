<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

namespace Shaack\Reboot;

/**
 * Base class of all AddOns. Overwrite init(), preRender() and postRender() to
 * hook into the request, and getAdminMenu(), getAdminPages() or getPages() to
 * add pages. See AddOnLoader for the folder layout of an AddOn.
 */
class AddOn
{
    protected Reboot $reboot;
    protected Site $site;
    protected ?string $dir;

    /**
     * @param ?string $dir the AddOn folder, null for the deprecated single file format
     */
    public function __construct(Reboot $reboot, Site $site, ?string $dir = null)
    {
        $this->reboot = $reboot;
        $this->site = $site;
        $this->dir = $dir;
        $this->init();
    }

    /**
     * Called after construction of the AddOn
     * @return void
     */
    protected function init()
    {

    }

    /**
     * Called on every request before rendering the page.
     * Return true, if you want to render the page or false if you do a redirect or deny access.
     */
    public function preRender(Request $request): bool
    {
        return true;
    }

    /**
     * Called after the page is rendered before displaying it. Use it to modify content after rendering.
     * Returns the modified content of the page.
     */
    public function postRender(Request $request, string $content): string
    {
        return $content;
    }

    /**
     * The name of the AddOn, which is its class name and the name of its folder.
     */
    public function getName(): string
    {
        return (new \ReflectionClass($this))->getShortName();
    }

    /**
     * The folder of the AddOn, null for the deprecated single file format.
     */
    public function getDir(): ?string
    {
        return $this->dir;
    }

    /**
     * A notice if this AddOn is deprecated, for example an alias kept for an
     * old name. Shown on the admin's health page. Null for a current AddOn.
     */
    public function getDeprecation(): ?string
    {
        return null;
    }

    /**
     * Pages this AddOn adds to its own site, as `path => PHP file`. A page is
     * rendered like a PHP page in `pages/`, with `$reboot`, `$site`, `$page`
     * and `$request` available. A file in `pages/` of the same path wins.
     */
    public function getPages(): array
    {
        return [];
    }

    /**
     * The admin path of this AddOn, the folder `admin/` is mounted here. By
     * default the lower case name, `/securedownloads` for the AddOn `SecureDownloads`.
     */
    public function getAdminPath(): string
    {
        return '/' . strtolower($this->getName());
    }

    /**
     * Admin pages this AddOn brings along, as `path => PHP file`. By default
     * every PHP file in the folder `admin/`: `admin/index.php` is mounted at
     * the admin path, `admin/log.php` at `<admin path>/log`, `admin/api/list.php`
     * at `<admin path>/api/list`. The pages appear in the admin only while the
     * AddOn is listed under `addons` in the site's `config.yml`.
     */
    public function getAdminPages(): array
    {
        if ($this->dir === null || !is_dir($this->dir . '/admin')) {
            return [];
        }
        $adminDir = $this->dir . '/admin';
        $base = $this->getAdminPath();
        $pages = [];
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($adminDir, \FilesystemIterator::SKIP_DOTS)
        );
        /** @var \SplFileInfo $file */
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $relative = substr($file->getPathname(), strlen($adminDir) + 1, -4);
            $relative = str_replace(DIRECTORY_SEPARATOR, '/', $relative);
            if (!preg_match('#^[a-zA-Z0-9_-]+(/[a-zA-Z0-9_-]+)*$#', $relative)) {
                continue;
            }
            if ($relative === 'index') {
                $relative = '';
            } elseif (str_ends_with($relative, '/index')) {
                $relative = substr($relative, 0, -6);
            }
            $pages[$relative === '' ? $base : $base . '/' . $relative] = $file->getPathname();
        }
        ksort($pages);
        return $pages;
    }

    /**
     * Entries this AddOn contributes to the admin navigation, as `label => path`.
     * By default one entry named after the AddOn, if it has an `admin/index.php`.
     *
     * A nested array adds entries to the dropdown of that name,
     * `['Admin' => ['Statistics' => '/statistics']]` extends the "Admin" menu.
     * Return an empty array to keep an admin page out of the menu. Like the
     * pages, the entries are shown only while the AddOn is listed under `addons`
     * in the site's `config.yml`, and to admins only.
     */
    public function getAdminMenu(): array
    {
        $pages = $this->getAdminPages();
        return isset($pages[$this->getAdminPath()]) ? [$this->getName() => $this->getAdminPath()] : [];
    }
}
