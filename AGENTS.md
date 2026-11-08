# AGENTS.md

Guidance for AI coding agents working in a Reboot CMS project, on a site built with it or on the CMS itself.

This file is a condensed orientation. For the full reference, with installation, configuration, addons, blocks and multilingual navigation, read the project's `README.md` in full before making non-trivial changes.

## What this is

Reboot CMS is a flat-file, database-free CMS in PHP. Since version 4 a page is usually a **structured page**, a YAML file of blocks with fields, and records like blog posts are **data**. Markdown pages with blocks still work. Every URL maps directly to a file. There is no database and no build step for content.

## Requirements

- **PHP** 8.1 or higher with the extensions json, fileinfo and dom
- **Web server:** Apache with mod_rewrite, or a compatible server
- **Composer** for the PHP dependencies: erusev/parsedown (Markdown), symfony/yaml (config and YAML pages), whitehat101/apr1-md5 (passwords), phpmailer/phpmailer (mail)
- **Node.js/npm** only to update the admin's browser libraries: `cd web/admin && npm install` copies the needed files from `node_modules/` (ignored) into `web/admin/vendor/` (committed) via `build-vendor.sh`
- **No database.** All content is stored as flat files.

## Project layout

- `core/src/Shaack/Reboot/`: CMS core classes, see "Core classes" below
- `core/addons/<Name>/`: AddOns shipped with the CMS (`CodeHighlighting`, `EmailObfuscation`, `LanguageRedirect`, `ProtectedAreas`, `SecureDownloads`), one folder each with `<Name>.php`, `src/` and `admin/`
- `core/admin/`: the admin interface, itself a Reboot CMS site. Its addons `Authentication` and `Admin` live in `core/admin/addons/`. Authentication keeps "Stay signed in" tokens hashed in `local/remember-tokens.json` via `src/RememberMeStore.php`, a subclass of `Shaack\Reboot\TokenStore`, which ProtectedAreas uses as well for login links and its 30 day cookie.
- `site/`: the site you edit
  - `pages/`: content pages (`.yml`, `.md` or `.php`)
  - `blocks/`: block templates, Markdown blocks as files, structured blocks as folders, see `site/blocks/README.md`
  - `data/<collection>/`: records with fixed fields, one Markdown file per entry
  - `blueprints/<collection>.yml`: the fields of a collection, with `url:` its entries are pages rendered by `blueprints/<collection>.php`
  - `addons/<Name>/<Name>.php`: site-specific addons, one folder each with optional `src/` and `admin/`
  - `template.php`: main HTML template
  - `config.yml`: navigation, addon registration, multilingual settings
- `web/`: document root (`index.php`, static assets, `media/`)
- `local/`: environment config and state, not in Git apart from the shipped defaults
- `tests/`: test suite
- `docs/`: upgrade notes and the screenshot scripts

## Request lifecycle

```
web/index.php → Reboot → Site → Page::render() → Block::render() → template.php
```

## Routing: file = URL

A request path maps straight onto `site/pages/`:

- `/` → `pages/index.yml` (or `index.md`)
- `/about` → `pages/about.yml`
- `/howto/git` → `pages/howto/git.yml`
- a folder is served by its `index` page
- `/blog/<slug>` → the entry `data/blog/<slug>.md`, because `blueprints/blog.yml` has `url: blog`
- `404.yml` (or `.md`, `.php`) is the custom 404 page

To find a page's source, append the URL path to `site/pages/`. To add a page, create the file, no route registration needed. At one path `.md` wins over `.yml`, which wins over `.php`. If no file matches, the entries of collections with `url` are tried (`Page::renderEntry`), then the pages of AddOns (`Site::getAddOnPage()`), then the 404 page.

New pages are structured pages, see below.

## Markdown pages

A Markdown page is Markdown. Optional YAML frontmatter at the top sets metadata:

```markdown
---
title: About
hide-nav: true
---
```

Templates read it with `$page->meta('title', 'Default')`, escaped for HTML; `$page->meta('key', null, false)` returns a string unescaped.

A page is split into **blocks** with HTML comments. Without any block comment the whole file renders as a single `text` block:

```markdown
<!-- intro -->
# Welcome

<!-- text -->
Some content.
```

A line with `---` separates the parts of a block. A block can be configured in its comment, `<!-- image-text: {image-position: left} -->`, the template reads it with `$block->getConfig()`.

## Blocks

