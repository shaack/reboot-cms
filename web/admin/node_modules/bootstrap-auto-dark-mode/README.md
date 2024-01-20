# bootstrap-auto-dark-mode

This script adds the missing auto-dark mode to the new theme feature of Bootstrap 5.3.

It switches the Bootstrap theme automatically between light and dark mode, depending on the system settings, if you set the `data-bs-theme` attribute of your &lt;html> to `auto`.

## Usage

Use it by just including `bootstrap-auto-dark-mode.js` in your header and setting `data-bs-theme` to `auto`.

```html
    <html lang="en" data-bs-theme="auto">
        <head>
            <!-- you code -->
            <script src="./src/bootstrap-auto-dark-mode.js"></script>
        </head>
        <!-- your code -->
        …
```
