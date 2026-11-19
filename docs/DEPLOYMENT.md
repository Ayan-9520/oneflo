# Production deployment

OneFlo production runs on a Hostinger VPS with Docker Compose. The production project name is `oneflo-prod`, so its volumes stay separate from the local `oneflo` stack. MySQL and Redis have no published ports. Only Nginx publishes 80 and 443.

The local `docker-compose.yml` is for development. Do not point it at the VPS.

## Hosts

| Host | Role |
| --- | --- |
| `oneflo.in`, `www.oneflo.in` | Redirect to `https://app.oneflo.in` |
| `app.oneflo.in` | Default login. The browser loads the app and calls `/api` on the same host. |
| `api.oneflo.in` | Same application for health checks and server clients. |
| `hr.example.com` | Optional organization domain. It selects a tenant only after DNS verification. |

`ONEFLO_PLATFORM_HOSTS` lists the addresses that do not select a tenant. Organization context on those addresses still requires an authorized `X-Organization-Id`. A hostname that is not in that list and is not a verified `organization_domains` row receives HTTP 421. `X-Forwarded-Host` is not trusted. Nginx overwrites the host PHP sees, and the default server closes unknown hosts. Local Compose adds the internal name `nginx` because the Vite proxy uses it. Do not add that name in production.

A verified domain locks every request to that organization. A different `X-Organization-Id` is rejected. The account payload and organization list on that host contain only the locked organization. The business switcher is hidden.

## Files

| File | Purpose |
| --- | --- |
| `infrastructure/docker/backend/Dockerfile` | PHP 8.4 FPM, Composer `--no-dev`, MySQL client, OPcache |
| `infrastructure/docker/frontend/Dockerfile.prod` | Frontend production build |
| `infrastructure/docker/nginx/Dockerfile` | Nginx serving the built frontend and proxying PHP |
| `docker-compose.prod.yml` | App, queue, scheduler, MySQL, Redis, Nginx |
| `infrastructure/nginx/production.conf` | TLS, redirects, unknown-host rejection |
| `infrastructure/nginx/custom-domain.conf.template` | One server block per customer domain |
| `.env.production.example` | Variable names. Copy it on the server. |
| `scripts/backup.sh` | `mysqldump` into private storage, then copy to `backups/` |
| `scripts/restore.sh` | Restore only with `CONFIRM=yes` |
| `scripts/issue-certificate.sh` | First Let's Encrypt certificate |
| `scripts/renew-certificates.sh` | Webroot renewal and Nginx reload |
| `infrastructure/logrotate/oneflo.conf` | Host rotation for copied SQL dumps |

Container logs use the Docker `json-file` driver, 10 MB per file, five files.

## First boot

1. Create a VPS user, install Docker Engine and the Compose plugin, and open only 22, 80, and 443.
2. Point `oneflo.in`, `www.oneflo.in`, `app.oneflo.in`, and `api.oneflo.in` at the VPS.
3. Copy the repository to `/opt/oneflo`. Do not copy a development `.env`.
4. `cp .env.production.example .env.production` and `chmod 600 .env.production`.
5. Set `APP_KEY` (`php artisan key:generate --show` on a trusted machine), database passwords, and `REDIS_PASSWORD`. Use a long random value for each. Repeat the database password into `MYSQL_PASSWORD` and `DB_PASSWORD`, and the root password into both root variables.
6. Set `SEED_INITIAL=true` and `SUPER_ADMIN_EMAIL` / `SUPER_ADMIN_PASSWORD` for the first start only. `RoleAndPermissionSeeder` loads the permission catalog. `InitialOrganizationSeeder` creates the four organizations when they are absent and the super admin when those variables are set. It does not create sample employees, and it does not reset an existing super admin password.
7. Issue the certificate before the HTTPS server can start:

```sh
sh scripts/issue-certificate.sh oneflo.in www.oneflo.in app.oneflo.in api.oneflo.in
```

The script stops Nginx, runs Certbot standalone on port 80, and starts Nginx again. The certificate name used by the shipped config is `app.oneflo.in`, so that name must be included.

