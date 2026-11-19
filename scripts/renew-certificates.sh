#!/bin/sh
set -eu

root=$(CDPATH= cd -- "$(dirname "$0")/.." && pwd)
cd "$root"
docker compose -f docker-compose.prod.yml --profile tools run --rm --no-deps certbot renew --webroot -w /var/www/certbot
docker compose -f docker-compose.prod.yml exec -T nginx nginx -s reload
