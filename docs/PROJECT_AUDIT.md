# OneFlo project audit

The application lives in `apps/backend` and `apps/frontend`. Docker files live in `infrastructure/`. Start the stack from the repository root with `docker compose up --build`. See `README.md` for the commands. The findings below describe the foundation before that layout move, except where a later sentence names a current path.

This audit inspected the workspace and the running local stack. No application code, migrations, or environment files were changed.

## Current project status

OneFlo is a local foundation, not an HRMS yet. There is one Laravel API and one React application. Docker Compose runs Nginx, PHP-FPM, MySQL 8.4, and Redis 7. The API exposes a real health check. The web app displays that health check.

There is no Git repository, no remote, and no commit history. `git status` cannot run until `git init`.

There are no businesses, employees, policies, payroll rows, or documents. The MySQL `users` table has zero rows. The default seeder has not been run.

## Applications

| Path | Role | Status |
| --- | --- | --- |
| `backend/` | Laravel 13 API | Running in Docker |
| `frontend/` | React 19 + Vite application | Source present; dev server is separate from Compose |
| `backend/resources/js` and `backend/vite.config.js` | Laravel installer Vite stub | Not used by the running UI |

No second product, mobile app, or admin app exists.

## Technologies in use

Backend, from `composer.lock`:

- PHP 8.4 FPM image, constraint `php: ^8.3`
- Laravel Framework 13.42.0
- Laravel Tinker 3.1.0
- PHPUnit 12.5.44
- Laravel Pint 1.32.3
- Laravel Pail 1.3.0

Frontend, from `frontend/package.json`:

- React 19.3.1 and React DOM 19.3.1
- TypeScript 7.0.x
- Vite 8.3.x with `@vitejs/plugin-react` 6.1.x
- Tailwind CSS 4.3.x and `@tailwindcss/vite`
- React Router 8.5.x
- TanStack Query 5.107.x
- React Hook Form 7.92.x, `@hookform/resolvers` 7.0.x, Zod 4.6.x
- Recharts 3.10.x
- Lucide React 1.74.x
- Radix Slot, class-variance-authority, clsx, tailwind-merge
- Oxlint 1.90.x

Infrastructure:

- Docker Compose file at the repository root
- Custom PHP image: `docker/php/Dockerfile`
- Nginx 1.27: `docker/nginx/default.conf`
- MySQL 8.4 and Redis 7 Alpine
- Named volumes `mysql_data` and `redis_data`

Installed but not used by application code yet: React Hook Form, Zod, and Recharts. The shadcn-style setup is limited to `components.json`, `Button`, and `Card`.

## Routes and API shape

API routes in `backend/routes/api.php` use Laravel's `/api` prefix:

- `GET /api/health` — `App\Modules\Platform\Http\Controllers\HealthController`

Framework and web routes:

- `GET /up` — Laravel health route registered in `bootstrap/app.php`
- `GET /` — JSON body `{ data: { name } }` from `config('app.name')`

Frontend route:

- `/` — `PlatformStatusPage`, which calls `/api/health` through the Vite proxy to `http://localhost:8080`

There are no auth, organization, employee, attendance, leave, payroll, document, or report routes.

The health action lives in `App\Modules\Platform\Actions\CheckPlatformHealth`. The controller delegates to that action and returns `HealthResource`. A failed database or Redis check is logged and returned as HTTP 503. Tests cover the success and Redis-failure paths in `backend/tests/Feature/PlatformHealthTest.php`.

## Authentication

Authentication is not implemented.

- `laravel/sanctum` is not in `composer.json`.
- `config/auth.php` has only the default `web` session guard and the `users` Eloquent provider.
- No login, logout, registration, password, or token routes exist.
- The frontend has no session, token, or protected route.
- `App\Models\User` is the Laravel skeleton model: `name`, `email`, `password`, and `remember_token`. It has no role, business, or organization fields.
- `DatabaseSeeder` would create `test@example.com` through `UserFactory` if `db:seed` is run. It has not been run. The factory's default password is the Laravel skeleton password.

## Database

Runtime connection, set by Docker Compose and overriding `backend/.env`:

- Driver: MySQL
- Host inside the app container: `mysql`
- Port: 3306
- Database name: `oneflo`
- Volume: `mysql_data`

Observed tables, all created by the three Laravel skeleton migrations:

| Table | Rows observed |
| --- | --- |
| `users` | 0 |
| `password_reset_tokens` | not counted; created empty with the users migration |
| `sessions` | created; unused while `SESSION_DRIVER=redis` |
| `cache` | created; unused while `CACHE_STORE=redis` |
| `cache_locks` | created; unused while cache is Redis |
| `jobs` | created; unused while `QUEUE_CONNECTION=redis` |
| `job_batches` | created; unused while the queue is Redis |
| `failed_jobs` | created; unused while the queue is Redis |
| `migrations` | 3 |

Migrations on disk:

- `0001_01_01_000000_create_users_table.php`
- `0001_01_01_000001_create_cache_table.php`
- `0001_01_01_000002_create_jobs_table.php`

`backend/database/database.sqlite` also exists. The Composer create-project script created it. The running API does not use it, because Compose sets `DB_CONNECTION=mysql`.

## MySQL and Redis configuration

Both are configured and were healthy at audit time.

Compose sets:

