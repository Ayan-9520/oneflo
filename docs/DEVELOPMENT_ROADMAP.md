# OneFlo development roadmap

Audit date: 17 November 2026.

Implement one phase at a time. Verify it before starting the next. Do not rebuild the foundation that is already running.

`README.md` calls the existing scaffold "phase 1". This roadmap keeps that work and places the audit in front of it as phase 0. The README was not rewritten.

## Phase 0 — Discovery and environment audit

Status: complete with this document set.

- Inspect the workspace, Docker, database, routes, and Git state.
- Record findings in `docs/`.
- Do not add product features.

## Phase 1 — Foundation

Status: scaffolded in `apps/` and `infrastructure/`, started from the repository root with Docker Compose.

Delivered:

- `backend/` Laravel API and `frontend/` React shell
- Docker Compose with Nginx, PHP-FPM, MySQL 8.4, and Redis 7
- `GET /api/health` with a real database check and a real Redis check
- Platform status page with loading, success, and refresh behavior
- Feature test for the health endpoint

Not included, and not part of closing phase 1:

- Authentication
- Business records
- Sample employees

Verification already performed: health JSON reports both checks ok, the page renders those checks, and `php artisan test` passed (4 tests).

## Phase 2 — Identity and access

Status: next implementation phase.

Build:

- Laravel Sanctum on the existing `users` table
- Login and logout API
- Roles and permissions stored as data
- Group admin role that can act across businesses
- Organization context required on every protected route
- React login screen with loading, validation, success, and error states
- Rejection when organization context is missing

Do not seed the four initial businesses in this phase.

Verify:

- Unauthenticated domain requests fail.
- A user cannot read another organization's records.
- Group admin access is explicit.
- Failed logins do not write passwords to logs.

## Phase 3 — Organization model

Build businesses, brands, legal entities, and branches as separate records.

- A business owns its brands, legal entities, and branches.
- Brand identity (logo, display name) is not the legal employer record.
- Group admin can create and manage businesses.
- No business name is hardcoded in permissions or queries.

The initial companies can be created through the admin flow after this phase, not by a migration that inserts them.

## Phase 4 — Workforce

Build the employee directory, departments, and designations.

- Every record carries `business_id`.
- HR and manager access is scoped to that business.
- Employee numbers, contacts, and identifiers are not written to logs.

## Phase 5 — Attendance and leave

Build attendance and leave policies per business, then the employee transactions that use those policies.

- Policies are configuration, not global constants.
- A failed accrual or attendance write rolls back and surfaces an error.

## Phase 6 — Payroll configuration

Build payroll settings per business and legal entity.

- No salary figures are hardcoded.
- Calculations that must succeed together run in a transaction.
- Statutory output keeps the prescribed format and issuer identity.

## Phase 7 — Documents

Build employee document storage and ordinary generated documents.

- Files go to S3-compatible storage.
- Ordinary documents show the employer logo and legal name, employer details in the body, and "Powered by OneFlo" in the footer.
- Statutory certificates are not restyled as OneFlo marketing documents.

## Phase 8 — Reports, permissions review, and audit

Build business-scoped reports and review the permission matrix.

- Sensitive reads and writes are audit logged.
- Reports cannot cross businesses unless the caller is a group admin acting in an explicit cross-business mode.

## Rules for every phase

- Migrations only. No manual schema edits.
- Form Requests for input. API Resources for output.
- No fake API payloads and no placeholder payroll or attendance results.
- No silent catch that skips a failed write.
- Responsive screens with loading, success, validation, and error states.
- Stop at the end of the phase and verify before continuing.

## Data safety during implementation

The MySQL volume currently holds empty framework tables. New phases should add migrations.

Do not run `migrate:fresh` or delete the `mysql_data` volume after real users or employees exist. The container entrypoint already runs `migrate --force` on startup, so a new migration file is applied on the next Compose start.

Do not run `DatabaseSeeder` as a way to create the four businesses. It only knows how to insert the Laravel test user.
