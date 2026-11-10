---
title: Your content, your files
description: Why a website whose pages are plain files outlives every redesign, every host and every CMS that comes after it.
date: 2026-09-14
block: post
links: |
  - [Fast pages without tuning](blog/2026-10-01-fast-pages-without-tuning)
  - [Structure for editors, freedom for designers](blog/2026-10-12-structure-for-editors-freedom-for-designers)
  - [All posts](blog)
  - [The repository on GitHub](https://github.com/shaack/reboot-cms)
---

Most content management systems keep your texts in a database. That sounds harmless until the day you want to leave. Then the content sits in tables only that one system understands, wrapped in its markup, its shortcodes and its plugins. Moving it means an export, a migration script and a long weekend.

Reboot CMS takes the opposite route. Every page is a text file, a short YAML file with the blocks of the page or a Markdown file, and the path of the file is the address of the page. The text you write is the text that is stored. There is nothing in between that you would have to translate later.

## Ownership you can see

Open the folder `site/pages/` and you see your website. A page about pricing is `pricing.yml`, and next to it, in `site/data/blog/`, every post of this blog is a file of its own. Any editor opens it, any search finds it, and it reads as well in ten years as it does today.

That changes how a website feels to its owner. Content is no longer something the CMS holds for you. It is a folder you have, like the photos on your computer.

## History for free

Because everything is text, the whole site fits into Git. Each change is a commit with a date, an author and a message, and every earlier state of every page is one command away. Two people can work on the site at the same time and merge their changes like code.

For those who prefer a browser, the admin keeps its own history of every page as well, and a backup is a single click that packs the content into a zip file.

## No lock-in, by design

The day you outgrow Reboot CMS, your content comes with you. YAML and Markdown are read by static site generators, by other flat file systems, by documentation tools and by every AI model. Nothing has to be exported, because nothing was ever locked in.

That is the quiet promise of a flat file CMS. The software is replaceable, your content is not, and it stays where you put it.
