#!/bin/sh
set -eu

if [ "${CONFIRM:-}" != "yes" ]; then
  echo "Set CONFIRM=yes to replace the production database." >&2
  exit 1
fi

if [ "$#" -ne 1 ]; then
  echo "Usage: CONFIRM=yes scripts/restore.sh backups/oneflo-YYYYmmdd-HHMMSS.sql" >&2
  exit 1
fi

file=$1
case "$file" in
  *public*|*../../*)
    echo "Refusing that backup path." >&2
    exit 1
    ;;
esac

if [ ! -f "$file" ]; then
  echo "Backup file not found." >&2
  exit 1
fi

root=$(CDPATH= cd -- "$(dirname "$0")/.." && pwd)
cd "$root"
name=$(basename "$file")
docker compose -f docker-compose.prod.yml cp "$file" "app:/var/www/html/storage/app/private/backups/${name}"
docker compose -f docker-compose.prod.yml exec -T app php artisan backup:restore "/var/www/html/storage/app/private/backups/${name}" --confirm
