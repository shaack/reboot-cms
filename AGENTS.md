# AGENTS.md

Guidance for AI coding agents working in a Reboot CMS project.

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
