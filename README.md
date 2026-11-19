# OneFlo

One platform for every business and every employee. This repository is the foundation: a React application, a Laravel API, and the local Docker stack. It does not yet include HR, payroll, or authentication screens.

## Structure

```text
apps/frontend          React, TypeScript, Vite
apps/backend           Laravel API
infrastructure/docker  PHP and frontend images
infrastructure/nginx   Nginx site config
docs                   Audit and roadmap
scripts                Local start and test commands
.github/workflows      CI
```

## Install and start

From the repository root:

```powershell
Copy-Item .env.example .env
docker compose up --build
```

`scripts/start.ps1` does the same thing.

That command starts:

| Service | Address |
| --- | --- |
| Web app | http://localhost:5173 |
| API | http://localhost:8080 |
| MySQL | localhost:3306 |
| Redis | localhost:6379 |

The API container runs migrations on startup. MySQL data is kept in the `mysql_data` volume. Redis data is kept in the `redis_data` volume.

## Day-to-day commands

Run these from the repository root.

Install PHP dependencies, if `apps/backend/vendor` is missing:

```powershell
docker compose run --rm app composer install --no-interaction
```

The app entrypoint also installs Composer dependencies when `vendor` is absent.

Run migrations:

```powershell
docker compose exec app php artisan migrate
```

Run seeders:

```powershell
docker compose exec app php artisan db:seed
```

The seeder creates platform and organization roles, permissions, and the initial organizations with a primary brand, legal entity, and head office. It does not create employees. A super admin is created only when `SUPER_ADMIN_EMAIL` and `SUPER_ADMIN_PASSWORD` are set in the environment.

Start the frontend on the host instead of in Docker:

```powershell
cd apps/frontend
npm install
npm run dev
```

The Vite server proxies `/api` and `/sanctum` to http://localhost:8080. Inside Docker, the frontend container proxies those paths to Nginx.

Start only the backend stack:

```powershell
docker compose up nginx app queue scheduler mysql redis
```

Run tests:

```powershell
docker compose exec app php artisan test
```

Or:

```powershell
.\scripts\test.ps1
```

Frontend checks from `apps/frontend`:

```powershell
npx tsc -b --pretty false
npx oxlint src
```

## API

- `GET /api/v1/health` — the PHP process is serving requests
- `GET /api/v1/ready` — MySQL and Redis responded
- `GET /up` — framework process probe

JSON bodies use `data` for success and `message` plus `errors` for failures. A failed dependency returns HTTP 503 and is not hidden.

## Local notes

Host PHP on this machine cannot run the API. Use the PHP container.

Do not run `migrate:fresh` or `docker compose down -v` after real accounts exist. `down -v` deletes the MySQL volume.
