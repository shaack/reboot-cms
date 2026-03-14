# Capturing Screenshots

The screenshots in `docs/img/` are generated automatically using a Puppeteer script. This ensures
consistent, clean viewport captures without browser chrome.

## Prerequisites

- **Node.js** installed
- **Puppeteer** installed globally: `npm install -g puppeteer`
- **Reboot CMS running** at `http://localhost` (e.g. via Docker Compose)
- An admin account with username `admin` and a known password

## Configuration

Edit `docs/capture-screenshots.mjs` to adjust:

- `BASE` — the base URL of the running CMS (default: `http://localhost`)
- `WIDTH` / `HEIGHT` — viewport dimensions (default: 1280x900)
- Admin credentials in the login section (lines 34-35)

## Running the script

```sh
NODE_PATH=$(npm root -g) node docs/capture-screenshots.mjs
```

The `NODE_PATH` is needed so Node.js can find the globally installed Puppeteer package.

## What it captures

The script logs in to the admin interface and takes screenshots of each section:

| Screenshot | Page |
|---|---|
| `reboot-cms-index.png` | Front page (no auth) |
| `reboot-cms-admin-login.png` | Admin login screen |
| `reboot-cms-admin-page-edit.png` | Page editor (index.md) |
| `reboot-cms-admin-site-configuration.png` | Site configuration editor |
| `reboot-cms-admin-media.png` | Media file manager |
| `reboot-cms-admin-users.png` | User management |
| `reboot-cms-admin-update.png` | Update page |

All images are saved to `docs/img/` and referenced from `README.md`.