Each block name maps to `site/blocks/{name}.php`, or to the folder `site/blocks/{name}/{name}.php`. Both may exist with the same name: Markdown blocks use the file, blocks of structured pages with fields use the folder, and each falls back to the other (`Block::templateFile($site, $name, $structured)`, `Block::isForMarkdown()`, `Block::names()`). A block template is plain PHP and receives a `$block` object. A Markdown block reads its content with `$block->content()`, `$block->field()` and `$block->xpath()`, XPath with the addition of `part(n)`. Adding a block type means adding one PHP file, or a folder with template and blueprint; there is no framework or build step in between. A file whose name starts with an underscore, like `_helpers.php`, is a helper and no block.

## Data

Records with fixed fields (seminars, events, people) are no pages. Each entry is a Markdown file in `site/data/<collection>/`, its fields in the frontmatter, the Markdown below it is the field `body`. `site/blueprints/<collection>.yml` lists the fields with `type`, `required`, `default` and `options`. Templates and blocks query them with `$site->collection("seminars")->where("date", ">=", "today")->sortBy("date")->limit(3)`. Entries that don't match the blueprint and drafts (`draft: true`) are left out. See the README section "Data".

A collection blueprint with `url: blog` makes each entry a page at `/blog/<slug>`, rendered by `site/blueprints/blog.php` with `$entry`; scalar entry fields become page config. `Entry::getUrl()` returns its address. In the demo the blog lives in `site/data/blog/` and the block `blog-list` lists it.

## Structured pages

`site/pages/<name>.yml` is a structured page, the default kind of page: `title`, `description` and `blocks`, a list of blocks with a `type` and the fields of that type. `site/blueprints/page.yml` may replace these page fields, it needs one field of type `blocks`.

A block type for structured pages is a folder `site/blocks/<type>/` with the template `<type>.php` and the blueprint `<type>.yml` (`Blueprint::forBlock`, `Blueprint::blockTypes`). Its template reads `$block->get("heading")`, `$block->html("text")`, `$block->items("images")` (field type `list`) and `$block->entry("seminar")` (field type `entry`, an entry of a collection). `get()` and `date()` escape strings for HTML, `html()` renders Markdown and does not escape.

A block with `markdown: "..."` instead of fields renders with the Markdown template of its type (`Blueprint::isMarkdownBlock`). A blueprint with `markdown: true` is offered in Markdown pages as well, its template then reads Markdown when `$block->getBlueprint()` is null, like `seminar-teaser`.

A form block's blueprint may say under `convert: {from, fields}` which Markdown block it takes over and how to read it (XPath strings, `{part: n}`, lists with `each`), so the admin converts Markdown pages into structured pages without losing anything. The admin edits structured pages as forms. See the README section "Structured pages".

## Multilingual

A site is monolingual by default. To enable several languages, declare them in `site/config.yml`:

```yaml
languages: [en, de]
defaultLanguage: en
```

The **first path segment** then selects the language, and pages live in a folder per language, the URL maps onto the file as usual:

- `/de/about` → `site/pages/de/about.md`
- `/en/` → `site/pages/en/index.md`

A path whose first segment is not a configured language carries no prefix and falls back to `defaultLanguage` (e.g. `/about` → `site/pages/about.md`).

In `template.php` and block templates the request object exposes:

- `$request->getLanguage()`: the active language code (`"en"`, `"de"`), for the `<html lang>` attribute and `hreflang` tags
- `$request->getPathWithoutLanguage()`: the path with the language prefix stripped, for building language-switcher links

Without `languages` in `config.yml` every request uses `defaultLanguage` (or `"en"`) and no prefix is expected.

The built-in `LanguageRedirect` addon (enable it via `addons` in `config.yml`) redirects visitors to the language version matching their browser or saved preference. See the README for details.

## AddOns

