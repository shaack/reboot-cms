# Downloads

Deprecated alias for [SecureDownloads](../SecureDownloads/README.md). The AddOn was renamed, this folder only keeps sites working that still list `Downloads` in `site/config.yml`. They get the same AddOn with the admin page under `/admin/downloads`, and the health page shows a hint.

Replace the entry by `SecureDownloads`:

```yaml
addons: [ SecureDownloads ]
```

The configuration key `downloads:` is still read, the current key is `secureDownloads:`. Stored files in `local/downloads/` and the link prefix `/downloads` are unchanged, so existing links keep working after the switch.
