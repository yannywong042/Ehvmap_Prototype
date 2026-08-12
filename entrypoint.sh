#!/bin/bash
set -e

# Ensure required Laravel storage directories exist with correct permissions
mkdir -p /var/www/html/storage/framework/{sessions,views,cache/data}
mkdir -p /var/www/html/bootstrap/cache
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

exec "$@"
