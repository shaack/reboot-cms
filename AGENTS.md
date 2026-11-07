# AGENTS.md

Guidance for AI coding agents working in a Reboot CMS project.

This file is a condensed orientation. For the full reference — installation,
configuration, addons, blocks and multilingual navigation — read the project's
`README.md` in full before making non-trivial changes.

## What this is

Reboot CMS is a flat-file, database-free CMS in PHP. Since version 4 a page is
usually a **structured page**, a YAML file of blocks with fields, and records like
blog posts are **data**. Markdown pages with blocks still work. Every URL maps
directly to a file. There is no database and no build step for content.

## Project layout

- `core/src/Shaack/` — CMS core classes (`Reboot`, `Site`, `Page`, `Block`, `Collection`, `Request`, `AddOn`, `AddOnLoader`)
- `core/addons/<Name>/` — AddOns shipped with the CMS (`EmailObfuscation`, `LanguageRedirect`, `ProtectedAreas`, `SecureDownloads`), one folder each
- `core/admin/` — admin interface (itself a Reboot CMS site)
- `site/` — the site you edit:
  - `pages/` — content pages (`.yml`, `.md` or `.php`)
  - `blocks/` — PHP templates for content blocks
  - `data/<collection>/` — records with fixed fields, one Markdown file per entry
  - `blueprints/<collection>.yml` — the fields of a collection, with `url:` its entries are pages rendered by `blueprints/<collection>.php`
  - `pages/<name>.yml` — a structured page, blocks with fields, see below
  - `addons/<Name>/<Name>.php` — site-specific addons, one folder each with optional `src/` and `admin/`
  - `template.php` — main HTML template
  - `config.yml` — navigation, addon registration, multilingual settings
- `web/` — document root (`index.php`, static assets)
- `local/` — environment config (`config.yml`, `.htpasswd`) — not in Git
- `tests/` — test suite

## Routing: file = URL

A request path maps straight onto `site/pages/`:

- `/` → `pages/index.yml` (or `index.md`)
- `/about` → `pages/about.yml`
- `/howto/git` → `pages/howto/git.yml`
- a folder is served by its `index` page
- `/blog/<slug>` → the entry `data/blog/<slug>.md`, because `blueprints/blog.yml` has `url: blog`

To find a page's source, append the URL path to `site/pages/`. To add a page,
create the file — no route registration needed. At one path `.md` wins over
`.yml`, which wins over `.php`. If no file matches, the entries of collections
with `url` are tried.

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

A page is split into **blocks** with HTML comments. Without any block comment
the whole file renders as a single `text` block:

```markdown
<!-- hero -->
# Welcome

<!-- text -->
Some content.
```

## Blocks

Each block name maps to `site/blocks/{name}.php`, or to the folder `site/blocks/{name}/{name}.php`. A block template is plain PHP
and receives a `$block` object — `$block->content()`, `$block->xpath()`,
`$block->getConfig()`. Adding a block type means adding one PHP file; there is
no framework or build step in between.

## Data

Records with fixed fields (seminars, events, people) are no pages. Each entry is a Markdown file in `site/data/<collection>/`, its fields in the frontmatter, the Markdown below it is the field `body`. `site/blueprints/<collection>.yml` lists the fields with `type`, `required`, `default` and `options`. Templates and blocks query them with `$site->collection("seminars")->where("date", ">=", "today")->sortBy("date")->limit(3)`. Entries that don't match the blueprint and drafts (`draft: true`) are left out. See the README section "Data".

## Structured pages

`site/pages/<name>.yml` is a structured page, the default kind of page: `title`, `description` and `blocks`, a list of blocks with a `type` and the fields of that type. A block type for structured pages is a folder `site/blocks/<type>/` with the template `<type>.php` and the blueprint `<type>.yml`; its template reads `$block->get("heading")`, `$block->html("text")`, `$block->items("cards")` (field type `list`) and `$block->entry("seminar")` (field type `entry`, an entry of a collection). A block with `markdown: "..."` instead of fields renders with the Markdown template of its type. A form block's blueprint may say under `convert` which Markdown block it takes over, so the admin converts Markdown pages into structured pages without losing anything. The admin edits these pages as forms. See the README section "Structured pages".

## Multilingual

A site is monolingual by default. To enable several languages, declare them in
`site/config.yml`:

```yaml
languages: [en, de]
defaultLanguage: en
```

The **first path segment** then selects the language, and pages live in a folder
per language — the URL maps onto the file as usual:

- `/de/about` → `site/pages/de/about.md`
- `/en/` → `site/pages/en/index.md`

A path whose first segment is not a configured language carries no prefix and
falls back to `defaultLanguage` (e.g. `/about` → `site/pages/about.md`).

In `template.php` and block templates the request object exposes:

- `$request->getLanguage()` — the active language code (`"en"`, `"de"`), for the
  `<html lang>` attribute and `hreflang` tags
- `$request->getPathWithoutLanguage()` — the path with the language prefix
  stripped, for building language-switcher links

Without `languages` in `config.yml` every request uses `defaultLanguage` (or
`"en"`) and no prefix is expected.

The built-in `LanguageRedirect` addon (enable it via `addons` in `config.yml`)
redirects visitors to the language version matching their browser or saved
preference. See the README for details.

## Conventions

- Core classes are namespaced `Shaack\Reboot\*` with PSR-4 autoloading.
- Admin CSS is authored in `web/admin/assets/screen.scss`; `screen.css` is
  compiled output — never edit the `.css` directly.
- Content and config are text under Git; prefer small, reviewable changes.
- Do not edit anything under `vendor/`, `web/admin/vendor/` or `node_modules/`. The admin's browser libraries in `web/admin/vendor/` are copied from `node_modules/` by `web/admin/build-vendor.sh`, run it after `npm install` or `npm update` there.

## Local development

```bash
./run.sh          # Apache + PHP in Podman at http://localhost:21007, Mailpit at http://localhost:25007
                  # writes local/config.local.yml on the first start (not in Git), ./run.sh 3000 3025 3080 for other ports
```

## Tests

```bash
./test.sh         # or: php tests/run.php
```

Add a test as `tests/<Name>Test.php` containing a class `Shaack\Tests\<Name>Test`;
the runner discovers `test*` methods automatically and supports `setUp`/`tearDown`.
Run the suite before finishing a change.
