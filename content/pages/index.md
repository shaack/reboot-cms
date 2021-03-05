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

<!-- markdown -->

## This is just a markdown block

Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt ut labore et dolore magna
aliqua. Ut enim ad minim veniam.

<!-- text-image -->

## The text-image block

Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt ut labore et dolore magna
aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.

---

![alt text](dummy.svg "Title Text")


<!-- 
text-image:
    image-position: left
-->

## The text-image block can also display the image on the left side

This is done via configuration in the block-comment.

```html
<!-- 
text-image:
    image-position: left
-->
```

--- 

![alt text](dummy.svg "Title Text")>