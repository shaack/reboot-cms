# Scope CMS

A Bootstrap concentric, flat file, Markdown CMS in PHP, inspired by [Pico](http://picocms.org) and [Redaxo](https://redaxo.org/).

The main idea is, to have a minimal CMS without needing a database, but with the support
of `Blocks`.

## Install

Download this repository, [install composer](https://getcomposer.org/download/),

and run `./composer.phar install`

## Main Objects

In short:

- `Page` = `Template` and `Article`
- `Article` = `Markdown file` or `PHP file`

### Article

Folder: `/local/articles`

An `Article` contains the content of a `Page`.  

It can be a **flat Markdown** file, can contain multiple `Blocks` or
also can be a **PHP-File**, where everything is possible.

`Articles` are auto-routed on web-requests:

- `index.md` or `index.php` will be shown on requesting `/`
- `NAME.md` or `NAME.php` will be shown on requesting `/NAME`
- `FOLDER/index.md` (or .php) will be shown on requesting `/FOLDER`
- `FOLDER/NAME.md` (or .php) will be shown on requesting `/FOLDER/NAME`

Example for a Markdown Article with Blocks:

``` markdown
<!-- 
block: jumbotron
values: 
    headline: Reboot CMS
    lead: A Bootstrap concentric, flat file, markdown CMS in PHP
    buttonText: Learn more
    buttonLink: /documentation
-->
The main idea is, to have a minimal CMS without needing a database, but with the support
of Blocks.

<!-- block: markdown -->
## This is a markdown block

Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod 
tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, 
quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea 
commodo consequat. 
```
This `Article` contains two `Blocks`, jumbotron and markdown.

### Block

Folder: `/local/blocks`

A `Block` is a mostly horizontal section in an `Article`. It can be seen as a template for an `Article` section. 

Examples for `Blocks`are 
- `jumbotron`, renders a Bootstrap Jumbotron
- `markdown`, renders Mardown
- `navbar`, renders a Bootstrap Navbar

### Template

Folder: `/local/tempalates`

A `Template` describes how to render a `Page`. `Templates` are written in PHP.
The `default.php` Template is used, if no other `Template` is defined for an
`Article`.


## ToDos

- multi language support
