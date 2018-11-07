# Reboot CMS

A Bootstrap concentric, flat file, Markdown CMS in PHP, inspired by [Pico](http://picocms.org) and [Redaxo](https://redaxo.org/).

The main idea was, to have a minimal CMS without needing a database, but with the support
of `Blocks`.

## Install

[Install composer](https://getcomposer.org/download/)

and run `./composer.phar install`

## Main Objects

In short:

- `Page` = `Template` + `Article`
- `Article` = `flat Markdown file` | `Markdown file with Blocks` | `PHP file`

Articles are auto routet, as they are structured in `/local/articles`.

### Template

Folder: `/local/tempalates`

A `Template` describes how to render a `Page`. `Templates` are written in PHP.
The `default.php` Template is used, if no other `Template` is defined for an
`Article`.

### Article

Folder: `/local/articles`

An `Article` contains the **main content** of a `Page`.  

It can be a **flat Markdown** file, or can contain multiple `Blocks` or
also can be a **PHP-File**, where everything is possible.

`Articles` are auto-routed on web-requests:

- `index.md` or `index.php` will be shown on requesting `/`
- `NAME.md` or `NAME.php` will be shown on requesting `/NAME`
- `FOLDER/index.md` (or .php) will be shown on requesting `/FOLDER`
- `FOLDER/NAME.md` (or .php) will be shown on requesting `/FOLDER/NAME`

### Block

Folder: `/local/blocks`

A `Block` is a mostly horizontal section in an `Article`. 

## ToDos

- multi language support