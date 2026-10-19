#!/bin/sh
# Renews the screenshots in web/media/screenshots/, see docs/screenshots.md.
#
# Starts a throwaway Podman container with Apache and PHP on port 26007 that
# works on copies of local/, site/ and web/media/, fills them with sample
# content (docs/screenshot-seed.php) and lets Puppeteer take the pictures
# (docs/capture-screenshots.mjs). Your own local/ with accounts and settings is
# never touched. The admin password exists only for this run.
#
# Puppeteer runs twice. The second run picks up the new screenshots, which the
# demo shows itself, in the gallery and in the enlarged view.

set -e

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
PORT="${PORT:-26007}"
HOST="${SITE_HOST:-www.my-site.test}"
NAME="reboot-cms-screenshots"
IMAGE="php:8.3-apache"
TMP="$(mktemp -d)"
PASSWORD="$(LC_ALL=C tr -dc 'A-Za-z0-9' < /dev/urandom | head -c 24)"

cleanup() {
    podman stop "$NAME" >/dev/null 2>&1 || true
    rm -rf "$TMP"
}
trap cleanup EXIT INT TERM

if ! command -v podman >/dev/null 2>&1 || ! command -v node >/dev/null 2>&1; then
    echo "Error: podman and node are needed" >&2
    exit 1
fi

# The shipped local/ from Git, the site and the media as they are now
cd "$ROOT"
git archive HEAD local | tar -x -C "$TMP"
cp -R site "$TMP/site"
cp -R web/media "$TMP/media"

podman run -d --rm --replace --name "$NAME" \
    -p "$PORT:80" \
    -v "$ROOT:/var/www/html" \
    -v "$TMP/local:/var/www/html/local" \
    -v "$TMP/site:/var/www/html/site" \
    -v "$TMP/media:/var/www/html/web/media" \
    "$IMAGE" \
    bash -c '
        a2enmod rewrite headers expires >/dev/null
        sed -ri "s/^(export APACHE_RUN_USER)=.*/\1=root/; s/^(export APACHE_RUN_GROUP)=.*/\1=root/" /etc/apache2/envvars
        sed -ri "s#DocumentRoot /var/www/html#DocumentRoot /var/www/html/web#" /etc/apache2/sites-available/000-default.conf
        printf "<Directory /var/www/html/web>\n    AllowOverride All\n    Require all granted\n</Directory>\n" \
            > /etc/apache2/conf-available/reboot-cms.conf
        a2enconf reboot-cms >/dev/null
        exec apache2-foreground
    ' >/dev/null

printf "Waiting for the container"
for i in $(seq 1 30); do
    if curl -s -o /dev/null "http://localhost:$PORT/"; then break; fi
    printf "."
    sleep 1
done
echo

podman exec -e ADMIN_USER=admin -e ADMIN_PASSWORD="$PASSWORD" -e SITE_HOST="$HOST" "$NAME" \
    php /var/www/html/docs/screenshot-seed.php

capture() {
    BASE="http://$HOST" HOST_RULES="MAP $HOST 127.0.0.1:$PORT" \
        ADMIN_USER=admin ADMIN_PASSWORD="$PASSWORD" \
        NODE_PATH="${NODE_PATH:-$(npm root -g)}" node "$ROOT/docs/capture-screenshots.mjs"
}
capture
cp "$ROOT"/web/media/screenshots/*.png "$TMP/media/screenshots/"
echo
echo "Second run, now with the new screenshots inside the demo"
capture