- `DB_CONNECTION=mysql`, `DB_HOST=mysql`, `DB_PORT=3306`
- `CACHE_STORE=redis`
- `QUEUE_CONNECTION=redis`
- `SESSION_DRIVER=redis`
- `REDIS_CLIENT=phpredis`, `REDIS_HOST=redis`, `REDIS_PORT=6379`

The PHP image installs `pdo_mysql`, `pdo_sqlite`, `mbstring`, `bcmath`, `zip`, `opcache`, `pcntl`, and the `redis` extension.

Redis has no password. Both MySQL and Redis publish ports on the host (`3306` and `6379`).

`backend/.env.example` still says `DB_CONNECTION=sqlite`, `SESSION_DRIVER=database`, `QUEUE_CONNECTION=database`, and `CACHE_STORE=database`. That file disagrees with Compose. Inside Docker, Compose environment variables win.

## Environment files

| File | Purpose |
| --- | --- |
| `.env` | Compose interpolation for database name, user, password, and root password. Gitignored. |
| `.env.example` | Same four database keys, with local-only values. |
| `backend/.env` | Laravel environment, including `APP_KEY`. Gitignored. |
| `backend/.env.example` | Laravel example. `APP_NAME=OneFlo`. Database section still documents SQLite. |

No frontend `.env` exists. The Vite dev server proxies `/api` to port 8080.

`FRONTEND_URL` is set in Compose and is not read by application code.

AWS keys in `backend/.env.example` are empty. No S3 disk is configured for employee documents.

## Docker readiness

Local development is ready:

- `docker compose up --build` builds the PHP image, waits for MySQL, generates `APP_KEY` when missing, and runs `php artisan migrate --force`.
- Nginx serves `backend/public` and forwards PHP to `app:9000`.
- The app container was up, MySQL was healthy, and Redis was up during this audit.

Not ready for a hosted environment:

- `APP_DEBUG=true` and `APP_ENV=local` are fixed in Compose.
- No TLS, production Compose override, queue worker, scheduler, backup job, or image registry workflow.
- The entrypoint migrates on every container start. That is acceptable locally and unsafe as a production startup policy once real data exists.
- No container healthcheck on `app`, `nginx`, or `redis`.

## Git status

The directory `e:\Projects\oneflo` is not a Git repository. Nothing has been committed. A later `git init` must keep ignoring `.env`, `backend/.env`, `backend/vendor`, and `frontend/node_modules`. `backend/.gitignore` already ignores `.env` and `/vendor`. The root `.gitignore` repeats those rules and also ignores frontend build output.

## Data that future migrations could affect

Today the only persisted application database is the MySQL volume. It contains framework tables and no people or business rows.

Safe next step: add new migrations. Do not run `migrate:fresh`, `migrate:refresh`, or `db:wipe` once any real account exists.

The `users` table can still be reshaped before the first real account, because it is empty. After accounts exist, identity changes must be additive migrations.

`php artisan db:seed` would insert the skeleton test user. Do not run it as a stand-in for organization data.

The SQLite file is disposable installer output. Deleting it would not affect the MySQL volume. It was left in place.

Container restarts will apply any new migration files automatically because the entrypoint calls `migrate --force`.

## Missing components

- Git repository and remote
- Laravel Sanctum and API authentication
- Role-based access control and a group-admin scope
- Organization context and tenant ownership on domain records
- Business, brand, legal-entity, and branch models
- Employees, departments, and designations
- Attendance, leave, and payroll
- Document storage and generated documents
- Reports and audit logs
- Form Request classes and domain policies
- Queue worker and scheduler containers
- Automated backups
- Production configuration, TLS, and CI
- Frontend auth, organization switching, and module screens

## Conflicts and duplicates

1. Two frontend toolchains. `frontend/` is the product UI. `backend/package.json`, `backend/vite.config.js`, `backend/resources/js/app.js`, `backend/resources/css/app.css`, and `backend/resources/views/welcome.blade.php` are the Laravel installer assets. `routes/web.php` no longer renders that welcome view. These files were not deleted.
2. Two health URLs. `GET /up` is the framework probe. `GET /api/health` is the product probe that checks MySQL and Redis.
3. Two database stories. Compose runs MySQL. `backend/.env.example` and the SQLite file still describe SQLite.
4. Session, cache, and job tables exist in MySQL while Compose points those services at Redis.
5. Package identity is still the skeleton: Composer name `laravel/laravel`, npm name `frontend`.
6. `README.md` numbers the foundation as phase 1. This audit is phase 0 and does not replace that README.

## Security risks

- No authentication or authorization on any route. `/api/health` is public by design. Every future domain route needs an authenticated organization context before it is added.
- MySQL and Redis are published on localhost with Redis unauthenticated. The database password lives in the local `.env` files. That is a local development exposure, not a production secret store.
- `APP_DEBUG=true` would leak stack traces outside this machine if the same Compose file were deployed unchanged.
- The unrun seeder and user factory can create a known test account. They must not be used for real employees.
- There is no audit log, no tenant boundary, and no encryption-at-rest configuration for future personal data.
- Host PHP cannot safely operate the app: the local PHP 8.4 build has no `php.ini`, no OpenSSL, and no `pdo_mysql`. Artisan and Composer must run inside Docker so secrets and database traffic stay with the container toolchain.

## What was not changed

No source, migration, environment, Docker, or lock file was edited or deleted for this audit. The four documents under `docs/` are new.
