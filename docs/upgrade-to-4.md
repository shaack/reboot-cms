# Upgrading to Reboot CMS 4

Reboot CMS 4 makes pages with forms the default. A page is a list of blocks, and every block is a small form whose fields a YAML blueprint names. Editors fill in fields, the templates own the layout. Records like seminars or blog posts live apart from the pages as data. Markdown pages keep working, nothing of an existing site has to change.

This is a major update. The updater in the admin of Reboot CMS 3 does not install it, it only points to this page. Reboot CMS 3 stays available and gets security fixes for the time being, so there is no hurry.

## What is new

- **Structured pages.** A page can be a YAML file, `site/pages/about.yml` for `/about`, with a title, a description and a list of blocks. The admin edits it as a form, a panel per block, with a live preview that follows the field being edited. New pages in the admin are structured pages.
- **Form blocks.** A block type for structured pages is a folder `site/blocks/<name>/` with the template `<name>.php` and the blueprint `<name>.yml`. The template reads the fields with `$block->get()`, `html()`, `items()` and `entry()`. Field types are text, textarea, markdown, number, date, boolean, select, image, url, list, entry and blocks.
- **Data.** Records with fixed fields live in `site/data/<collection>/`, one Markdown file per entry, described by a blueprint in `site/blueprints/<collection>.yml`. The admin has a new section "Data" to edit them. Templates and blocks query them with `$site->collection("seminars")->where("date", ">=", "today")->limit(3)`.
- **Entries as pages.** With `url: blog` in its blueprint, every entry of a collection is a page at `/blog/<slug>`, rendered by the template `site/blueprints/blog.php`. The blog of the demo works like that.
- **Converting Markdown pages.** *… › Convert to structured page* turns a Markdown page into a structured page at the same address. A Markdown block becomes a form block if a block type says in its blueprint under `convert` how to read it, otherwise it stays a Markdown block inside the structured page and looks as before. The Markdown file is kept in the history.
- **Markdown blocks in structured pages.** A block with `markdown: "..."` instead of fields renders with the Markdown template of its type, so every existing block type can be used in structured pages right away.

The README describes all of it in the sections "Data", "Entries as pages" and "Structured pages".

## What stays the same

- Markdown pages, their frontmatter and their blocks work as before. At one address a Markdown page still wins over a structured page.
- PHP pages, `site/template.php`, the AddOns and their hooks are unchanged.
- The update replaces `core/`, `web/admin/` and `vendor/` as always. Your `site/`, `local/` and `web/` content stay untouched.

## What changes

- **New pages are structured pages.** In the page list, *New page* creates a YAML file. *New Markdown page* creates a Markdown file as before. A new folder starts with `index.yml` instead of `index.md`. If you only want Markdown pages, use *New Markdown page*.
- **Structured pages need form blocks.** The update does not touch `site/`, so an existing site has no form blocks yet. Until you add some, *Add block* offers the Markdown blocks of the site under *Markdown blocks*. The demo of Reboot CMS 4 ships form blocks to start from, `intro`, `content`, `image-text`, `features`, `gallery`, `seminar-teaser`, `seminar-spotlight` and `blog-list`, each a folder in `site/blocks/`. Copy the ones you like and adapt them to your design.
- **New reserved folders.** `site/blueprints/` and `site/data/` have a meaning now. `site/blueprints/page.yml` describes the fields of structured pages, the other blueprints describe collections. A folder `site/blocks/<name>/` with `<name>.php` is a block type and wins over a file `site/blocks/<name>.php` of the same name.
- **Routing.** If no file in `site/pages/` matches a request, the entries of collections with `url` are tried before the pages of AddOns and the 404 page. Without such a blueprint nothing changes.
- **Page actions.** For scripts that post to the admin: the action `add_page` creates a structured page, `add_markdown_page` a Markdown page. `add_structured_page` is gone.
- **Update branches.** Every major version has its own branches. Reboot CMS 4 updates from `distrib-4` (stable) and `main-4` (unstable). Reboot CMS 3 stays on `distrib` and `main`.

## How to upgrade

1. Make a backup, in the admin under *Backup* or as a copy of the project folder.
2. Download Reboot CMS 4 from the branch `distrib-4`: [reboot-cms-distrib-4.zip](https://github.com/shaack/reboot-cms/archive/refs/heads/distrib-4.zip).
3. Replace `core/`, `web/admin/` and `vendor/` of your installation with the ones from the download. Replace `composer.json` as well, it holds the version, and the files `README.md`, `AGENTS.md`, `LICENSE`, `run.sh` and `test.sh` if you use them.
4. Keep `site/`, `local/`, `web/index.php` and the rest of `web/` as they are.
5. Open the admin. The page *Update* shows version 4 and from now on checks `distrib-4`.
6. Optional: copy the form blocks you want from `site/blocks/` of the download into your site, and convert Markdown pages with *… › Convert to structured page*.

If you run your site from a clone of the repository, check out `distrib-4` instead, or merge it into your branch.

## Staying on Reboot CMS 3

Nothing forces the upgrade. The updater of Reboot CMS 3 keeps installing 3.x versions from `distrib`, and security fixes for Reboot CMS 3 are published there.
