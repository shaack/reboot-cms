#!/bin/sh
# Copies the files the admin loads in the browser from node_modules/ into
# vendor/, which is committed. node_modules/ is not: npm installs complete
# packages (13 MB), the browser needs about 600 KB of them.
#
# Run after `npm install` or `npm update` (npm runs it on install as well):
#
#     cd web/admin && npm install
#
# The paths below are kept as they are in the packages, so a reference in
# core/admin/template.php only changes node_modules/ to vendor/.

set -e
cd "$(dirname "$0")"

FILES="
bootstrap/LICENSE
bootstrap/dist/css/bootstrap.min.css
bootstrap/dist/js/bootstrap.bundle.min.js
bootstrap-auto-dark-mode/LICENSE
bootstrap-auto-dark-mode/src/bootstrap-auto-dark-mode.js
bootstrap-show-modal/LICENSE
bootstrap-show-modal/src/ShowModal.js
bootstrap-show-toast/LICENSE
bootstrap-show-toast/src/bootstrap-show-toast.js
cm-md-editor/LICENSE
cm-md-editor/src
"

rm -rf vendor
for path in $FILES; do
    if [ ! -e "node_modules/$path" ]; then
        echo "missing: node_modules/$path, run npm install first" >&2
        exit 1
    fi
    mkdir -p "vendor/$(dirname "$path")"
    cp -R "node_modules/$path" "vendor/$path"
done
echo "vendor/ rebuilt, $(du -sh vendor | cut -f1)"
