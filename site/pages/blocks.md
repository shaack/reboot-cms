---
title: Markdown blocks in Reboot CMS
description: The Markdown blocks that ship with Reboot CMS, each with the Markdown that produces it.
---

<!-- text -->

# Markdown blocks

Most pages of this demo are structured pages, edited as forms. This page is a Markdown page, written as text. An HTML comment with the name of a block type, like the first line of the examples below, starts a block, and the type decides how the Markdown below it is rendered. A line with `---` separates the parts of a block, for example the text and the image of an "image-text" block.

Every block type is a PHP template in `site/blocks/`. Each section below shows the Markdown first and the rendered block right after it. A structured page can hold these blocks as well, under *Add block › Markdown blocks*.

## Intro

```markdown
<!-- intro -->

# Headline

One sentence that says what this is.

---

A paragraph with **Markdown** for the details.

---

[Call to action](blocks)
```

<!-- intro -->

# Headline

One sentence that says what this is.

---

A paragraph with **Markdown** for the details.

---

[Call to action](blocks)

<!-- text -->

## Text and image

The image sits on the right by default. A configuration in the block comment moves it to the left.

```markdown
<!--
image-text:
    image-position: left
-->

## A heading

Some text with a [link](documentation).

---

![Alt text](media/dummy.svg "Title")
```

<!--
image-text:
    image-position: left
-->

## A heading

Some text with a [link](documentation). The block takes the text from the first part and the image from the second, its position comes from the configuration in the block comment.

---

![Alt text](media/dummy.svg "Title")

<!-- text -->

## Features

```markdown
<!-- features -->

### First

Text of the first column.

---

### Second

Text of the second column.

---

### Third

Text of the third column.
```

<!-- features -->

### First

Each column is one part of the block, separated by `---`. The columns stack on small screens.

---

### Second

A column holds any Markdown, a heading, a paragraph, a list or a [link](documentation).

---

### Third

Three parts make three columns. The template could as well count the parts and size the columns to fit.

<!-- text -->

## Gallery

A list of images becomes a gallery, a click opens an image enlarged. The alt text of each image is the caption.

```markdown
<!-- gallery -->

## Heading

- ![First card](media/dummy.svg)
- ![Second card](media/dummy.svg)
- ![Third card](media/dummy.svg)
```

<!-- gallery -->

## Heading

- ![First card](media/dummy.svg)
- ![Second card](media/dummy.svg)
- ![Third card](media/dummy.svg)

<!-- text -->

## Text

The "text" block renders its Markdown as it is. A page without any block comment is one text block, which makes Reboot CMS a plain Markdown CMS until you need more.

```markdown
<!-- text -->

## Heading

A paragraph, a list, a table, a code block, everything Markdown offers.
```

## Inverse

Every block of this demo but the text can be set inverse with a configuration in its comment. It then stands as a white band over the whole width, and the intro, which is white already, turns dark. The gallery below is the same as above, set inverse.

```markdown
<!-- gallery: {inverse: true} -->
```

<!-- gallery: {inverse: true} -->

## Heading

- ![First card](media/dummy.svg)
- ![Second card](media/dummy.svg)
- ![Third card](media/dummy.svg)

<!-- text -->

## Your own blocks

A new block type is a new PHP file in `site/blocks/`. As a folder with the template and a blueprint, a YAML file of its fields, it becomes a block of [structured pages](documentation#structured-pages), which editors fill in as a form. File and folder may share a name: the form blocks of this demo are called `intro`, `text`, `image-text`, `gallery` and `features` as well, Markdown pages use the file, structured pages the folder. The template receives the parts of the block and picks what it needs, the heading, the image, the list, with a short query. The [documentation](documentation#block) shows how the blocks above are built.

The [test pages](testpages) hold a few edge cases used during development, an empty page, an unknown block and a code block with HTML inside.
