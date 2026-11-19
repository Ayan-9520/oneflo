# OneFlo technical architecture

Audit date: 17 November 2026.

This is the architecture to keep. The foundation already follows it. Later phases should extend it, not replace it.

## Recommendation

Build OneFlo as a modular monolith:

- One React application in `frontend/`
- One Laravel API in `backend/`
- One MySQL 8.4 schema for all businesses
- Redis for cache, queues, and sessions
- Nginx in front of PHP-FPM
- S3-compatible object storage for documents, added when the document phase starts

A database-per-business layout is not recommended. A group admin must see across businesses, and the first release has four businesses in one platform. Shared tables with a required business owner on every business-specific row are the right boundary. Legal employing entities stay separate from brand identity.

## Runtime

```text
Browser
  -> Vite dev server :5173  (local)
  -> Nginx :8080
  -> PHP-FPM (Laravel)
  -> MySQL 8.4
  -> Redis 7
```

In local development, Vite proxies `/api` to Nginx. The browser does not call port 8080 directly.

Production should serve the built frontend from Nginx or a static host and terminate TLS there. That deployment file does not exist yet.

## Backend modules

Current code:

```text
backend/app/Modules/Platform/
  Actions/CheckPlatformHealth.php
  Data/PlatformHealthReport.php
  Http/Controllers/HealthController.php
  Http/Resources/HealthResource.php
```

Keep this shape for every module:

```text
backend/app/Modules/<Module>/
  Actions/            business use cases
  Data/               immutable results and value objects
  Http/Controllers/   thin HTTP entry points
  Http/Requests/      Form Request validation
  Http/Resources/     API response shapes
  Models/             Eloquent models owned by the module
  Policies/           authorization
```

Rules already stated for the product, and matched by the health module:

- Controllers call an action and return a resource.
- Validation belongs in Form Requests.
- Critical writes use database transactions.
- Every schema change is a migration.
- Protected routes resolve the authenticated user and the active business before querying tenant data.
- Logs record the operation and the failure class, not passwords, tokens, document contents, or government identifiers.

Do not add a second Laravel app for "admin" and "employee". Group admin, HR, manager, and employee are roles in the same API.

## Tenancy

Recommended ownership columns:

- `businesses` are the operating companies on the platform.
- `brands` belong to a business and carry logo and display identity.
- `legal_entities` belong to a business and carry the employer legal name used on documents and statutory output.
- `branches` belong to a business.
- Domain rows such as employees, departments, policies, and payroll setups carry `business_id`.
- A row that is legally issued by an employing entity also carries `legal_entity_id`.

Brand and legal entity must not be the same table. A business can present one brand and employ people through another legal name.

Authorization checks both:

1. The user is allowed to perform the action.
2. The record belongs to a business that user is allowed to access.

Group admin is an explicit platform role, not a missing `business_id`. Queries for a normal user always include the active business. Missing organization context is a rejection, not a query without a filter.

## Identity

Add Laravel Sanctum to the existing `users` table. Do not invent a second user table.

Session or token auth stays on the API. The React app sends the credential with TanStack Query and treats 401 and 422 as visible error states.

Roles and permissions are data, not hardcoded business names. Seed only permission names and system role definitions. Do not seed RupeeDial, Motorcart, Propancer, or UdhyamSetu in code.

The default `web` guard can remain for Laravel internals. API requests should use the Sanctum guard. Until that exists, no domain endpoint should be added.

## Database

Keep MySQL 8.4 and the `oneflo` schema.

Use migrations for every change. Prefer additive changes. Do not reset the volume to "start clean" after the first real user exists.

Redis remains the cache, queue, and session store. The MySQL `cache`, `jobs`, and `sessions` tables can stay; they are unused while Compose points those drivers at Redis. Do not switch the running app back to the SQLite file.

Future personal and financial columns need deliberate retention and access rules. Payroll figures and document keys must not be written to logs.

## Frontend

Current structure:

```text
frontend/src/
  app/providers.tsx          Query client
  app/router.tsx             React Router
  components/ui/             shared primitives
  features/platform/         platform health
  lib/api.ts                 shared response reading
  lib/utils.ts               class name helper
```

Add product areas under `frontend/src/features/<module>/` with their own API functions, forms, and pages. Shared controls stay in `components/ui`. Feature screens must show loading, success, validation, and error states, and must remain usable on a narrow viewport.

React Hook Form, Zod, and Recharts are already dependencies. Use them when the first real form and chart arrive. Do not add a second component library.

## Documents

Ordinary generated documents:

- Header: employing company's logo and legal name
- Body: employer details for that document
- Footer: "Powered by OneFlo"

Statutory certificates follow the prescribed format and issuer identity. They do not get a OneFlo marketing footer.

Store files in S3-compatible storage when the document phase starts. The AWS keys in `backend/.env.example` are placeholders only.

## Docker

Compose services: `nginx`, `app`, `queue`, `scheduler`, `frontend`, `mysql`, and `redis`.

The frontend service is the Vite dev server. Host `npm run dev` remains available and proxies to Nginx on port 8080.

A backup command is still a later phase.

## Folder structure to keep

```text
oneflo/
  apps/backend/            Laravel API
  apps/frontend/           React application
  infrastructure/docker/   PHP and frontend images
  infrastructure/nginx/    Nginx site config
  docs/
  docker-compose.yml
  .env.example             Compose database settings
```

Leave the Laravel installer files in `apps/backend/resources` and `apps/backend/vite.config.js` until a dedicated cleanup. They are not on the request path. Deleting them is a separate change and should be explained when it happens.

## Out of scope for the current foundation

The health endpoint is the only product behavior. Organization, workforce, time, payroll, documents, and reports are later modules on this same architecture.
