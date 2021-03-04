# Reboot CMS

A flat file, Markdown CMS in PHP, inspired by [Pico](http://picocms.org) and [Redaxo](https://redaxo.org/).

A minimal CMS without needing a database, but with the support
of `Blocks`.

### Websites using reboot-cms

- [wukies.de](https://wukies.de)
- [chesscoin032.com](https://chesscoin032.com)

## Install

Download this repository, [install composer](https://getcomposer.org/download/),
and run `composer.phar install`.

Configure `/web` as the root folder in your webserver.

## Documentation

**In a Nutshell**

- A `Page` is a 
    - `Markdown file` (flat or with blocks) or a 
    - `PHP file` (where you can do everything)
- A `Block` renders a block
- A `Template` renders the `Page`

### Page

Folder: `/content/pages`

A `Page` contains the content of a webpage.  

It can be a **flat Markdown** file, can contain **multiple Blocks** or
also can be a **PHP-File**, where everything is possible.

Pages are auto-routed on web-requests:

- `index.md` or `index.php` will be shown on requesting `/`
- `NAME.md` or `NAME.php` will be shown on requesting `/NAME`
- `FOLDER/index.md` (or .php) will be shown on requesting `/FOLDER`
- `FOLDER/NAME.md` (or .php) will be shown on requesting `/FOLDER/NAME`

Example for a Markdown `Page` with `Blocks`:

``` markdown
---
title: Reboot CMS
author: shaack.com
date: 2021-03-04
---

<!-- jumbotron -->

# Reboot CMS

A flat file, markdown CMS in PHP

--- 

The main idea is, to have a minimal CMS without needing a database, but with the support
of blocks.

[Learn more](/documentation)

<!-- markdown -->

## This is a markdown block

Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod 
tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, 
quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea 
commodo consequat. 
```
This `Page` contains two `Block`s, "jumbotron" and "markdown". It will render to
this:

![](https://shaack.com/projekte/assets/img/reboot-cms-jumbotron.png)

Markdown files without blocks will render to a flat Markdown page, like in every
other flat file CMS.

You can define metadata for the page on top in`YAML Front Matter` syntax.

```
---
title: My new Webpage
description: Lorem ipsum dolor sit amet, consectetur adipisicing elit.
author: shaack.com
date: 2021-03-04
# ... more meta data in YAML, as you like
---
```

### Block

Folder: `/themes/THEME_NAME/blocks`

A `Block` describes how a block is rendered. Blocks are written in PHP.

The code for the "jumbotron" `Block` which was used in the `Page` above,
looks like this:
``` php
<div class="container">
    <div class="jumbotron">

        <h1 class="display-4"><?= $this->query("/h1[1]/text()") ?></h1>
        <p class="lead"><?= $this->query("/p[1]/text()") ?></p>
        <hr class="my-4">
        <?= $this->query("/hr/following-sibling::*") ?>
        <p class="lead">
            <a class="btn btn-primary btn-lg" href="<?= $this->value("/a[1]/@href") ?>"
               role="button"><?= $this->value("/a[1]/text()") ?></a>
        </p>
    </div>
</div>
```

Elements in the markdown are queried and used as values for the block. The query syntax
is [Xpath](https://devhints.io/xpath).

### Template

Folder: `/themes/THEME_NAME`

`Templates` are written in PHP. The `template.php` Template is used, if no other `Template` is defined for a
`Page`.