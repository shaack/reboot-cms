---
title: Blocks in Reboot CMS
description: The block types that ship with Reboot CMS, each with the Markdown that produces it.
---

<!-- text -->

# Blocks

A page in Reboot CMS is one Markdown file. An HTML comment with the name of a block type, like the first line of the examples below, starts a block, and the type decides how the Markdown below it is rendered. A line with `---` separates the parts of a block, for example the text and the image of a "text-image" block.

Every block type is a PHP template in `site/blocks/`. This demo ships with five of them. Each section below shows the Markdown first and the rendered block right after it.

## Hero

```markdown
<!-- hero -->

# Headline

One sentence that says what this is.

---

A paragraph with **Markdown** for the details.

---

[Call to action](blocks)
```

<!-- hero -->

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
text-image:
    image-position: left
-->

## A heading

Some text with a [link](documentation).

---

![Alt text](media/dummy.svg "Title")
```

<!--
text-image:
    image-position: left
-->

## A heading

Some text with a [link](documentation). The block takes the text from the first part and the image from the second, its position comes from the configuration in the block comment.

---

![Alt text](media/dummy.svg "Title")

<!-- text -->

## Three columns

```markdown
<!-- three-columns -->

### First

Text of the first column.

---

### Second

Text of the second column.

---

### Third

Text of the third column.
```

<!-- three-columns -->

### First

Each column is one part of the block, separated by `---`. The columns stack on small screens.

---

### Second

A column holds any Markdown, a heading, a paragraph, a list or a [link](documentation).

---

### Third

Three parts make three columns. The template could as well count the parts and size the columns to fit.

<!-- text -->

## Cards

A list of images becomes a row of cards. The alt text of each image is the caption.

```markdown
<!-- cards -->

## Heading

- ![First card](media/dummy.svg)
- ![Second card](media/dummy.svg)
- ![Third card](media/dummy.svg)
```

<!-- cards -->

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

## Your own blocks

A new block type is a new PHP file in `site/blocks/`. The template receives the parts of the block and picks what it needs, the heading, the image, the list, with a short query. The [documentation](documentation#block) shows how the blocks above are built.

The [test pages](testpages) hold a few edge cases used during development, an empty page, an unknown block and a code block with HTML inside.
