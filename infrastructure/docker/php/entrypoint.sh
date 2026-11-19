#!/bin/sh
set -eu

cd /var/www/html

mkdir -p \
  storage/app/private/documents \
  storage/app/public \
  storage/framework/cache/data \
  storage/framework/sessions \
  storage/framework/views \
  storage/logs \
  bootstrap/cache

chmod -R a+rwX storage bootstrap/cache

if [ ! -f vendor/autoload.php ]; then
  composer install --no-interaction --prefer-dist --no-progress
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

if [ ! -f .env ]; then
  cp .env.example .env
fi

if ! grep -q '^APP_KEY=base64:' .env; then
  php artisan key:generate --force --no-interaction
fi

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
  php artisan migrate --force --no-interaction
fi

exec "$@"
