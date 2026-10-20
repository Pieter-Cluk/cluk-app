#!/usr/bin/env bash
# Runs ON the one.com server after the new build has been uploaded.
# Usage: server-install.sh <webroot> <build.tgz>
# Keeps the server's .env and storage between versions, switches over in one move,
# runs the database migrations and warms the caches. If anything fails, the old
# version stays online.
set -euo pipefail

WEBROOT="$1"
BUILD="$2"
APP="$WEBROOT/app"
NEW="$WEBROOT/app.new"
OLD="$WEBROOT/app.old"
SHARED="$WEBROOT/shared"

mkdir -p "$SHARED"
rm -rf "$NEW" "$OLD"
mkdir -p "$NEW"
tar -xzf "$BUILD" -C "$NEW"
rm -f "$BUILD"

# Persistent storage (uploads, logs, sessions) lives in shared/ and is linked in.
if [ ! -d "$SHARED/storage" ]; then
    mkdir -p "$SHARED/storage"
    cp -R "$NEW/storage/." "$SHARED/storage/"
fi
mkdir -p "$SHARED/storage/framework/cache/data" "$SHARED/storage/framework/sessions" \
         "$SHARED/storage/framework/views" "$SHARED/storage/logs" "$SHARED/storage/app/public"
rm -rf "$NEW/storage"
ln -s "$SHARED/storage" "$NEW/storage"

# Server settings: .env is written by the deploy step into shared/.env.new.
# The APP_KEY is created once on the server and kept forever after.
if [ -f "$SHARED/.env" ] && grep -q '^APP_KEY=base64:' "$SHARED/.env"; then
    KEY_LINE=$(grep '^APP_KEY=' "$SHARED/.env")
else
    KEY_LINE="APP_KEY=base64:$(php -r 'echo base64_encode(random_bytes(32));')"
fi
grep -v '^APP_KEY=' "$SHARED/.env.new" > "$SHARED/.env"
echo "$KEY_LINE" >> "$SHARED/.env"
rm -f "$SHARED/.env.new"
chmod 600 "$SHARED/.env"
cp "$SHARED/.env" "$NEW/.env"

cd "$NEW"
php artisan migrate --force --no-interaction
php artisan cluk:first-install --no-interaction

# Switch over, then build the caches in the final location (they hold paths).
cd "$WEBROOT"
if [ -d "$APP" ]; then mv "$APP" "$OLD"; fi
mv "$NEW" "$APP"
cp "$APP/deploy/htaccess-root" "$WEBROOT/.htaccess"
cd "$APP"
php artisan optimize:clear >/dev/null
php artisan config:cache
php artisan route:cache
php artisan view:cache
rm -rf "$OLD"

echo "Deployed $(cat "$APP/REVISION" 2>/dev/null || echo unknown) to $WEBROOT"
