# System Architecture

LINE Reservation Platform 是 Laravel API、Vue 管理後台與 LINE Messaging API 組成的多租戶預約系統。

## Context

```text
Tenant staff
    |
    v
Vue 3 administration dashboard
    |
    | Bearer token / JSON
    v
Laravel 12 API
    |
    +-- MySQL / MariaDB
    +-- database queue
    +-- file or database cache
    +-- local/public file storage

LINE user
    |
    v
LINE Messaging API
    |
    | signed webhook
    v
Laravel webhook endpoint
```

系統目前作為作品集封存，沒有 live production environment。

## Technology

| Layer | Technology |
| --- | --- |
| Backend | PHP 8.2+, Laravel 12, Eloquent, Sanctum |
| Frontend | Vue 3.5, Vue Router 4, Tailwind CSS 3, Vite 7 |
| Calendar | FullCalendar 6 |
| Integration | LINE Bot SDK 11 |
| Database | MySQL 8+ or MariaDB 10.6+ |
| Deployment | Bash, Apache, PHP-FPM, Let's Encrypt, Cloudflare Pages |

Pinia 是已安裝 dependency，但目前主要狀態管理仍由 Vue composables、component state 與 `localStorage` 完成。

## Repository Boundaries

```text
backend/
├── app/Http/Controllers/Api
├── app/Http/Middleware
├── app/Models
├── app/Services
├── database/migrations
├── routes/api.php
└── tests/Feature

frontend/
├── src/components
├── src/composables
├── src/pages
├── src/utils
└── src/router.js

docs/
├── api
├── architecture
├── development
├── deployment
└── maintenance
```

## Multi-Tenant Model

### Tenant Context

`currentTenant` 是 request lifecycle 內的 container binding。

| Request type | Resolver |
| --- | --- |
| Tenant admin API | `TenantIdentificationMiddleware` reads authenticated user's `tenant_id` |
| Public catalog API | `PublicTenantMiddleware` resolves `{tenant_slug}` |
| LINE webhook | `WebhookTenantMiddleware` resolves `{webhook_token}` |
| System admin API | No tenant binding; controller handles cross-tenant access |

### Data Isolation

Business models use `BelongsToTenant`:

1. Registers `TenantScope`.
2. Adds `WHERE <table>.tenant_id = currentTenant.id` when context exists.
3. Automatically assigns `tenant_id` during model creation when omitted.

Models using this pattern include customers, services, available times, reservations, settings and logs.

Operations using `withoutGlobalScopes()` or `withoutGlobalScope(TenantScope::class)` must explicitly constrain `tenant_id`. These calls are privileged and should remain review targets.

### Tenant Lifecycle

Tenant status supports active, trial, suspended and inactive behavior. `Tenant::isActive()` also considers trial and subscription expiry dates.

Requests for unavailable tenants return `403`, except LINE webhook requests intentionally return `200 OK` in several unavailable/configuration error cases to prevent repeated webhook delivery.

## Authentication And Authorization

1. User submits email and password to `/api/auth/login`.
2. Backend verifies user status and tenant availability.
3. Sanctum issues a personal access token.
4. Frontend stores token and user summary in `localStorage`.
5. Authenticated requests send `Authorization: Bearer <token>`.
6. Route middleware verifies role and tenant context.

Authorization layers:

- `auth:sanctum`
- `system.admin`
- `admin`
- `tenant`
- frontend route guards for navigation UX

Frontend checks do not replace backend authorization.

## LINE Webhook Flow

```text
LINE
  -> POST /api/webhook/{uuid}
  -> WebhookTenantMiddleware
     -> find active tenant by webhook_token
     -> load encrypted LINE settings for tenant
     -> decrypt Channel Secret
     -> bind currentTenant
  -> LineWebhookController
     -> verify X-Line-Signature
     -> dispatch event processing to LineBotService
  -> persist customer/reservation/message log
  -> reply through LINE Messaging API
```

