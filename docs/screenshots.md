# Capturing Screenshots

The screenshots in `web/media/screenshots/` are taken automatically with Puppeteer, from a throwaway installation filled with sample content. The README and the demo site use them.

## Prerequisites

- **Podman**, for the throwaway Apache and PHP container
- **Node.js** with **Puppeteer** installed globally: `npm install -g puppeteer`

## Running

```sh
./docs/capture-screenshots.sh
```

The script starts a container on port 26007 that works on copies of `local/`, `site/` and `web/media/`, so your own accounts and settings are never touched. It fills the copies with sample content, takes the screenshots twice and removes the container. The second run is needed because the demo shows the screenshots itself, in the gallery and in the enlarged view, and these should show the new ones.

The admin account of the run gets a random password that exists only for this run. The pages are addressed as `www.my-site.test`, mapped to the container, so links and file names in the images show a neutral domain.

Settings, all optional:

- `PORT` — the port of the container, default `26007`
- `SITE_HOST` — the domain shown in the images, default `www.my-site.test`

## The parts

- `docs/capture-screenshots.sh` — sets up the container, runs the seed and the capture, cleans up
- `docs/screenshot-seed.php` — the sample content: two editors, a page history spread over two weeks, blog images, customer accounts, secure downloads and backups
- `docs/capture-screenshots.mjs` — the Puppeteer script, dark mode, 1280×800 at device scale factor 2. Pages with little content are cut below it.

The capture script also runs against any other installation, given its address and an admin account:

```sh
BASE=http://localhost:21007 ADMIN_USER=admin ADMIN_PASSWORD=... \
  NODE_PATH=$(npm root -g) node docs/capture-screenshots.mjs
```

## What it captures

| Screenshot | Page |
|---|---|
| `reboot-cms-index.png` | Front page of the demo |
| `reboot-cms-blog-article.png` | A blog article with its box of links |
| `reboot-cms-lightbox.png` | The gallery with an enlarged image |
| `reboot-cms-admin-login.png` | Admin login |
| `reboot-cms-admin-page-edit.png` | Page editor with preview |
| `reboot-cms-admin-insert-block.png` | Page editor with the block menu open |
| `reboot-cms-admin-page-history.png` | Page history |
| `reboot-cms-admin-media.png` | Media manager, a folder of images |
| `reboot-cms-admin-site-configuration.png` | Site configuration |
| `reboot-cms-admin-users.png` | User management |
| `reboot-cms-admin-protected-areas.png` | Accounts of the protected areas |
| `reboot-cms-admin-secure-downloads.png` | Secure downloads |
| `reboot-cms-admin-backup.png` | Backups |
| `reboot-cms-admin-health.png` | System health |
| `reboot-cms-admin-update.png` | Update |

A shot of an admin section that is not available, for example of an AddOn that is not registered, is skipped with a notice.
