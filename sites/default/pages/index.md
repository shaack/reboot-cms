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
[Learn more](/documentation)

<!-- text-image -->

## The text-image block

The gray block above was a jumbotron block. This one is a text-image block, it contains two parts.
Parts are separated by `---`.

---
![alt text](/media/dummy.svg "Title Text")

<!-- 
text-image:
    image-position: left
-->

## Configure blocks in the block comment

The text-image block can also display the image to the left.

```html
<!-- 
text-image:
    image-position: left
-->
```

---
![alt text](/media/dummy.svg "Title Text")>

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

<!-- text -->

## This is a text block

A text block contains just one markdown part.

### This is a h3 in a text block

It can contain

- [links](https://www.chessmail.de)
- and lists

and everything else in markdown.