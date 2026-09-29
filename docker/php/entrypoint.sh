#!/bin/sh
set -e

# First boot on a fresh checkout: install PHP dependencies into the bind mount.
if [ ! -f vendor/autoload.php ]; then
    composer install --no-interaction --prefer-dist
fi

exec "$@"
