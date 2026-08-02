#!/bin/sh
# Start Reboot CMS locally in a Podman container running Apache + PHP.
#
# This mirrors production (Apache with mod_rewrite/mod_headers/mod_expires reading
# the .htaccess files). PHP's built-in server ignores .htaccess, so caching and
# rewrite behaviour could not be tested with it.
#
# Port 21007 is this project's HTTP port from ~/Shared/Work/PORTS.md (own project 07).
# Nothing is installed on the host; the image is pulled by Podman on first run.

set -e

PORT="${1:-21007}"
ROOT="$(cd "$(dirname "$0")" && pwd)"
IMAGE="php:8.3-apache"
NAME="reboot-cms"

if ! command -v podman >/dev/null 2>&1; then
    echo "Error: podman is not installed" >&2
    exit 1
fi

echo "Starting Reboot CMS (Apache + PHP in Podman) at http://localhost:$PORT"
echo "Admin: http://localhost:$PORT/admin"
echo "Press Ctrl+C to stop"
echo ""

# DocumentRoot points at web/ (the app's real docroot), so URLs are clean (/admin/,
# not /web/admin/) and the per-directory .htaccess files apply exactly as intended.
# The project root is still mounted so the app can reach vendor/, core/, site/ and
# local/ one level up. Apache workers run as root so the container (rootless Podman
# maps container-root to the host user) can write local/, site/ and web/media/.
exec podman run --rm --replace --name "$NAME" \
    -p "$PORT:80" \
    -v "$ROOT:/var/www/html" \
    "$IMAGE" \
    bash -c '
        set -e
        a2enmod rewrite headers expires >/dev/null
        sed -ri "s/^(export APACHE_RUN_USER)=.*/\1=root/; s/^(export APACHE_RUN_GROUP)=.*/\1=root/" /etc/apache2/envvars
        sed -ri "s#DocumentRoot /var/www/html#DocumentRoot /var/www/html/web#" /etc/apache2/sites-available/000-default.conf
        printf "<Directory /var/www/html/web>\n    AllowOverride All\n    Require all granted\n</Directory>\n" \
            > /etc/apache2/conf-available/reboot-cms.conf
        a2enconf reboot-cms >/dev/null
        exec apache2-foreground
    '
