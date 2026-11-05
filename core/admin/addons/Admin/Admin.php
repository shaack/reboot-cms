<?php

namespace Shaack\Reboot\AddOns;

use Shaack\Logger;
use Shaack\Reboot\AddOn;
use Shaack\Reboot\Reboot;
use Shaack\Reboot\Request;
use Shaack\Reboot\Site;
use Shaack\Reboot\Updater;
use Shaack\Reboot\AddOns\Admin\AdminMenu;

class Admin extends AddOn
{
    private ?Site $defaultSite = null;

    /**
     * The site this admin manages, including its AddOns. Created once per
     * request, so the site's AddOns are initialized only once.
     */
    public function getDefaultSite(): Site
    {
        if ($this->defaultSite === null) {
            $this->defaultSite = new Site($this->reboot, "/site", "");
        }
        return $this->defaultSite;
    }

    /**
     * The admin pages the site's AddOns bring along, see `AddOn::getAdminPages()`,
     * plus an info page per AddOn at `/addons/<name>`, see `AdminMenu::infoPath()`.
     * They are mounted into the admin site; a page in `core/admin/pages/` of the
     * same path wins, and so does the AddOn listed first in `site/config.yml`.
     */
    public function getPages(): array
    {
        $pages = [];
        foreach ($this->getDefaultSite()->getAddOns() as $addOn) {
            foreach ($addOn->getAdminPages() as $path => $file) {
                if (!is_string($path) || !str_starts_with($path, '/') || !is_string($file) || !is_file($file)) {
                    Logger::error("Admin: ignoring invalid admin page '$path' of addon " . $addOn->getName());
                    continue;
                }
                $pages[$path] = $pages[$path] ?? $file;
            }
            // the info page, linked in the menu for AddOns without an own page
            $pages[AdminMenu::infoPath($addOn)] = $pages[AdminMenu::infoPath($addOn)] ?? __DIR__ . '/pages/addon-info.php';
        }
        return $pages;
    }

    /**
     * Until 3.18 the admin pages of an AddOn were mounted at `/<name>`, now at
     * `/addons/<name>`. Old bookmarks and links are redirected, unless a
     * built-in admin page has that path.
     */
    public function preRender(Request $request): bool
    {
        $path = $request->getPath();
        foreach ($this->getDefaultSite()->getAddOns() as $addOn) {
            $oldPath = '/' . strtolower($addOn->getName());
            if ($addOn->getAdminPath() === $oldPath || is_file($this->site->getFsPath() . '/pages' . $oldPath . '.php')) {
                continue;
            }
            if ($path === $oldPath || str_starts_with($path, $oldPath . '/')) {
                $query = $request->getParamsGet() ? '?' . http_build_query($request->getParamsGet()) : '';
                $this->reboot->redirect($this->site->getWebPath() . $addOn->getAdminPath() . substr($path, strlen($oldPath)) . $query);
                return false;
            }
        }
        return true;
    }

    /**
     * The admin navigation: the static structure from `core/admin/config.yml`
     * plus the entries contributed by the site's AddOns, see `AddOn::getAdminMenu()`.
     * An AddOn that is not listed in `site/config.yml` contributes nothing.
     */
    public function getNavbarStructure(): array
    {
        $structure = $this->site->getConfig()['navbar']['structure'] ?? [];
        return AdminMenu::build($structure, $this->getDefaultSite()->getAddOns());
    }

    public function getLocalConfig(): array
    {
        return $this->reboot->getConfig();
    }

    /**
     * The installed Reboot CMS version, read from `composer.json`. No network
     * access, the comparison with the published version happens in the browser.
     */
    public function getLocalVersion(): ?string
    {
        return (new Updater($this->reboot->getBaseFsPath()))->getLocalVersion();
    }
}