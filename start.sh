#!/bin/sh
# Start Reboot CMS with PHP's built-in web server
# Requires: PHP 8.0+ with extensions json, fileinfo, dom

PORT=${1:-8080}

if ! command -v php >/dev/null 2>&1; then
    echo "Error: PHP is not installed"
    exit 1
fi

if [ ! -d "vendor" ]; then
    echo "Installing dependencies..."
    composer install --no-dev
fi

echo "Starting Reboot CMS at http://localhost:$PORT"
echo "Admin: http://localhost:$PORT/admin"
echo "Press Ctrl+C to stop"
echo ""
php -S "localhost:$PORT" -t web web/router.php
