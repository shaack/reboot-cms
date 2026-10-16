# ExampleAddOn

A minimal AddOn that shows the three lifecycle hooks. It logs `init()`, `preRender()` and `postRender()` and appends an HTML comment to every rendered page.

It is part of the example site and registered in `site/config.yml`:

```yaml
addons: [ ExampleAddOn ]
```

Remove it from the list, or delete the folder, when you set up your own site.

## Layout

An AddOn is a folder named after its class. This one needs only the class file. Helper classes go into `src/` and are autoloaded under `Shaack\Reboot\AddOns\ExampleAddOn\`, admin pages go into `admin/` and are mounted at `/admin/exampleaddon`.

```
ExampleAddOn.php   the AddOn, namespace Shaack\Reboot\AddOns
src/               optional helper classes
admin/             optional admin pages
```

See the section "AddOns" in the main README for the full description of the hooks, and `core/addons/SecureDownloads/` for a complete AddOn with a helper class and an admin page.
