---
title: Fast pages without tuning
description: Speed is a feature visitors notice before they read a word. Reboot CMS gets there by leaving things out.
---

<!-- text -->

# Fast pages without tuning

<time datetime="2026-10-01">October 1, 2026</time>

<!-- article -->

A visitor decides within a second or two whether a page is worth waiting for. Every moment of a blank screen costs attention, and search engines know it. Loading speed has long been part of how Google ranks a page, and slow pages lose visitors before they have seen the first sentence.

The usual answer is tuning. A caching plugin, an image optimizer, a CDN, a second caching plugin for the first one. Each fixes a little of what the system made slow in the first place.

## Fast because there is less

Reboot CMS starts from the other end. There is no database to ask, no framework to boot and no plugin chain to run. A request reads one Markdown file, renders it into the template and sends it out. That takes a few milliseconds on ordinary shared hosting.

What reaches the browser is what you put into the template. No admin scripts, no tracking you did not choose, no stylesheet for features the page does not use. The demo you are looking at loads Bootstrap and one small stylesheet, and that is all.

## A score of 100

Sites built with Reboot CMS reach a performance score of 100 in Google PageSpeed Insights, [shaack.com](https://shaack.com) among them. Not after weeks of optimization, but because there was little to optimize.

The same simplicity helps where speed is not measured. Fewer moving parts mean fewer updates, fewer security notices and fewer surprises after a server move.

## Speed that stays

Performance tends to decay. A site launches fast, then grows plugins and widgets until it is slow again. With Reboot CMS every addition is a deliberate line in a template or a small PHP file in `site/blocks/`. Nothing arrives by itself, so nothing slows the site down by itself.

---

- [Your content, your files](blog/2026-09-14-your-content-your-files)
- [Structure for editors, freedom for designers](blog/2026-10-12-structure-for-editors-freedom-for-designers)
- [All posts](blog)
- [PageSpeed Insights](https://pagespeed.web.dev/)