8. Build and start:

```sh
docker compose -f docker-compose.prod.yml up -d --build
```

The app container migrates and runs `RoleAndPermissionSeeder`. With `SEED_INITIAL=true` it also runs `InitialOrganizationSeeder`.

9. Set `SEED_INITIAL=false` and recreate the app container so later restarts do not keep seeding:

```sh
docker compose -f docker-compose.prod.yml up -d app queue scheduler
```

10. Install log rotation:

```sh
cp infrastructure/logrotate/oneflo.conf /etc/logrotate.d/oneflo
```

11. Schedule the host backup, for example daily at 02:30, after the in-container schedule has also run `backup:database` at 02:15 when the database connection is MySQL:

```sh
30 2 * * * cd /opt/oneflo && sh scripts/backup.sh >> /var/log/oneflo-backup.log 2>&1
20 3 * * 1 cd /opt/oneflo && sh scripts/renew-certificates.sh >> /var/log/oneflo-renew.log 2>&1
```

Copy `backups/` off the VPS. A backup that exists only on the same disk is not a backup.

## Custom organization domain

1. In the business, under Businesses → Custom domains, add `hr.motorcart.in`. The screen shows a TXT record named `oneflo-domain-verification`.
2. Publish that TXT record and an A record to the VPS.
3. Copy `infrastructure/nginx/custom-domain.conf.template` to `infrastructure/nginx/custom/hr.motorcart.in.conf`, replace the example host, and issue a certificate for that host.
4. `docker compose -f docker-compose.prod.yml exec nginx nginx -s reload`
5. Choose Verify DNS in OneFlo. Until the TXT record matches, the host returns 421 and does not select a tenant.
6. After verification, opening `https://hr.motorcart.in` locks the session to that organization.

Repeat for `hr.rupeedial.com`, `hr.propancer.com`, and `hr.udhyamsetu.com` only when each business asks for its own host. Do not add those names to `ONEFLO_PLATFORM_HOSTS`.

## Checks

```sh
docker compose -f docker-compose.prod.yml ps
curl -fsS https://app.oneflo.in/api/v1/health
curl -fsS https://app.oneflo.in/api/v1/ready
curl -fsS https://api.oneflo.in/api/v1/health
```

Then in a browser:

- Sign in at `https://app.oneflo.in/login`.
- Switch between organizations the account is allowed to enter. A custom domain must not show the switcher.
- Open payroll for the selected business and generate a document preview. Confirm the legal name matches that business.
- Run `sh scripts/backup.sh`. Restore only into a spare database or a maintenance window, with `CONFIRM=yes scripts/restore.sh backups/<file>.sql`. Compare organization counts before and after a drill.

`/api/v1/ready` returns 503 when MySQL or Redis is down. `/api/v1/health` answers while PHP is up.

## Storage

Employee documents and backups live in the `storage` volume at `/var/www/html/storage`. The documents disk is `storage/app/private/documents`. Backups are `storage/app/private/backups`. Neither path is under `public/`.

Redis persists with AOF, `appendfsync everysec`, and `requirepass`. Queue and scheduler use that Redis. The scheduler runs `reports:run` daily and `backup:database` at 02:15.

## Rollback

1. Stop traffic by stopping Nginx: `docker compose -f docker-compose.prod.yml stop nginx`.
2. If the release migrated the database, restore the dump taken immediately before the release:

```sh
CONFIRM=yes sh scripts/restore.sh backups/oneflo-<stamp>.sql
```

3. Check out the previous release and rebuild:

```sh
docker compose -f docker-compose.prod.yml up -d --build
```

4. Start Nginx and repeat the health, login, and organization checks.

Do not run `docker compose down -v`. That deletes MySQL, Redis, certificates, and uploaded documents. Do not run `migrate:fresh`.

If the new containers fail health checks and the database was not migrated, `docker compose -f docker-compose.prod.yml up -d --build` on the previous source is enough. Keep the previous image tags or the previous git revision on the VPS until the new release has passed the checks above.
