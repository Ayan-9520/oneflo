#!/bin/sh
set -eu

root=$(CDPATH= cd -- "$(dirname "$0")/.." && pwd)
cd "$root"
mkdir -p backups
docker compose -f docker-compose.prod.yml exec -T app php artisan backup:database --path=/var/www/html/storage/app/private/backups
latest=$(docker compose -f docker-compose.prod.yml exec -T app sh -c 'ls -1t /var/www/html/storage/app/private/backups/oneflo-*.sql | head -n 1')
latest=$(printf '%s' "$latest" | tr -d '\r')
docker compose -f docker-compose.prod.yml cp "app:${latest}" "backups/$(basename "$latest")"
echo "backups/$(basename "$latest")"
