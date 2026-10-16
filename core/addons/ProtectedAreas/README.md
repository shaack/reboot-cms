# ProtectedAreas

Puts parts of the site behind a login. A protected area is a path prefix, everything below it is only served to a logged in user whose account lists a matching path. What the pages contain is up to the site, the AddOn only knows paths and accounts. Typical uses are a customer area with one folder per customer, an intranet section or a preview of unpublished pages.

ProtectedAreas is a site AddOn shipped with Reboot CMS. It brings the access check, a login page rendered inside the site template, a logout URL and the admin section "Protected Areas" where accounts are managed.

## Enabling

Add it to the `addons` list in `site/config.yml` and name the protected path prefixes:

```yaml
addons: [ ProtectedAreas ]
protectedAreas:
  areas: [ /customers ]
```

Then create the pages below the area as usual, for example `site/pages/customers/acme/index.md`, and add an account for the customer in the admin under "Protected Areas" with the allowed path `/customers/acme`.

## How access works

A request to a path inside an area is answered in one of three ways:

- The visitor is not logged in. They are redirected to the login page, which returns them to the requested page after the login.
- The visitor is logged in and one of their paths is the requested path or a parent of it. The page is served with `Cache-Control: no-store`, so it never ends up in a shared cache.
- The visitor is logged in but has no matching path. They are sent to the login page with a notice and may log in with another account or log out.

A user with the path `/customers/acme` may open `/customers/acme` and everything below it, but not `/customers` or `/customers/acme-corp`. The path `/` grants access to all areas, useful for your own account. Pages outside the areas are public as before, and the AddOn does nothing on a site without configured areas.

On multilingual sites the paths are compared without the language prefix, so `/de/customers/acme` is protected like `/customers/acme`. The login page exists once per language, `/de/login` speaks German.

## Login and logout

The login page is provided by the AddOn and rendered through the site template like a page in `site/pages/`, so it looks like the rest of the site. Its markup uses the classes `protected-areas-login`, `protected-areas-error`, `protected-areas-notice`, `protected-areas-button`, `protected-areas-link`, `protected-areas-link-form` and `protected-areas-password-form` for styling. The texts ship in English and German and follow the request language.

The logout URL ends the login and returns to the homepage. Both paths can be changed:

```yaml
protectedAreas:
  areas: [ /customers ]
  loginPath: /login
  logoutPath: /logout
  texts:
    de:
      title: "Kundenbereich"
      intro: "Bitte melden Sie sich mit den Zugangsdaten aus unserer E-Mail an."
```

The keys that can be overridden per language are `title`, `intro`, `emailLabel`, `passwordLabel`, `submit`, `wrongCredentials`, `tooManyAttempts`, `denied`, `loggedInAs`, `continue`, `logout`, `linkIntro`, `linkSubmit`, `linkSent`, `linkInvalid`, `orPassword`, `mailSubject` and `mailBody`. The mail texts are `sprintf()` patterns, `mailSubject` takes the site name, `mailBody` the site name, the URL and the lifetime in minutes.

## Login links by mail

If the site can send mail (section `mail` in `local/config.yml`, see the main README), the login page asks for the e-mail address only and sends a link that logs the visitor in. No password needed. The link is valid for 15 minutes and works once, afterwards the visitor stays logged in for 30 days through a cookie, also across browser restarts. The password login is folded away below the link form, for accounts that have a password. The site name in the mail is the `brand` of the `navbar` section or `title` of `site/config.yml`.

The answer to the form is the same whether an account exists for the address or not, so the form cannot be used to find out which addresses have access. Link requests are limited to five per IP address and 15 minutes, separately from failed password logins. The link is built from the host of the request, set `baseUrl` if the site is reached through a proxy under another name:

```yaml
protectedAreas:
  areas: [ /customers ]
  loginLinks: true          # false switches the link form off
  baseUrl: https://example.com
```

The admin page "Protected Areas" has a button "Send login link" per account, for the first contact with a customer. Without a mail configuration the button and the form are not shown and the admin page says why.

The 30 day login is a token in the cookie `protected_areas_remember`. Only a hash of it is stored, every token is good for one use and replaced on the next visit, and the cookie is marked `Secure` on HTTPS. Logging out, changing the password or deleting the account forgets the tokens of that account.

To show the login state in the site template, ask the AddOn:

```php
<?php if (isset($site->getAddOns()['ProtectedAreas'])) {
    $protectedAreas = $site->getAddOn('ProtectedAreas');
    if ($user = $protectedAreas->getUser()) { ?>
        <a href="<?= $site->getWebPath() . $protectedAreas->getLogoutPath($request) ?>">Logout (<?= htmlspecialchars($user) ?>)</a>
    <?php } else { ?>
        <a href="<?= $site->getWebPath() . $protectedAreas->getLoginPath($request) ?>">Login</a>
    <?php }
} ?>
```

## Accounts

Accounts are managed in the admin under "Protected Areas": e-mail address, the list of allowed paths and optionally a password. There is no self registration and no password reset by mail. The e-mail address is the account name. On a site that sends mail the password can be left empty, the account then logs in by link only, which is the simplest way for customers. A password can be set or removed later. Without a mail configuration every account needs a password. It is stored in lower case and the login does not care about case, so `Alice@Example.com` and `alice@example.com` are the same account. Passwords need at least eight characters and are stored as `password_hash()` hashes.

Five failed logins from one IP address within 15 minutes block further attempts from that address until the window has passed. A successful login clears the counter.

## Storage

```
local/protected-areas/users.json            accounts, keyed by e-mail address
local/protected-areas/login-attempts.json   failed logins for the throttle
local/protected-areas/link-requests.json    login link requests for the throttle
local/protected-areas/login-links.json      hashes of the login link tokens, 15 minutes
local/protected-areas/remember-tokens.json  hashes of the 30 day login tokens
```

All files live in `local/`, outside the web root and protected by `local/.htaccess`.

## Limits

The AddOn protects pages, not files. An image or PDF in `web/media/` referenced from a protected page is public like any other file in the web root. Use the [SecureDownloads](../SecureDownloads/README.md) AddOn for files that must stay private.

The login shares the PHP session with the admin. Logging out of the admin in the same browser therefore ends a customer login as well, which only matters while testing.

## Files

```
ProtectedAreas.php      the AddOn: access check, login links and logout in preRender(), login API
src/AccessControl.php   the path logic, static and without side effects
src/UserStore.php       accounts in users.json with locking
src/LoginThrottle.php   the limit on failed logins and link requests, one counter file each
pages/login.php         the login page, mounted at the login path of every language
admin/index.php         the admin page, mounted at /admin/protectedareas
```
