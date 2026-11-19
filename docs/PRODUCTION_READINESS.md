# Production readiness

Date: 18 November 2026.

OneFlo is **not ready to deploy** until the residual items below are accepted or closed. Critical authentication, tenant, payroll, document, and audit defects found in this pass were fixed and covered by the backend suite: **53 passed, 834 assertions**. Development data was left in place (`Organization::count()` = 4). The November 2026 Motorcart payroll preview was not locked or reversed.

## Ready

- Session authentication, account lockout, login rate limits, and a 12-character mixed-case reset policy.
- Organization header, tenant scoping, and the named cross-tenant denials in `ProductionAuditTest`.
- Payslip and issued-document authorization, including employee-to-employee denial.
- One open payroll period per legal employer, enforced by a unique key.
- Immutable audit rows.
- Security headers `nosniff` and `X-Frame-Options: DENY` on the live API after the nginx restart.
- CSRF rejection (HTTP 419) on a cross-site login without a token.
- `composer audit` reports no advisories.
- `php artisan backup:database` can dump MySQL with `--single-transaction` and refuses in-memory and `public/` targets.
- Feature restrictions apply only when a subscription row exists, so current businesses without a plan keep working.

## Blockers and accepted residuals

1. **High — frontend toolchain.** `npm audit` reports five high findings, all picomatch ReDoS via Vite 8. There is no forward fix that keeps the current Vite major. Decide explicitly to accept this for the dev server, or replace the toolchain. Do not treat it as fixed.
2. **No frontend or end-to-end test runner.** Component tests and a browser suite do not exist. Production sign-off cannot claim them. See `docs/TESTING_REPORT.md`.
3. **Backups are not scheduled.** Wire `backup:database` to off-server storage and a restore drill before go-live. Confirm a dump and a restore; this audit did not.
4. **CORS.** Laravel still allows origin `*`. Keep the browser on the same site as the API, or set an explicit origin list and credential policy before exposing the API on another host.
5. **Uploads.** MIME and size checks are in place. Add magic-byte checks before accepting identity documents from the public internet.
6. **Transport.** `SESSION_SECURE_COOKIE` follows production automatically. Terminate TLS in front of nginx and confirm the session cookie is `Secure` on that host. Local HTTP was intentionally left usable.
7. **Secrets.** Production `APP_KEY`, database password, mail, and integration secrets must come from the host environment. Do not copy the local `.env`.

## Go-live checks still to perform on the target host

- Run `php artisan test` on the release build and keep the sqlite guard.
- Run `composer audit` and `npm audit`, and record the picomatch decision.
- `GET /api/v1/health` and confirm `X-Frame-Options: DENY` is the only frame policy.
- Repeat the unauthenticated login POST and confirm 419.
- Sign in as super admin and confirm only authorized organizations are listed.
- Confirm a non-platform user cannot enter a pending organization.
- Confirm the payroll month that should stay open is still preview, not locked.
- Take a database backup, restore it to a spare database, and compare organization and employee counts.
