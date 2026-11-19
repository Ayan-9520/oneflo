# Testing report

Date: 18 November 2026.

Command:

```text
docker compose exec -T app php artisan test --compact
```

Result: **53 passed, 834 assertions, 76.72s**. Tests use sqlite `:memory:` (`tests/bootstrap.php` and the `TestCase` guard). After the suite, `Organization::count()` on the development MySQL database was **4**. The suite did not seed or convert employees there.

One expected `testing.ERROR` line: `platform.health.failed` with Redis connection refused, from `PlatformHealthTest` forcing that failure.

## Backend tests that exist

| Area | File | What it covers |
| --- | --- | --- |
| Payroll calculation | `tests/Unit/PayrollCalculatorTest.php` | Pure calculator, no statutory constants baked in |
| Attendance calculation | `tests/Unit/AttendanceCalculatorTest.php` | Attendance day math |
| Authentication | `tests/Feature/AuthenticationTest.php` | Login, lockout, reset with a 12-character mixed password |
| Tenant isolation | `tests/Feature/TenantIsolationTest.php` | Cross-organization reads and writes |
| Payroll API | `tests/Feature/PayrollTest.php` | Runs, payslips, statutory path |
| Attendance and leave | `tests/Feature/AttendanceLeaveTest.php` | Leave and attendance workflows |
| Employees | `tests/Feature/EmployeeManagementTest.php` | Employee lifecycle, including transfer history |
| Documents | `tests/Feature/DocumentConfigurationTest.php`, `tests/Feature/ReportDocumentTest.php` | Configuration, generation, official versus branded output |
| Recruitment and onboarding | `tests/Feature/RecruitmentOnboardingTest.php` | Hiring workflow |
| Operations | `tests/Feature/OperationsTest.php` | Performance, assets, expenses, helpdesk, engagement |
| Portal and notifications | `tests/Feature/PortalNotificationTest.php` | Self-service and delivery status |
| SaaS | `tests/Feature/SaasReadinessTest.php` | Plans, tenant boundaries for AI, biometric, and workflows |
| Platform | `tests/Feature/PlatformHealthTest.php` | Health check, including a forced Redis failure |
| Production audit | `tests/Feature/ProductionAuditTest.php` | Named tenant cases below; **2 passed, 39 assertions** |

`tests/Feature/ExampleTest.php` and `tests/Unit/ExampleTest.php` are the framework smoke tests.

## Named checks

| Check | Result |
| --- | --- |
| OneFlo Super Admin can manage authorized organizations | `ProductionAuditTest`: super admin `GET /organizations` contains both named organizations. Live session on `/platform` and `/businesses` showed administration and the Motorcart brand. |
| RupeeDial HR cannot access Motorcart private employee data | Same test: 404 on the employee, list omits `EMP-MC-PRIVATE`, Motorcart header returns 403. |
| Motorcart managers cannot access RupeeDial payroll | Foreign header 403; foreign run id on the own header 404. |
| Employees cannot access other employees' payslips | Colleague payslip 403; own locked payslip 200. |
| Unauthorized users cannot download documents | Employee role `GET` of a colleague's issued PDF returns 403. |
| Organization branding is applied | Preview HTML contains `Motorcart Private Limited`, omits `RupeeDial Legal`, and escapes a script tag. Live businesses page showed the Motorcart brand. |
| Payroll cannot be processed twice accidentally | Second `POST /payroll/runs` for the same month returns 422. Database unique key is `payroll_run_open_period`. |
| Reports respect permissions | Department manager: headcount 200, CSV export 403. |
| Business transfers preserve employment history | Source status `transferred`, employment record `transfer`, new employee `EMP-RD-TRANSFER` in the target organization. |
| Login abuse | Eleventh failed login in a minute returns 429. |
| In-memory backup | `backup:database` against sqlite `:memory:` fails. |

These named tenant cases use organizations renamed inside the sqlite test. They were not replayed by creating a RupeeDial HR user in MySQL.

## What was not run

- **Frontend component tests.** `apps/frontend/package.json` has `dev`, `build`, `lint`, and `preview`. There is no Vitest, Jest, or Playwright script, and no component test files. Nothing in this category passed, because nothing was executed.
- **Browser end-to-end suite.** There is no Playwright project. Manual checks on 18 November 2026: sign-in reached `/platform`; `/businesses` showed Motorcart and Anika Shah; `/people` showed `EMP-MC-001` and did not show `RupeeDial`.
- **Backup against development MySQL.** The command exists and refuses `:memory:` and `public/` paths. A dump of the live database was not taken.
- **CSRF inside PHPUnit.** The base test case turns that middleware off. The live 419 is recorded in `docs/SECURITY_AUDIT.md`.

`npx tsc -b --pretty false` in `apps/frontend` was clean in the previous phase. It was not re-run as part of the 53-test suite.

Do not treat a feature as covered by a frontend or end-to-end test. Coverage for the named checks is the PHPUnit files in the table above.
