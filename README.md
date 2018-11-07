# reboot

Reboot CMS, a Bootstrap concentric, flat file, markdown CMS.

Inspired by [Pico](http://picocms.org) and [Redaxo](https://redaxo.org/)

Main idea is, to have a minimal CMS without needing a database like Pico, which has a
module structure, so it allows to build the page out of slices like Redaxo does.

The default example uses Bootstrap and it was created with using Bootstrap in mind but 
it will also work without Bootstrap.

## Install

Install composer and run `./composer.phar install`

## File Structure

### /core

Don`t modify files in this folder, its the Reboot core.

### /vendor

Composers install dir for external dependencies. For now:

- [Parsedown Markdown Parser](http://parsedown.org/)

### /local

Put all modifications here.

#### /local/pages

Your page structure. Files created here are created in Markdown or PHP and redered on request.

`index.md` or `index.php` will be shown on requesting `/`
`NAME.md` or `NAME.php` will be shown on requesting `/NAME`
`FOLDER/NAME.md` will be shown on requesting `/FOLDER/NAME`

To hide pages, name them with underscore, like _footer.md, _navigation.php

The default _navigation.php renders a navigation from the existing files in `/local/pages`.
If you just want to hide a file for the main navigation, prefix it with `#`, like `#legal.php`

#### /local/modules

#### /local/templates

## ToDos

- render with template
- parse yaml configuration in articles
- make navbar module working

