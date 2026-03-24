#!/bin/sh
# =============================================================================
# MediaFlow — Container Entrypoint
#
# Runs before the main process (php-fpm or php artisan horizon) starts.
# Ensures Laravel's writable directories exist and have correct permissions.
#
# Kept deliberately minimal — application setup (migrations, key generation)
# is performed manually via `docker compose exec app php artisan ...` after
# the first `docker compose up`.
# =============================================================================
set -e

# Ensure Laravel's required writable directories exist.
# These may be absent on a fresh clone before storage is initialised.
mkdir -p /var/www/html/storage/framework/sessions \
         /var/www/html/storage/framework/views \
         /var/www/html/storage/framework/cache \
         /var/www/html/storage/logs \
         /var/www/html/bootstrap/cache

# 775: owner and group can write, world can read. Safe for development.
chmod -R 775 /var/www/html/storage \
             /var/www/html/bootstrap/cache

# Hand off to CMD (php-fpm) or the service-level override (php artisan horizon).
exec "$@"
