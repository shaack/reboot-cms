# Reboot CMS

A flat file, Markdown CMS in PHP, inspired by [Pico](http://picocms.org) and [Redaxo](https://redaxo.org/).

A minimal CMS without needing a database, but with the support
of `Blocks`.

### Websites using reboot-cms

- [wukies.de](https://wukies.de)
- [chesscoin032.com](https://chesscoin032.com)

## Install

Download this repository, [install composer](https://getcomposer.org/download/),

and run `composer.phar install`

## Documentation

In short:

- `Page` = `Template` (view) + `Article` (content)
- `Article` = `Markdown file` (with blocks) or `PHP file` (where you can do everything)

### Article

Folder: `/content/articles`

An `Article` contains the content of a `Page`.  

It can be a **flat Markdown** file, can contain multiple `Blocks` or
also can be a **PHP-File**, where everything is possible.

`Articles` are auto-routed on web-requests:

- `index.md` or `index.php` will be shown on requesting `/`
- `NAME.md` or `NAME.php` will be shown on requesting `/NAME`
- `FOLDER/index.md` (or .php) will be shown on requesting `/FOLDER`
- `FOLDER/NAME.md` (or .php) will be shown on requesting `/FOLDER/NAME`

Example for a Markdown `Article` with `Blocks`:

``` markdown
<!-- 
block: jumbotron
values: 
    headline: Reboot CMS
    lead: A flat file, markdown CMS in PHP
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
This `Article` contains two `Blocks`, "jumbotron" and "markdown". It will render to
this:

![](https://shaack.com/projekte/assets/img/reboot-cms-jumbotron.png)

Markdown files without blocks will render as a flat Markdown Article.

### Block

Folder: `/themes/THEME_NAME/blocks`

A `Block` describes how a Block is rendered. Blocks are written in PHP.

The code for the "jumbotron" `Block` which was used in the `Article` above,
looks like this:
``` php
<div class="container">
    <div class="jumbotron">

        <h1 class="display-4"><?= $this->value("headline") ?></h1>
        <p class="lead"><?= $this->value("lead") ?></p>
        <hr class="my-4">
        <?= $this->content() ?>
        <p class="lead">
            <a class="btn btn-primary btn-lg" href="<?= $this->value("buttonLink") ?>"
               role="button"><?= $this->value("buttonText") ?></a>
        </p>
    </div>
</div>
```

### Template

Folder: `/themes/THEME_NAME/tempalates`

A `Template` describes how an `Article` is rendered to a `Page`. `Templates` are written in PHP.
The `default.php` Template is used, if no other `Template` is defined for an
`Article`.