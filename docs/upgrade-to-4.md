# Upgrading to Reboot CMS 4

Reboot CMS 4 makes pages with forms the default. A page is a list of blocks, and every block is a small form whose fields a YAML blueprint names. Editors fill in fields, the templates own the layout. Records like seminars or blog posts live apart from the pages as data. Markdown pages keep working, nothing of an existing site has to change.

This is a major update. A normal update in the admin of Reboot CMS 3 does not install it. From Reboot CMS 3.23 on, the page *Update* offers it with a button of its own, see "How to upgrade". Reboot CMS 3 stays available and gets security fixes for the time being, so there is no hurry.

## What is new

- **Structured pages.** A page can be a YAML file, `site/pages/about.yml` for `/about`, with a title, a description and a list of blocks. The admin edits it as a form, a panel per block, with a live preview that follows the field being edited. New pages in the admin are structured pages.
- **Form blocks.** A block type for structured pages is a folder `site/blocks/<name>/` with the template `<name>.php` and the blueprint `<name>.yml`. The template reads the fields with `$block->get()`, `html()`, `items()` and `entry()`. Field types are text, textarea, markdown, number, date, boolean, select, image, url, list, entry and blocks.
- **Data.** Records with fixed fields live in `site/data/<collection>/`, one Markdown file per entry, described by a blueprint in `site/blueprints/<collection>.yml`. The admin has a new section "Data" to edit them. Templates and blocks query them with `$site->collection("seminars")->where("date", ">=", "today")->limit(3)`.
- **Entries as pages.** With `url: blog` in its blueprint, every entry of a collection is a page at `/blog/<slug>`, rendered by the template `site/blueprints/blog.php`. The blog of the demo works like that.
- **Converting Markdown pages.** *… › Convert to structured page* turns a Markdown page into a structured page at the same address. A Markdown block becomes a form block if a block type says in its blueprint under `convert` how to read it, otherwise it stays a Markdown block inside the structured page and looks as before. The Markdown file is kept in the history.
- **Multilingual pages.** With `languages: [en, de]` in `site/config.yml` a structured page holds every language in its one file, `heading: {en: Welcome, de: Willkommen}`, blocks and lists stay the same in all languages. A Markdown page has a file per language next to it, `about.de.md`. The admin edits a page in one language at a time, with a switcher above the editor.
- **Markdown blocks in structured pages.** A block with `markdown: "..."` instead of fields renders with the Markdown template of its type, so every existing block type can be used in structured pages right away.

The README describes all of it in the sections "Data", "Entries as pages", "Structured pages" and "Multilingual sites".

## What stays the same

- Markdown pages, their frontmatter and their blocks work as before. At one address a Markdown page still wins over a structured page.
- PHP pages, `site/template.php`, the AddOns and their hooks are unchanged.
- The update replaces `core/`, `web/admin/` and `vendor/` as always. Your `site/`, `local/` and `web/` content stay untouched.

## What changes

- **New pages are structured pages.** In the page list, *New page* creates a YAML file. *New Markdown page* creates a Markdown file as before. A new folder starts with `index.yml` instead of `index.md`. If you only want Markdown pages, use *New Markdown page*.
- **Structured pages need form blocks.** The update does not touch `site/`, so an existing site has no form blocks yet. Until you add some, *Add block* offers the Markdown blocks of the site under *Markdown blocks*. The demo of Reboot CMS 4 ships form blocks to start from, `intro`, `text`, `image-text`, `features`, `gallery`, `seminar-teaser`, `seminar-spotlight` and `blog-list`, each a folder in `site/blocks/`. Copy the ones you like and adapt them to your design. The Markdown blocks of the demo now carry the same names, `hero` became `intro`, `text-image` became `image-text`, `cards` became `gallery` and `three-columns` became `features`. Your own site keeps its blocks, and a form block takes over a Markdown block of another name with `convert: {from: hero}`.
- **New reserved folders.** `site/blueprints/` and `site/data/` have a meaning now. `site/blueprints/page.yml` describes the fields of structured pages, the other blueprints describe collections. A folder `site/blocks/<name>/` with `<name>.php` is a block type as well. It may share its name with a file `site/blocks/<name>.php`: Markdown blocks take the file, blocks with fields the folder.
- **A file per language.** The German version of a Markdown page lies next to it, `site/pages/about.de.md` instead of `site/pages/de/about.md`. The folder per language still works but is deprecated, the log names each file to rename. A file next to the page wins over the folder.
- **The navigation of the demo.** `site/config.yml` of the demo writes the navigation with the path first and a label per language, `/seminars: {en: Seminars, de: Seminare}`, instead of `Seminars: /seminars`. Your site keeps its configuration and template, nothing changes there. If you take over the configuration of the demo, take over `site/template.php` with it: the template of the demo reads both forms, an older template stops with an error on the new one.
- **Routing.** If no file in `site/pages/` matches a request, the entries of collections with `url` are tried before the pages of AddOns and the 404 page. Without such a blueprint nothing changes.
- **Page actions.** For scripts that post to the admin: the action `add_page` creates a structured page, `add_markdown_page` a Markdown page. `add_structured_page` is gone.
- **Update branches.** Every major version has its own branches. Reboot CMS 4 updates from `distrib-4` (stable) and `main-4` (unstable). Reboot CMS 3 stays on `distrib` and `main`.

## How to upgrade

### In the admin

1. Update Reboot CMS 3 to version 3.23 or later, as usual on the page *Update*.
2. The page then says that Reboot CMS 4 is available and lists what Reboot CMS 4 reads differently in your site, if anything. A `.yml` file in `site/pages/` for example becomes a page.
3. *Upgrade to Reboot CMS 4* makes a backup of `site/` and `web/media/` under *Backup*, keeps the current `core/`, `web/admin/` and `vendor/` in `local/previous-version/` and installs Reboot CMS 4 from `distrib-4`.
4. If the site does not work as before, *Back to Reboot CMS 3* on the page *Update* puts the kept version back. Once everything works, *Delete the previous version* frees the space.

### By hand

1. Make a backup, in the admin under *Backup* or as a copy of the project folder.
2. Download Reboot CMS 4 from the branch `distrib-4`: [reboot-cms-distrib-4.zip](https://github.com/shaack/reboot-cms/archive/refs/heads/distrib-4.zip).
3. Replace `core/`, `web/admin/` and `vendor/` of your installation with the ones from the download. Replace `composer.json` as well, it holds the version, and the files `README.md`, `AGENTS.md`, `LICENSE`, `run.sh` and `test.sh` if you use them.
4. Keep `site/`, `local/`, `web/index.php` and the rest of `web/` as they are.
5. Open the admin. The page *Update* shows version 4 and from now on checks `distrib-4`.
6. Optional: copy the form blocks you want from `site/blocks/` of the download into your site, and convert Markdown pages with *… › Convert to structured page*.

If you run your site from a clone of the repository, check out `distrib-4` instead, or merge it into your branch.

## Staying on Reboot CMS 3

Nothing forces the upgrade. The updater of Reboot CMS 3 keeps installing 3.x versions from `distrib`, and security fixes for Reboot CMS 3 are published there.
