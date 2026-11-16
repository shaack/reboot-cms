# Lightbox

Opens linked images enlarged in a dialog over the page, with paging through a group of images. It needs no library, the dialog is the HTML element `<dialog>`.

Lightbox is a site AddOn shipped with Reboot CMS. It has no admin page, no storage and no configuration.

## Enabling

Add it to the `addons` list in `site/config.yml`:

```yaml
addons: [ Lightbox ]
```

## Usage

A link to an image takes part with the attribute `data-lightbox`. Links with the same value form a group:

```html
<a href="/media/large-1.jpg" data-lightbox="screenshots" data-caption="The editor">
    <img src="/media/small-1.jpg" alt="The editor">
</a>
<a href="/media/large-2.jpg" data-lightbox="screenshots" data-caption="Dark mode">
    <img src="/media/small-2.jpg" alt="Dark mode">
</a>
```

The attributes are written by a block template, the gallery blocks of the demo site show how. In plain Markdown a link cannot carry attributes.

## Behaviour

- A click on a link opens its image. With a pressed modifier key, for a new tab for example, the link stays a link.
- The arrow keys, the buttons beside the image and a horizontal swipe page through the group, which starts again at the end. Links that are hidden, one of a light and a dark variant of an image for example, are left out. A counter shows the position. A group of one image has no buttons and no counter.
- `data-caption` is shown below the image and is its alternative text.
- Escape, the close button or a click beside the image end it, the focus returns to the link.
- The labels of the buttons follow the language of the page, English or German.
- Without JavaScript the link simply shows the image.

The AddOn adds its style and script before `</body>` and only to pages with at least one link with `data-lightbox`.

## Colors

The selectors of the AddOn sit in `:where()`, so every rule of the site wins. The colors are variables, set them in the style sheet of the site:

```css
:root {
    --lightbox-backdrop: rgba(0, 0, 0, 0.92);
    --lightbox-color: #fff;                     /* caption and counter */
    --lightbox-button-background: transparent;
    --lightbox-button-color: #fff;
    --lightbox-button-hover-background: #fff;
    --lightbox-button-hover-color: #000;
}
```

The parts of the dialog have the classes `lightbox`, `lightbox-figure`, `lightbox-image`, `lightbox-caption`, `lightbox-counter` and `lightbox-button` with `lightbox-prev`, `lightbox-next` and `lightbox-close`. While it is open, `<html>` has the class `lightbox-open`.

## Files

```
Lightbox.php          the AddOn, adds style and script in postRender()
assets/lightbox.css   the style
assets/lightbox.js    the script
```
