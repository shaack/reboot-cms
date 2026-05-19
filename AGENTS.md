# AGENTS.md

Guidance for AI coding agents working in a Reboot CMS project.

This file is a condensed orientation. For the full reference — installation,
configuration, addons, blocks and multilingual navigation — read the project's
`README.md` in full before making non-trivial changes.

## What this is

Reboot CMS is a flat-file, database-free CMS in PHP. Content is plain Markdown,
pages render through optional **blocks**, and every URL maps directly to a file.
There is no database, no schema, and no build step for content.

## Project layout

- `core/src/Shaack/` — CMS core classes (`Reboot`, `Site`, `Page`, `Block`, `Request`, `AddOn`)
- `core/admin/` — admin interface (itself a Reboot CMS site)
- `site/` — the site you edit:
  - `pages/` — content pages (`.md` or `.php`)
  - `blocks/` — PHP templates for content blocks
  - `addons/` — site-specific addon classes
  - `template.php` — main HTML template
  - `config.yml` — navigation, addon registration, multilingual settings
- `web/` — document root (`index.php`, static assets)
- `local/` — environment config (`config.yml`, `.htpasswd`) — not in Git
- `tests/` — test suite

## Routing: file = URL

A request path maps straight onto `site/pages/`:

- `/` → `pages/index.md`
- `/about` → `pages/about.md`
- `/howto/git` → `pages/howto/git.md`
- a folder is served by its `index.md`

To find a page's source, append the URL path to `site/pages/`. To add a page,
create the file — no route registration needed. A `.php` page is used when no
`.md` exists at that path.

## Pages

A page is Markdown. Optional YAML frontmatter at the top sets metadata:

```markdown
---
title: About
hide-nav: true
---
```

A page is split into **blocks** with HTML comments. Without any block comment
the whole file renders as a single `text` block:

```markdown
<!-- hero -->
# Welcome

<!-- text -->
Some content.
```

## Blocks

Each block name maps to `site/blocks/{name}.php`. A block template is plain PHP
and receives a `$block` object — `$block->content()`, `$block->xpath()`,
`$block->getConfig()`. Adding a block type means adding one PHP file; there is
no framework or build step in between.

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
- Do not edit anything under `vendor/` or `node_modules/`.

## Local development

```bash
./run.sh          # serve at http://localhost:8080 (./run.sh 3000 for another port)
```

## Tests

```bash
./test.sh         # or: php tests/run.php
```

Add a test as `tests/<Name>Test.php` containing a class `Shaack\Tests\<Name>Test`;
the runner discovers `test*` methods automatically and supports `setUp`/`tearDown`.
Run the suite before finishing a change.
