---
title: Reboot CMS
description: Reboot CMS is a flat file CMS, with the support of blocks.
author: Stefan Haack

---

<!-- jumbotron -->

# Reboot CMS

A flat file, markdown CMS with blocks

---
The main idea is, to have a **minimal CMS** without needing a database, but with the support of blocks.

---
[Learn more](documentation)

<!-- text-image -->

## The text-image block

The gray block above was a jumbotron block. This one is a text-image block. Blocks are defined in PHP, see the
[Reboot CMS documentation](documentation)

---
![alt text](../../web/media/dummy.svg "Title Text")

<!--
text-image:
    image-position: left
-->

## Configure blocks in the block comment

The text-image block can also display the image to the left.

<pre><code>&lt;!-- 
text-image:
    image-position: left
--&gt;</code></pre>

---
![alt text](../../web/media/dummy.svg "Title Text")>

<!-- three-columns -->

### the

Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint
occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est.

---
### three-colums

Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquid ex ea commodi consequat. Quis aute
iure reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur.

---
### block

Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt ut labore et dolore magna
aliqua.

<!-- cards -->

## The block `cards`

- ![This is](/media/dummy.svg)
- ![a dynamic list](/media/dummy.svg)
- ![of images.](/media/dummy.svg)
- ![4 is enough.](/media/dummy.svg)

<!-- text -->

## This is a text block

A text block contains just one markdown part.

### This is a h3 in a text block

It can contain

- [links](https://shaack.com)
- and lists

and everything else in markdown.