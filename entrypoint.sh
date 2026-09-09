#!/usr/bin/env sh
set -e

cd /var/www

# Initialise the persistent storage volume on first run.
if [ -z "$(ls -A storage 2>/dev/null)" ]; then
    cp -r /opt/storage-skel/. storage/
fi

chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# Generate APP_KEY when not provided via environment.
# Persist it in the storage volume (storage/.appkey) so it survives
# container restarts; write it into a minimal .env for artisan.
if [ -z "$APP_KEY" ]; then
    if [ -f storage/.appkey ]; then
        APP_KEY=$(cat storage/.appkey)
    else
        APP_KEY=$(php artisan key:generate --show --force)
        printf '%s' "$APP_KEY" > storage/.appkey
    fi
    export APP_KEY
    test -f .env || touch .env
    if grep -q '^APP_KEY=' .env; then
        sed -i "s#^APP_KEY=.*#APP_KEY=$APP_KEY#" .env
    else
        printf 'APP_KEY=%s\n' "$APP_KEY" >> .env
    fi
fi

# Ensure the public/storage symlink exists.
php artisan storage:link || true

# Production optimisations (config / route / view cache).
php artisan optimize

# Run migrations only when explicitly enabled.
if [ "$RUN_MIGRATION" = "true" ]; then
    attempt=1
    until php artisan migrate --force; do
        if [ "$attempt" -ge 5 ]; then
            echo "Migrations failed after 5 attempts."
            exit 1
        fi
        echo "Migration attempt $attempt failed, retrying in 5s..."
        attempt=$((attempt + 1))
        sleep 5
    done
fi

exec "$@"
