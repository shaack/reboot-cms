# Downloads

Hands out single files through cryptic, short lived links that are additionally protected by a six digit password. It is meant for sending a document to one recipient, not for public files. Those belong in "Media".

Downloads is a site AddOn shipped with Reboot CMS. It brings the delivery of the files, the admin section "Downloads" where files are uploaded, and the storage behind both.

## Enabling

Add it to the `addons` list in `site/config.yml`:

```yaml
addons: [ Downloads ]
```

This activates the download links and mounts the admin page under "Downloads" in the admin navigation. Without the AddOn there is neither a menu entry nor an admin page, and existing links answer with 404.

## Usage

Upload a file in the admin under "Downloads". Reboot CMS creates a link and a password:

```
https://example.com/downloads/3ef938314c4d6cfe.pdf
Password: 692072
```

Send the link by mail and the password through another channel, then neither message alone is enough to open the file. The optional note entered on upload is shown to the recipient above the password form, so they know what they are about to download without the file name being revealed.

The real file name never appears in the link. The extension is cosmetic, the name is only sent in the `Content-Disposition` header, so the recipient still saves the file under its proper name.

The admin list shows for every download whether it was fetched already, when, and from which anonymized IP address. The password stays readable there, which is why it is stored in plain text next to the file.

## Lifetime

A link expires one week after the upload, or one hour after the first download, whichever comes first. Within that hour the file may be fetched again, so an interrupted transfer does not ruin the link. Ten wrong passwords lock a link for good, which is what keeps six digits from being guessable.

After expiry the file is deleted from the server and the list entry stays for another 30 days, marked as expired, so the log remains readable. There is no cron job. The cleanup runs whenever a download link is opened or the admin page is visited. An expired link is refused regardless, so the delay until the cleanup does not expose anything.

## Configuration

The defaults need no configuration. All values can be changed in `site/config.yml`:

```yaml
downloads:
  path: /downloads        # URL prefix of the links
  linkLifetime: 604800    # seconds a fresh link stays valid (one week)
  downloadWindow: 3600    # seconds after the first download (one hour)
  logRetention: 2592000   # seconds an expired entry stays in the list (30 days)
  texts:                  # override the built-in texts, per language
    de:
      title: "Datei herunterladen"
      intro: "Bitte geben Sie das Passwort ein, das Sie per SMS erhalten haben."
```

The download page is self contained and does not use the site template, so a link looks the same in every project. Its texts ship in English and German and follow the request language on multilingual sites, so `/de/downloads/3ef938314c4d6cfe.pdf` speaks German. The keys that can be overridden per language are `title`, `intro`, `passwordLabel`, `submit`, `wrongPassword`, `attemptsLeft`, `notFoundTitle`, `notFound`, `lockedTitle`, `locked` and `downloadStarted`.

The upload size is limited by PHP, not by Reboot CMS. The current limits are shown next to the upload field. Raise `upload_max_filesize` and `post_max_size` in `php.ini` for larger files.

## Storage

Files live in `local/downloads/`, outside the web root and behind `local/.htaccess`, so nothing is ever served by the web server directly. Only this AddOn delivers a file, and only after the password was accepted.

```
local/downloads/index.json     metadata of all downloads, keyed by token
local/downloads/files/<token>  the payload, stored without an extension
```

## Files

```
Downloads.php          the AddOn: answers the download links in preRender()
src/DownloadStore.php  storage, passwords, expiry and cleanup
admin/index.php        the admin page, mounted at /admin/downloads
```

The store is independent of the request and can be used from scripts or tests: `new DownloadStore($baseFsPath, $config)`.
