# LanguageRedirect

Sends visitors of a multilingual site to the language version that matches their preference. It does nothing unless the site is multilingual, that is two or more `languages` are configured.

LanguageRedirect is a site AddOn shipped with Reboot CMS. It has no admin page and no storage.

## Enabling

Add it to the `addons` list in `site/config.yml`:

```yaml
languages: [ en, de ]
defaultLanguage: en
addons: [ LanguageRedirect ]
```

## Behaviour

On the homepage the visitor is redirected to their preferred language. An explicit choice stored in the `lang` cookie wins. Without a cookie the browser's `Accept-Language` header decides, taking the quality values into account and matching `de-DE` to `de`. A visitor whose preference is the default language is not redirected.

The decision is taken on every visit, so a visitor whose browser prefers a non-default language consistently lands on that language's homepage. Deep links to a page in any language are served as requested.

## Redirects

The redirects under `redirects` in `site/config.yml` follow the preference too. With German preferred and

```yaml
redirects:
  /microtask: /products/microtask
```

`/microtask` leads to `/de/products/microtask`, everybody else gets the permanent redirect to `/products/microtask`. The redirect to a language is temporary (302), because its target depends on the visitor. An old path with a language prefix, a new path that names its language and a full URL as target are left to the plain redirect.

## Language switcher

The `lang` cookie only ever records an explicit choice. That is what keeps the default language reachable. If the cookie were set from the browser preference, a German visitor could never reach the English homepage, every click on "English" would bounce back.

A language switcher therefore links to the current page in the other language and appends the marker `?setlang=xx`:

```php
$href = $site->getWebPath() . '/de' . $request->getPathWithoutLanguage() . '?setlang=de';
```

When the AddOn sees the marker, it stores the choice in the cookie and redirects to the same page without the marker. Other query parameters survive the redirect. The cookie lives one year, is sent for the whole site and is not readable from JavaScript.

## Files

```
LanguageRedirect.php   the AddOn, everything happens in preRender()
```

See the section "Multilingual sites" in the main README for the navigation and template side of a multilingual site.