Security properties:

- Webhook URL does not expose sequential tenant IDs.
- Every tenant has an independent UUID token.
- Channel credentials are stored in `settings` and encrypted.
- Request signature is validated against the matched tenant.
- Logs should not contain access tokens or complete reply tokens.

## Reservation Flow

```text
Service selection
  -> available date/time selection
  -> customer identity/contact collection
  -> capacity and duplicate validation
  -> reservation creation
  -> pending or confirmed state
  -> optional reschedule/cancel
  -> check-in/no-show
  -> optional payment record
```

Reservation records keep customer, service and available-time relations, plus reservation name/phone/notes snapshots so later customer profile changes do not rewrite historical booking input.

## Frontend Architecture

The Vue app uses:

- `DefaultLayout.vue` for authenticated navigation and role-specific menus.
- Page components for each business module.
- `router.js` for auth and role guards.
- `utils/api.js` as the primary fetch wrapper.
- `composables/useReservationFilter.js` for reservation filtering.
- `utils/logger.js` and `composables/useLogger.js` for controlled frontend logging.
- DOMPurify-based helpers for rendered user content.

The application is currently built as one main client bundle. Production build succeeds, but the generated JavaScript chunk is above Vite's 500 kB warning threshold; route-level lazy loading is the clearest future optimization.

## Backend Architecture

Controllers handle HTTP validation and responses. Models own relationships and small state transitions. Services contain cross-cutting or integration logic:

| Service | Responsibility |
| --- | --- |
| `LineBotService` | LINE conversation and message delivery |
| `ActivityLogger` | Administrator activity audit |
| `SecurityLoggingService` | Authentication and security events |
| `CryptographyService` | Encryption-related helpers |
| `DataIntegrityService` | Integrity checks |
| `SecureHttpClientService` | Restricted outbound HTTP behavior |
| `LoggingService` | Application logging helpers |

`LineBotService` is a large legacy service and is the main maintainability hotspot. Future work should split conversation state, reservation orchestration and message rendering into smaller units with focused tests.

## Error Handling

Laravel exception rendering returns JSON for API requests:

- authentication: `401`
- validation: `422`
- authorization: `403`
- model not found: `404`
- unexpected server error: `500`

Exceptions are logged with request context. When `APP_DEBUG=false`, unexpected 500 responses use a generic message instead of exposing internal exception text.

## Security Controls

- Sanctum bearer tokens
- password hashing
- tenant global scopes
- role middleware
- LINE signature validation
- encrypted LINE credentials
- rate limiting
- security response headers
- CORS allow-list
- audit and security logs
- frontend sanitization helpers
- soft deletes for customer and reservation history

Security limitations:

- Tokens are stored in browser `localStorage`, so XSS prevention remains important.
- The frontend logger and API logger process request metadata; production log retention and redaction must be reviewed.
- Deployment scripts automate privileged server changes and must not be run unreviewed on a new host.

## Deployment Topologies

### Unified

Apache serves the Vue build and forwards API/PHP requests to Laravel/PHP-FPM on one host.

### Headless

Cloudflare Pages hosts the Vue build and Apache/PHP-FPM hosts the API. CORS, Sanctum domains and frontend API environment variables must match both domains.

Deployment scripts are archived operational tooling, not a guarantee of current platform compatibility.

## Known Technical Debt

- Large `LineBotService`.
- Main frontend bundle exceeds Vite's default chunk warning.
- No committed frontend unit or E2E test suite.
- Backend tests cover critical boundaries but not every controller.
- API response envelopes are not fully uniform.
- Some legacy utility modules overlap in authentication and logging responsibility.

## Source Of Truth

- Routes: `backend/routes/api.php`
- Middleware aliases: `backend/bootstrap/app.php`
- Schema: `backend/database/migrations/`
- Frontend routes: `frontend/src/router.js`
- Deployment: `docs/deployment/cicd/*.sh`

Last reviewed: 2026-06-13
