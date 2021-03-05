---
title: Reboot CMS
description: Reboot CMS is a flat file CMS, with the support of blocks.
author: Stefan Haack
---

<!--  
jumbotron:
    test: 123
-->

# Reboot CMS

A flat file, markdown CMS with blocks

---
The main idea is, to have a **minimal CMS** without needing a database, but with the support of blocks.

---
[Learn more](/documentation)

<!-- text-image -->

## The text-image block

The gray block above was a jumbotron block. This one is a text-image block, it contains two parts.
In the markdown content source, parts are separated by `---`.

---

![alt text](dummy.svg "Title Text")


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

![alt text](dummy.svg "Title Text")>

<!-- markdown -->

## This is a text block

A text block contains just one markdown part.

### This is a h3 in a text block

It can contain

- [links](https://www.chessmail.de)
- and lists

and everything else in markdown.