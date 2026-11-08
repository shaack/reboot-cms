---
title: Structure for editors, freedom for designers
description: Pages made of form blocks give editors clear fields to fill in and leave the design to those who made it. No page builder required.
date: 2026-10-12
links: |
  - [Your content, your files](blog/2026-09-14-your-content-your-files)
  - [Fast pages without tuning](blog/2026-10-01-fast-pages-without-tuning)
  - [Markdown blocks of this demo](blocks)
  - [Documentation](documentation)
---

Every website team knows the tension. Editors want to change their pages without asking anyone. Designers want the site to look the way it was designed, also six months after launch. Page builders try to serve both and usually serve neither, the editor gets a hundred options and the design gets a hundred variations.

## Blocks as the agreement

Reboot CMS solves this with blocks. A designer defines the building blocks of the site, a hero, a gallery, a row of features, each as a small folder in `site/blocks/`. It holds a template for the HTML and a blueprint that names the fields, a heading, a text, an image, a list of items.

A page is a list of these blocks. The editor adds a block, fills in its fields and moves it to the right place. The editor decides what the page says and in which order. The block decides how it looks. Neither has to step on the other's ground.

## Filling in instead of formatting

In the admin every block is a small form. A heading is a text field, an image is picked from the media library, a list grows with a button. Editors see only the fields that matter for this block, and nothing they enter can break the layout.

Next to the form runs a live preview of the page. It scrolls along with the field being edited, so the editor sees right away where the text lands and how it looks. Blocks can be collapsed, moved and switched off for a while without losing their content.

Because the content carries no design, a redesign does not touch it. Change the templates, and every page follows.

## A new block is an afternoon

When a page needs something new, a comparison table or a team grid, the designer adds a folder with two files. The blueprint lists the fields in a few lines of YAML, and the admin builds the form from it. The template reads the fields with `$block->get()` and renders them in whatever HTML the design needs. There is no framework to learn and no build step.

Records that appear on several pages, like the seminars or the posts of this blog, live apart from the pages as data. A block can show the next three seminars or the latest posts, and they stay up to date without anyone touching the page.

## Markdown, if you like it

Pages written in Markdown keep working. Some people prefer typing a hash sign to clicking a field, and for them the admin still creates Markdown pages. Such a page can be converted into a structured page later, with its Markdown blocks becoming form blocks where the site provides them.

The [Markdown blocks page](blocks) of this demo shows each Markdown block next to the text that produces it.
