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

The login page is provided by the AddOn and rendered through the site template like a page in `site/pages/`, so it looks like the rest of the site. Its markup uses the classes `protected-areas-login`, `protected-areas-error`, `protected-areas-button` and `protected-areas-link` for styling. The texts ship in English and German and follow the request language.

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

The keys that can be overridden per language are `title`, `intro`, `emailLabel`, `passwordLabel`, `submit`, `wrongCredentials`, `tooManyAttempts`, `denied`, `loggedInAs`, `continue` and `logout`.

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

Accounts are managed in the admin under "Protected Areas": e-mail address, password and the list of allowed paths. There is no self registration and no password reset by mail, the site owner hands out the credentials. The e-mail address is the account name. It is stored in lower case and the login does not care about case, so `Alice@Example.com` and `alice@example.com` are the same account. Passwords need at least eight characters and are stored as `password_hash()` hashes.

Five failed logins from one IP address within 15 minutes block further attempts from that address until the window has passed. A successful login clears the counter.

## Storage

```
local/protected-areas/users.json           accounts, keyed by e-mail address
local/protected-areas/login-attempts.json  failed logins for the throttle
```

Both files live in `local/`, outside the web root and protected by `local/.htaccess`.

## Limits

The AddOn protects pages, not files. An image or PDF in `web/media/` referenced from a protected page is public like any other file in the web root. Use the [SecureDownloads](../SecureDownloads/README.md) AddOn for files that must stay private.

The login shares the PHP session with the admin. Logging out of the admin in the same browser therefore ends a customer login as well, which only matters while testing.

## Files

```
ProtectedAreas.php      the AddOn: access check and logout in preRender(), login API
src/AccessControl.php   the path logic, static and without side effects
src/UserStore.php       accounts in users.json with locking
src/LoginThrottle.php   the limit on failed logins
pages/login.php         the login page, mounted at the login path of every language
admin/index.php         the admin page, mounted at /admin/protectedareas
```
