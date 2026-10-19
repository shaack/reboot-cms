---
title: Structure for editors, freedom for designers
description: Blocks give editors a clear frame to write in and leave the design to those who made it. No page builder required.
---

<!-- text -->

# Structure for editors, freedom for designers

<time datetime="2026-10-12">October 12, 2026</time>

<!-- article -->

Every website team knows the tension. Editors want to change their pages without asking anyone. Designers want the site to look the way it was designed, also six months after launch. Page builders try to serve both and usually serve neither, the editor gets a hundred options and the design gets a hundred variations.

## Blocks as the agreement

Reboot CMS solves this with blocks. A designer defines the building blocks of the site, a hero, a gallery, a row of features, each as a small template in `site/blocks/`. The editor writes Markdown and chooses which block a section uses, with a one line comment above it.

The editor decides what the page says and in which order. The block decides how it looks. Neither has to step on the other's ground.

## Writing stays writing

Inside a block, content is plain Markdown. A heading is a hash sign, a link is a pair of brackets, an image is a link with an exclamation mark. Editors who have written an email with formatting already know most of it, and the admin's editor has a menu for the rest, including a list of all block types with an example to insert.

Because the content carries no design, a redesign does not touch it. Change the templates, and every page follows.

## A new block is an afternoon

When a page needs something new, a comparison table or a team grid, the designer adds one PHP file. There is no framework to learn and no build step. The template picks the heading, the list and the image from the Markdown with short queries, and renders them in whatever HTML the design needs.

The [Blocks page](blocks) of this demo shows each shipped block next to the Markdown that produces it.

---

- [Your content, your files](blog/2026-09-14-your-content-your-files)
- [Fast pages without tuning](blog/2026-10-01-fast-pages-without-tuning)
- [The blocks of this demo](blocks)
- [Block documentation](documentation#block)
