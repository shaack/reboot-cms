#!/bin/sh
# Start Reboot CMS locally in a Podman container running Apache + PHP, plus a
# Mailpit container that catches every mail the CMS sends.
#
# This mirrors production (Apache with mod_rewrite/mod_headers/mod_expires reading
# the .htaccess files). PHP's built-in server ignores .htaccess, so caching and
# rewrite behaviour could not be tested with it.
#
# Ports from ~/Shared/Work/PORTS.md (own project 07): 21007 HTTP, 24007 SMTP,
# 25007 the Mailpit web UI. Nothing is installed on the host; the images are
# pulled by Podman on first run.
#
# On the first start a local/config.local.yml is written that points the CMS at
# Mailpit and raises the log level. That file is for this machine only and is
# ignored by Git, the shipped local/config.yml stays untouched.

set -e

PORT="${1:-21007}"
MAIL_PORT="${2:-24007}"
MAIL_UI_PORT="${3:-25007}"
ROOT="$(cd "$(dirname "$0")" && pwd)"
IMAGE="php:8.3-apache"
MAIL_IMAGE="docker.io/axllent/mailpit"
NAME="reboot-cms"
MAIL_NAME="reboot-cms-mail"
LOCAL_CONFIG="$ROOT/local/config.local.yml"

if ! command -v podman >/dev/null 2>&1; then
    echo "Error: podman is not installed" >&2
    exit 1
fi

if [ ! -f "$LOCAL_CONFIG" ]; then
    cat > "$LOCAL_CONFIG" <<YAML
# Settings for this machine only, laid over local/config.yml. Not in Git.
# Written by run.sh, edit or delete as you like.

logLevel: 1

# Mail goes to the Mailpit container started by run.sh, read it at http://localhost:$MAIL_UI_PORT
mail:
  transport: smtp
  host: host.containers.internal
  port: $MAIL_PORT
  encryption: none
  from: noreply@reboot.test
  fromName: "Reboot CMS"
YAML
    echo "Created local/config.local.yml (log level 1, mail to Mailpit)"
fi

echo "Starting Reboot CMS (Apache + PHP in Podman) at http://localhost:$PORT"
echo "Admin: http://localhost:$PORT/admin"
echo "Mail:  http://localhost:$MAIL_UI_PORT (Mailpit, SMTP on $MAIL_PORT)"
echo "Press Ctrl+C to stop"
echo ""

# Mailpit catches every mail the CMS sends. The CMS reaches it on the host's
# published port, see local/config.local.yml.
MAIL_ID="$(podman run -d --rm --replace --name "$MAIL_NAME" \
    -p "$MAIL_PORT:1025" -p "$MAIL_UI_PORT:8025" \
    "$MAIL_IMAGE")"

# Stop Mailpit when this script ends, but only the instance this script started.
# A second run.sh replaces the containers of the first one, and the first must
# not take the new Mailpit down with it on its way out.
cleanup() {
    podman stop "$NAME" >/dev/null 2>&1 || true
    current="$(podman inspect --format '{{.Id}}' "$MAIL_NAME" 2>/dev/null || true)"
    if [ -n "$current" ] && [ "$current" = "$MAIL_ID" ]; then
        podman stop "$MAIL_NAME" >/dev/null 2>&1 || true
    fi
}
trap cleanup EXIT INT TERM

# DocumentRoot points at web/ (the app's real docroot), so URLs are clean (/admin/,
# not /web/admin/) and the per-directory .htaccess files apply exactly as intended.
# The project root is still mounted so the app can reach vendor/, core/, site/ and
# local/ one level up. Apache workers run as root so the container (rootless Podman
# maps container-root to the host user) can write local/, site/ and web/media/.
# Started detached, with only the log in the foreground. Attached, Podman would
# forward every signal to the container, SIGWINCH included, which the terminal
# sends to the foreground process on each window or pane resize. Apache reads
# SIGWINCH as "graceful stop" and would shut down.
podman run -d --rm --replace --name "$NAME" \
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
    ' >/dev/null

# The log in the foreground, so Ctrl+C ends the script as before. The trap above
# stops both containers.
podman logs -f "$NAME"
