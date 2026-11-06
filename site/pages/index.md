---
title: Reboot CMS, the flat file CMS with blocks
description: Reboot CMS is a small and fast flat file CMS in PHP. Pages are Markdown, blocks give them structure, no database needed.
author: shaack.com
---

<!-- hero -->

# Reboot CMS

The flat file CMS that thinks in blocks.

---

Your pages are **Markdown files**. Blocks give them structure, a small PHP core delivers them fast, and an admin interface lets you edit everything in the browser. No database, no build step, nothing to migrate.

---

[View on GitHub](https://github.com/shaack/reboot-cms)

<!-- three-columns -->

### Just files

A page is a Markdown file, and its path is its URL. Put the site under Git and every change becomes a reviewable diff. Backups are a copy of a folder.

---

### Structured with blocks

Split a page into sections with a one line comment. Each block type is a single PHP template, so a hero, a gallery or a pricing table is a few lines of HTML away.

---

### Fast by default

No database, no framework, no queries. Pages render in milliseconds and ship without the weight of a classic CMS. Sites built with Reboot CMS score 100 on PageSpeed Insights.

<!-- text-image -->

## Edit in the browser

The admin interface edits pages in a Markdown editor with a block menu and a live preview, keeps a history of every page, and manages media, users, protected areas and secure downloads. Updates come straight from GitHub with one click, and a backup is one more.

[See the admin in the documentation](documentation#admin-interface)

---

![The page editor of the admin](media/screenshots/reboot-cms-admin-page-edit.png "Editing a page")

<!--
text-image:
    image-position: left
-->

## Made for AI agents

An agent does well with Reboot CMS for the same reason you do. Content is text, the URL names the file, and the whole core fits into a context window. The repository ships an `AGENTS.md` that orients coding assistants, so "add a page about pricing" is a task an agent completes on its own.

[Read how it works](documentation#working-with-ai-agents)

---

![The media manager of the admin](media/screenshots/reboot-cms-admin-media.png "The media manager")

<!-- cards -->

## The admin at a glance

- ![Pages: a Markdown editor with block menu and live preview](media/screenshots/reboot-cms-admin-insert-block.png)
- ![History: every save is kept and can be restored](media/screenshots/reboot-cms-admin-page-history.png)
- ![Protected areas: customer logins by password or mail link](media/screenshots/reboot-cms-admin-protected-areas.png)
- ![Secure downloads: send a file with a link and a password](media/screenshots/reboot-cms-admin-secure-downloads.png)

<!-- text -->

## Up and running in a minute

Clone the repository and start it. The included script runs Apache and PHP in a container, the same setup as on a typical web host.

```bash
git clone https://github.com/shaack/reboot-cms.git my-site
cd my-site
./run.sh
```

The site is at `http://localhost:21007`, the admin at `http://localhost:21007/admin`. On a server, point the document root at `web/` and open `/admin` to create the first account.

Reboot CMS is open source under the MIT license. Have a look at the [blocks of this demo](blocks), read the [blog](blog) and the [documentation](documentation), or go straight to the [repository on GitHub](https://github.com/shaack/reboot-cms).
