# Testing Guide

This guide describes checks that actually exist in the repository.

## Current Coverage

Committed automated tests are Laravel Feature tests:

| File | Focus | Tests |
| --- | --- | --- |
| `MultiTenantAuthTest.php` | login, role access, tenant status, forced password change | 10 |
| `TenantIsolationTest.php` | customer/service scope and API isolation | 4 |
| `WebhookTenantTest.php` | UUID tenant resolution, signature checks and follow event | 6 |

Total: 20 Feature tests.

The repository currently has:

- no backend `tests/Unit` test files
- no Vitest suite
- no Cypress/Playwright suite
- no committed GitHub Actions workflow
- no enforced coverage threshold

## Backend Setup

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
```

`phpunit.xml` currently configures MySQL with database `line_reservation`. Create an isolated test database or override environment variables before running tests:

```bash
DB_DATABASE=line_reservation_test php artisan test
```

Never point `RefreshDatabase` tests at a database containing required data.

## Run Backend Tests

```bash
php artisan test
php artisan test --testsuite=Feature
php artisan test --filter=MultiTenantAuthTest
php artisan test --filter=TenantIsolationTest
php artisan test --filter=WebhookTenantTest
```

Code style:

```bash
./vendor/bin/pint --test
```

Coverage requires a compatible coverage driver such as Xdebug or PCOV:

```bash
php artisan test --coverage
```

Coverage output is informational because no minimum is configured.

## What The Tests Assert

### Authentication

- system administrator login
- tenant administrator login
- invalid credential rejection
- suspended tenant rejection
- force-change flag returned at login
- password change flow
- system administrator tenant-management access
- tenant administrator denial on system routes

### Tenant Isolation

- tenant A cannot query tenant B customers through scoped models
- tenant A cannot query tenant B services
- creating within context auto-fills `tenant_id`
- customer list API excludes records from another tenant

### Webhook

- unknown UUID returns `404`
- inactive tenant does not trigger normal processing
- invalid LINE signature returns `401`
- valid signed request is accepted
- follow event creates customer under the resolved tenant
- tenants receive distinct webhook URLs

## Frontend Checks

```bash
cd frontend
npm ci
npm run lint
npm run build
npm audit --omit=dev
```

What these checks provide:

- ESLint syntax and rule validation
- Vue template compilation
- Vite production bundling
- production dependency advisory scan

They do not replace component, integration or browser tests.

## Shell Checks

Deployment scripts can receive a syntax-only check:

```bash
find docs/deployment/cicd -name '*.sh' -type f -print0 \
  | xargs -0 -n1 bash -n
```

This does not test package installation, Apache changes, database creation or rollback.

## Manual Smoke Test

### Authentication

- valid tenant administrator can log in
- invalid password shows validation error
- suspended tenant cannot log in
- forced-password account is redirected
- logout invalidates current token

### Tenant Boundaries

- tenant A does not see tenant B customers
- tenant A does not see tenant B services or reservations
- system administrator can access tenant management
- tenant administrator receives `403` for `/api/system/*`

### Reservation

- create service
- create available time
- create reservation
- confirm reservation
- reschedule reservation
- cancel reservation
- check in and record payment
- capacity and unavailable-time validation work

### LINE

- settings page masks existing credentials
- webhook URL contains UUID token
- invalid signature is rejected
- follow event creates tenant-owned customer
- booking conversation creates correct reservation
- outgoing logs do not expose access tokens

### Frontend

- desktop and mobile navigation work
- protected routes redirect to login
- system-admin pages are hidden from tenant administrators
- avatar URL uses configured backend origin
- forms show loading and errors
- no production console output contains credentials

## API Smoke Commands

Login:

```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -d '{"email":"admin@example.com","password":"your-password"}'
```

Authenticated request:

```bash
TOKEN='1|replace-with-token'

curl http://localhost:8000/api/customers \
  -H 'Accept: application/json' \
  -H "Authorization: Bearer ${TOKEN}"
```

Unknown webhook tenant:

```bash
curl -i -X POST \
  http://localhost:8000/api/webhook/00000000-0000-4000-8000-000000000000 \
  -H 'Content-Type: application/json' \
  -H 'X-Line-Signature: invalid' \
  -d '{"events":[]}'
```

## Adding Tests

Prioritize tests when changing:

- tenant scope or middleware
- role authorization
- webhook identity/signature handling
- reservation capacity and state transitions
- encrypted settings
- destructive customer/reservation behavior

Use factories where possible and assign explicit tenant IDs for test clarity. For cross-tenant tests, create at least two tenants and assert both inclusion and exclusion.

## Known Gaps

- controller coverage beyond core tenant boundaries
- LINE conversation branches in `LineBotService`
- payment/check-in edge cases
- frontend component behavior
- browser accessibility
- deployment integration on clean VM
- backup restore verification

Last reviewed: 2026-06-13
