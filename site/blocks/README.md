# Blocks

The block types of the site. A page is a list of blocks, and every block renders with a template from this folder. There are two kinds of blocks, and you can tell them apart by where they live.

## Structured blocks, in the folders

A folder `<name>/` is a block of structured pages, the YAML pages in `site/pages/` that the admin edits as forms. It holds two files, both named like the folder:

- `<name>.yml` is the blueprint. It names the fields of the block, and the admin builds the form from it.
- `<name>.php` is the template. It reads the fields with `$block->get()`, `html()`, `items()` and `entry()`.

```
intro/
  intro.yml     # the fields: heading, lead, image, button
  intro.php     # the HTML
```

The structured blocks of the demo are `intro`, `text`, `image-text`, `features`, `gallery`, `blog-list`, `seminar-teaser` and `seminar-spotlight`.

## Markdown blocks, directly in this folder

A file `<name>.php` directly in this folder is a block of Markdown pages. In a Markdown page an HTML comment like `<!-- intro -->` starts the block, and its template reads the Markdown below the comment with XPath queries, `$block->field("//h2", "Heading")` for example. A line with `---` separates the parts of a block.

The Markdown blocks of the demo are `intro`, `text`, `image-text`, `features`, `gallery` and `article`. The page `site/pages/blocks.md` shows each of them with the Markdown that produces it.

## Same name, two kinds

A file and a folder may share a name. Markdown pages use the file `intro.php`, structured pages the folder `intro/`. That is why `intro`, `text`, `image-text`, `features` and `gallery` exist twice. A structured page may also hold a Markdown block, written with `markdown:` instead of fields, which then renders with the file.

If one of the two is missing, a block takes the other. `seminar-teaser` has only a folder, and its blueprint says `markdown: true`, so its template renders both kinds.

When the admin converts a Markdown page into a structured page, the `convert` section in a blueprint says which Markdown block the structured block takes over and how to read its fields.

## Helpers

`_helpers.php` holds functions shared by the templates, like `blockClasses()`. A name with an underscore in front is no block type.

More in the README of the repository, in the sections "Block" and "Structured pages".
