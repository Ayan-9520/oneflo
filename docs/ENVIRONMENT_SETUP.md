# OneFlo environment setup

Audit date: 17 November 2026.

This describes the environment that is already in the workspace. It does not change those files.

## Local toolchain

Observed on this machine:

- Docker Desktop is installed and the daemon can run Compose.
- Node.js is available for the Vite app.
- Host PHP 8.4 is installed without a `php.ini`. It does not provide OpenSSL, mbstring, or `pdo_mysql`.

Use Docker for Composer, Artisan, tests, MySQL, and Redis. `docker compose up --build` from the repository root also starts the frontend. Host Node.js can still run `apps/frontend` with `npm run dev`.

## Repository layout

```text
.env.example                 Compose database settings
.env                         Local Compose values, gitignored
docker-compose.yml
infrastructure/docker/php/   PHP 8.4 FPM image and entrypoint
infrastructure/docker/frontend/
infrastructure/nginx/        Nginx site config
apps/backend/                Laravel API
apps/frontend/               React application
docs/
scripts/
.github/workflows/
```

There is no Git repository yet.

## Configure Compose

From the repository root:

```powershell
Copy-Item .env.example .env
```

`.env.example` defines `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, and `DB_ROOT_PASSWORD` for local MySQL. If `.env` already exists, keep it. Do not commit `.env` or `apps/backend/.env`.

`backend/.env` is created by the entrypoint when it is missing, and `APP_KEY` is generated when it is empty. Compose injects MySQL and Redis settings into the app container, which override the SQLite values still written in `backend/.env.example`.

## Start the API stack

```powershell
docker compose up --build
```

Services:

| Service | Image | Host port |
| --- | --- | --- |
| nginx | nginx:1.27-alpine | 8080 |
| app | local `oneflo-app` build | 9000 inside the network only |
| mysql | mysql:8.4 | 3306 |
| redis | redis:7-alpine | 6379 |

On startup the app container waits for MySQL, then runs `php artisan migrate --force`.

Checks:

- http://localhost:8080/api/health returns `data.status` of `ok` when MySQL and Redis respond.
- http://localhost:8080/up is the framework probe and does not check Redis.

Run tests inside the app container:

```powershell
docker compose exec app php artisan test
```

## Start the web app

```powershell
cd frontend
npm install
npm run dev
```

The dev server listens on port 5173 and proxies `/api` to http://localhost:8080. Open http://localhost:5173.

`frontend/package.json` scripts:

- `npm run dev`
- `npm run build` (`tsc -b` and Vite build)
- `npm run lint` (Oxlint)
- `npm run preview`

## Configuration that is present

- PHP extensions in the image: `pdo_mysql`, `pdo_sqlite`, `mbstring`, `bcmath`, `zip`, `opcache`, `pcntl`, `redis`
- Nginx root: `backend/public`
- Cache, queue, and session drivers in Compose: Redis
- Logs in Compose: stderr
- Vite path alias `@` to `frontend/src`

## Configuration that is missing or inconsistent

- `backend/.env.example` still documents SQLite, database sessions, database cache, and the database queue. The running stack uses MySQL and Redis. Trust Compose for the running app.
- `backend/database/database.sqlite` is installer output. The API does not use it while Compose is running.
- Redis has no password. MySQL and Redis ports are published on the host for local development.
- `APP_ENV=local` and `APP_DEBUG=true` are set in Compose. Do not deploy this Compose file as production.
- No queue worker, scheduler, backup job, TLS certificate, or CI workflow is defined.
- No frontend `.env`. API calls use the Vite proxy.
- `FRONTEND_URL` is set in Compose and unused by PHP code.
- S3 settings in `backend/.env.example` are empty.
- Sanctum and CORS are not installed or published as application config. The dev proxy makes a browser CORS setup unnecessary for the current page.

## Data already in MySQL

The `mysql_data` volume has the Laravel framework tables and zero users. Restarting containers reapplies migrations. It does not wipe the volume.

Do not run `migrate:fresh` or `docker compose down -v` if you need to keep that database. Today a volume reset would drop only empty framework tables. That stops being true after the first real account or employee.

`php artisan db:seed` is not part of setup. The default seeder inserts a test user and has not been run.

## Duplicate frontend files

`backend/package.json` and `backend/vite.config.js` belong to the Laravel installer. Do not run `npm run dev` inside `backend/` for the product UI. The product UI is `frontend/`.