An addon is a folder `addons/<Name>/` with `<Name>.php` (class in namespace `Shaack\Reboot\AddOns`), optional `src/` (helpers in `Shaack\Reboot\AddOns\<Name>\`, autoloaded by `AddOnLoader`) and optional `admin/` (admin pages, mounted at `/admin/addons/<name>`, old `/admin/<name>` redirects). Lookup order: `site/addons/<Name>/`, deprecated `site/addons/<Name>.php`, `core/addons/<Name>/`. Register it in `site/config.yml` under `addons:`.

Hooks: `init()`, `preRender(Request)`, `postRender(Request, content)`, `getPages()`, `getAdminPath()`, `getAdminPages()`, `getAdminMenu()`. The admin preview (`core/admin/api/pages-preview.php`) runs the `postRender` hooks as well.

The `Admin` addon of the admin site mounts the `admin/` pages and menu entries of the registered site addons via `getPages()` and `getNavbarStructure()`, so an unregistered addon shows neither. `AdminMenu::build()` puts every registered addon into the dropdown "AddOns (n)": its own flat menu entry, or else its info page `/addons/<name>` (`core/admin/addons/Admin/pages/addon-info.php`, renders the addon's README.md).

## Core classes

| Class | Purpose |
|-------|---------|
| `Reboot` | Bootstrap coordinator, loads `local/config.yml` and the overlay `local/config.local.yml` |
| `Site` | Site manager, config, addons |
| `Page` | Renders Markdown, structured and PHP pages and the entries of collections |
| `Block` | A block, its template and the XPath queries of Markdown blocks |
| `Blueprint` | Field definitions from `site/blueprints/<name>.yml` or a block folder, normalizes and validates values |
| `Collection` | The entries of `site/data/<name>/`, queried via `$site->collection()` (`where`, `sortBy`, `limit`) |
| `Entry` | One data entry, a Markdown file with frontmatter fields, `body` is the Markdown below |
| `FieldAccess` | Trait with `get`, `html`, `date`, `items`, `entry` for `Entry`, `Block` and `Item` (list items) |
| `YamlWriter` | Writes readable YAML (quotes only where needed, `\|` blocks), used for data entries and structured pages |
| `Request` | HTTP request parser, language of the path |
| `AddOn`, `AddOnLoader` | Extension base class, finds and loads addons |
| `Mailer` | Sends mail via PHPMailer, configured in `local/config.yml` under `mail:`, `$reboot->getMailer()` |

The admin's own classes live in `core/admin/addons/Admin/src/`: `FieldForm` (HTML of a form from a blueprint), `FieldInput` (form → values → clean values), `StructuredPageHandler` (reads and writes structured pages), `PageConverter` (Markdown page → structured page), `PageValidator`, `DataEntryHandler`, `PageHistoryHelper`. The template of the structured page editor is `core/admin/addons/Admin/templates/structured-page.php`, its browser side `web/admin/assets/field-form.js`. The admin action `add_page` creates a `.yml` page, `add_markdown_page` a `.md` page, a new folder gets `index.yml`.

## Configuration

- `local/config.yml`: logging level (0 = debug, 1 = info, 2 = error), editor, mail; shipped with the CMS and edited on the server
- `local/config.local.yml`: optional per-machine overlay merged over `config.yml` (`Reboot::loadLocalConfig`), ignored by Git; `run.sh` writes one pointing mail at Mailpit
- `local/.htpasswd`: admin credentials (APR1-MD5), roles in `local/roles.yml`. The admin asks for the first account on the first visit of `/admin`.
- `site/config.yml`: site settings, navbar, addon registration

## Conventions

- Core classes are namespaced `Shaack\Reboot\*` with PSR-4 autoloading.
- Admin CSS is authored in `web/admin/assets/screen.scss`; `screen.css` is compiled output, never edit the `.css` directly. Compile with `sass screen.scss screen.css` in `web/admin/assets`.
- The CSS of the demo site is `web/assets/styles/screen.css`, written directly.
- Content and config are text under Git; prefer small, reviewable changes.
- Do not edit anything under `vendor/`, `web/admin/vendor/` or `node_modules/`. The admin's browser libraries in `web/admin/vendor/` are copied from `node_modules/` by `web/admin/build-vendor.sh`, run it after `npm install` or `npm update` there.

## Local development

```bash
composer install  # PHP dependencies, if vendor/ is missing
./run.sh          # Apache + PHP in Podman at http://localhost:21007, Mailpit at http://localhost:25007
                  # writes local/config.local.yml on the first start (not in Git), ./run.sh 3000 3025 3080 for other ports
```

The site is at `http://localhost:21007`, the admin at `http://localhost:21007/admin`.

## Tests

```bash
./test.sh         # or: php tests/run.php
```

Add a test as `tests/<Name>Test.php` containing a class `Shaack\Tests\<Name>Test`; the runner discovers `test*` methods automatically and supports `setUp`/`tearDown`. Run the suite before finishing a change.
