# EmailObfuscation

Hides e-mail addresses from harvesters. After a page has been rendered, every `mailto:` link and every address in the visible text is replaced by a small inline script that writes the original markup back into the page. Visitors see the address as before, a crawler that does not run JavaScript sees only base64.

EmailObfuscation is a site AddOn shipped with Reboot CMS. It has no admin page, no storage and no configuration.

## Enabling

Add it to the `addons` list in `site/config.yml`:

```yaml
addons: [ EmailObfuscation ]
```

## Behaviour

The AddOn works in the `postRender()` hook, so it covers everything the template outputs, including addresses from blocks, the footer and Markdown content. Two passes run over the finished HTML.

First every `mailto:` link is replaced as a whole, label included, by a script that writes a clean `<a href="mailto:…">label</a>`. Other attributes of the original link such as `class` or `title` are dropped. Then every address that remains in the visible text is replaced by a script writing just the address. The text is checked with the markup stripped, so an address that occurs only inside an attribute is left alone.

The replacement looks like this:

```html
<script>document.write(atob('aW5mb0BleGFtcGxlLmNvbQ=='));</script>
```

Visitors without JavaScript do not see the addresses. Place one address in an image or write it out as "info at example dot com" if that matters for your site.

## Files

```
EmailObfuscation.php     the AddOn, calls the obfuscator in postRender()
src/EmailObfuscator.php  the replacement logic, pure functions
```
