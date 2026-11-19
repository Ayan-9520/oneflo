# Security audit

Date: 18 November 2026. Scope: OneFlo API (`http://localhost:8080`) and workspace (`http://localhost:5173`). Evidence is the backend suite, `ProductionAuditTest`, live HTTP headers, a live login, and dependency audits. Items without that evidence are listed as residual.

## Controls that hold

| Control | Evidence |
| --- | --- |
| Authentication | `AuthenticationTest` covers login, lockout after five failures (423 on the correct password while locked), session regeneration, and password reset. Login uses `Hash::check` with a dummy hash for unknown accounts. Remember-me is off. |
| Login rate limit | `ProductionAuditTest`: ten wrong passwords return 422; the eleventh returns 429. Limiter `auth` is 10/minute per IP and lowercased email, and 60/minute per IP, on login, forgot-password, and reset-password. |
| Password policy | Resets use `Password::defaults()`: minimum 12 characters, letters, mixed case, and numbers. `AuthenticationTest` resets to `Replacement-Password-12`. |
| Session cookie | `config/session.php` uses the database driver, 120-minute lifetime, `http_only`, `same_site=lax`. `secure` defaults to true when `APP_ENV=production`. Local HTTP stays usable. |
| CSRF | Live `POST /api/v1/auth/login` from origin `http://localhost:5173` without `X-XSRF-TOKEN` returned **419** `The page expired. Refresh and try again.` and set an `httponly` `samesite=lax` session cookie. PHPUnit disables `PreventRequestForgery`, so the suite does not prove CSRF. |
| Authorization and tenant scope | Protected routes require a verified session, `X-Organization-Id`, and `RequireOrganizationContext`. `organization_id` is not fillable; `BelongsToOrganization` rejects a mismatched context. Route binding is tenant-scoped. |
| Cross-tenant access | `ProductionAuditTest` and `TenantIsolationTest`. Named case: a RupeeDial HR admin reading a Motorcart employee returns 404, and the Motorcart header returns 403. A Motorcart department manager reading RupeeDial payroll returns 403 with the foreign header and 404 for a foreign run id on the own header. |
| Payslip access | A colleague's payslip returns 403. The employee's own locked payslip returns 200 when `payroll.view_own` or `employees.view_own` applies. |
| Issued documents | `authorizeIssuedDocument` requires `documents.view`. Holders of `employees.view` or `documents.generate` see the organization's issued documents. An employee role, which has `documents.view` only, receives 403 on a colleague's letter. Downloads set `Content-Disposition: attachment`, `Cache-Control: private, no-store`, and `X-Content-Type-Options: nosniff`. |
| XSS | No `dangerouslySetInnerHTML` in `apps/frontend/src`. Document preview uses escaped Blade `{{ }}`. The audit preview contains `&lt;script&gt;` and does not contain a raw `<script>` tag. |
| SQL injection | Report `whereRaw` clauses use bound parameters. No user-supplied raw SQL was found. |
| Payroll double processing | `payroll_runs.open_period` is unique with `legal_entity_id`. Reversed runs clear `open_period`. A second open run for the same legal employer and month returns 422. |
| Reports | A department manager can view headcount and receives 403 on CSV export (`reports.export` is not on that role). |
| Business transfer | Source employment status becomes `transferred`, an employment record of change type `transfer` is stored, and the new employee is created in the target organization. |
| Branding | Motorcart document preview contains `Motorcart Private Limited` and does not contain `RupeeDial Legal`. A foreign `legal_entity_id` returns 404. Live `/businesses` for the signed-in super admin showed the Motorcart brand, legal entity, and Anika Shah. |
| Audit integrity | `AuditLog` throws `LogicException` on update and delete. Covered at the end of `ProductionAuditTest`. |
| Security headers | `SecurityHeaders` and nginx both send `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, and a referrer policy. Live `GET /api/v1/health` on 18 November 2026 confirmed both DENY values after the nginx change. PHP also sends `X-Permitted-Cross-Domain-Policies: none`. `X-Powered-By` is hidden at nginx. |
| Secrets | Passwords are hashed. Integration and notification secrets are encrypted. The backup command passes the database password through `MYSQL_PWD` and does not print it. `.env` and `APP_KEY` are not committed by this audit. |
| Biometric intake | Outside session auth, throttled at 30/minute per IP and organization header, requires a matching Bearer secret (`hash_equals`) and an employee inside that organization. |
| PHP dependencies | `composer audit`: no security advisories. |

## Live session

Signed in at `http://localhost:5173/login` as `admin@oneflo.local` (OneFlo Super Admin). `/platform` rendered organization registration, plans, and activation. `/businesses` showed the Motorcart brand, the Motorcart legal entity, and Anika Shah. `/people` contained Anika Shah and `EMP-MC-001` and did not contain the text `RupeeDial`.

A separate RupeeDial HR login was not created in MySQL. The negative cross-tenant cases are the sqlite feature tests above.

## Fixes applied in this audit

- Login, forgot-password, and reset-password are rate limited.
- Password reset requires 12 characters with mixed case and a number.
- Production session cookies default to `Secure`.
- Audit rows cannot be updated or deleted.
- One open payroll period per legal employer is enforced in the database (`2026_11_18_100000_guard_open_payroll_periods`).
- Employees cannot download another employee's issued letter.
- `php artisan backup:database` refuses a path inside `public/` and refuses an in-memory database.
- Clickjacking header is `DENY` in both PHP and nginx.

## Residual

| Severity | Item |
| --- | --- |
| High | `npm audit` in `apps/frontend`: 5 high, 0 critical. All are picomatch `<=4.0.7` ReDoS (GHSA-fc5q-39xc-vjpf) through Vite 8, `@vitejs/plugin-react`, fdir, and tinyglobby. npm's only fix is a major downgrade to Vite 6.2.7. That was not applied. This is a development file-glob issue, not tenant data. |
| Medium | CORS uses Laravel's default `allowed_origins` of `*`. The browser app talks to the API through the Vite proxy, so the session stays same-origin in local development. Production must set explicit origins before the API is exposed on another host. |
| Medium | Upload rules check MIME and size (pdf/jpg/jpeg/png, 5 MB; photos also webp, 2 MB). They do not inspect magic bytes. |
| Medium | `backup:database` is not scheduled. It was not run against the development MySQL database. |
| Low | `Access-Control-Allow-Origin: *` appears on the public health response. Cookie responses are not readable cross-origin while credentials are not reflected. |
| Info | The full PHPUnit suite cannot prove CSRF because `tests/TestCase.php` disables `PreventRequestForgery`. The live 419 is the evidence. |

No critical defect remained open after the suite. The picomatch finding is the remaining high-severity item, and it is confined to the frontend toolchain.
