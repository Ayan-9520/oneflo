#!/bin/sh
set -eu

cd /var/www/html

mkdir -p \
  storage/app/private/documents \
  storage/app/private/backups \
  storage/framework/cache/data \
  storage/framework/sessions \
  storage/framework/views \
  storage/logs \
  bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

if [ -z "${APP_KEY:-}" ]; then
  echo "APP_KEY is required" >&2
  exit 1
fi

php <<'PHP'
<?php
$host = getenv('DB_HOST') ?: 'mysql';
$port = getenv('DB_PORT') ?: '3306';
$database = getenv('DB_DATABASE') ?: 'oneflo';
$username = getenv('DB_USERNAME') ?: 'oneflo';
$password = getenv('DB_PASSWORD') ?: '';

for ($attempt = 1; $attempt <= 30; $attempt++) {
    try {
        new PDO(
            sprintf('mysql:host=%s;port=%s;dbname=%s', $host, $port, $database),
            $username,
            $password,
            [PDO::ATTR_TIMEOUT => 3]
        );
        fwrite(STDOUT, "database is ready\n");
        exit(0);
    } catch (Throwable $exception) {
        fwrite(STDERR, "waiting for database ({$attempt}/30)\n");
        sleep(2);
    }
}

fwrite(STDERR, "database did not become ready\n");
exit(1);
PHP

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
  php artisan migrate --force --no-interaction
  php artisan db:seed --class=Database\\Seeders\\RoleAndPermissionSeeder --force --no-interaction
fi

if [ "${SEED_INITIAL:-false}" = "true" ]; then
  php artisan db:seed --class=Database\\Seeders\\InitialOrganizationSeeder --force --no-interaction
fi

exec "$@"
