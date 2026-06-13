# Frontend Architecture

Vue 3 administration dashboard for the LINE Reservation Platform.

## Stack

| Technology | Purpose |
| --- | --- |
| Vue 3.5 | UI and Composition API |
| Vue Router 4 | Client routing and route guards |
| Tailwind CSS 3 | Styling |
| Headless UI | Accessible interactive primitives |
| Heroicons | Icons |
| FullCalendar 6 | Available-time calendar |
| DOMPurify | Content sanitization |
| Vite 7 | Development and production build |

Pinia and Axios are installed, but the current primary implementation uses composables, component-local state, `localStorage` and a Fetch-based API wrapper.

## Structure

```text
frontend/src/
├── components/
│   ├── ActionButtons.vue
│   ├── DataTable.vue
│   ├── DefaultLayout.vue
│   ├── GuestLayout.vue
│   └── StatusTag.vue
├── composables/
│   ├── useAuth.js
│   ├── useLogger.js
│   └── useReservationFilter.js
├── pages/
│   ├── Dashboard.vue
│   ├── Customers.vue
│   ├── Reservations.vue
│   ├── CheckIn.vue
│   ├── Services.vue
│   ├── AvailableTimes.vue
│   ├── Settings.vue
│   ├── Profile.vue
│   ├── Subscription.vue
│   ├── Tenants.vue
│   ├── ActivityLogs.vue
│   ├── LineMessageLogs.vue
│   ├── ForceChangePassword.vue
│   ├── Login.vue
│   └── NotFound.vue
├── utils/
│   ├── api.js
│   ├── auth-enhanced.js
│   ├── logger.js
│   ├── security.js
│   ├── validation.js
│   └── xss-protection.js
├── router.js
├── main.js
└── style.css
```

`Users.vue` exists in the source tree but is not currently registered in `router.js`.

## Routes

Authenticated routes render under `DefaultLayout`.

| Route | Page | Access |
| --- | --- | --- |
| `/` | Dashboard | admin/system admin |
| `/customers` | Customers | admin/system admin |
| `/check-in` | CheckIn | admin/system admin |
| `/services` | Services | admin/system admin |
| `/available-times` | AvailableTimes | currently listed as public by route guard |
| `/reservations` | Reservations | admin/system admin |
| `/profile` | Profile | authenticated |
| `/subscription` | Subscription | authenticated |
| `/settings` | Settings | admin/system admin |
| `/tenants` | Tenants | system admin |
| `/activity-logs` | ActivityLogs | system admin metadata |
| `/line-message-logs` | LineMessageLogs | system admin metadata |
| `/force-change-password` | ForceChangePassword | force-change flow |
| `/login` | Login | public |

The route guard:

1. Reads token and user summary from `localStorage`.
2. Redirects unauthenticated users to login.
3. Enforces forced password change.
4. Calls backend token validation.
5. Checks system administrator metadata.
6. Restricts management pages to administrator roles.

Backend middleware remains the authorization source of truth.

## API Client

`src/utils/api.js` provides:

- base URL resolution from `VITE_API_BASE_URL`
- bearer token attachment
- optional Sanctum CSRF cookie retrieval
- JSON sanitization
- common 401/403/429 handling
- validation error extraction
- request logging in development
- helpers for GET, POST, PUT, DELETE and uploads

Default local URL:

```text
http://localhost:8000/api
```

Production must set:

```dotenv
VITE_API_BASE_URL=https://api.example.com/api
```

`getBackendOrigin()` derives storage asset URLs from the API origin, avoiding hard-coded localhost avatar paths.

## State

Current state is distributed:

- authentication token and user summary: `localStorage`
- page data: component `ref` and `computed`
- reservation filters: `useReservationFilter`
- logging helpers: `useLogger`

This is workable for the current application, but future growth would benefit from consolidating session and shared server state. Introducing a store should replace duplicated logic rather than add another layer beside it.

## Security

- Rendered untrusted content should pass through DOMPurify helpers.
- API URLs must be relative paths passed to the shared client.
- Do not log tokens, credentials, customer payloads or full webhook URLs.
- Production builds drop `console` and `debugger` statements through Terser.
- Token storage in `localStorage` increases the impact of XSS; CSP and sanitization remain important.

## Logging

Frontend logging supports local console output in development and optional backend ingestion. Production configuration should keep verbose/security logs disabled unless retention, redaction and access policies are defined.

## Styling

The UI uses Tailwind utility classes with:

- gray page backgrounds
- white bordered cards
- blue primary actions
- green success/LINE accents
- red destructive actions
- responsive tables and stacked mobile layouts

See [UI Design Guide](UI_DESIGN_GUIDE.md).

## Development

```bash
cd frontend
cp .env.example .env
npm ci
npm run dev
```

Checks:

```bash
npm run lint
npm run build
npm audit --omit=dev
```

The repository currently has no committed Vitest or browser E2E suite.

## Build Notes

Production build output goes to `frontend/dist`.

The current main JavaScript bundle is approximately 805 kB before gzip and triggers Vite's chunk-size warning. Recommended future work:

1. Convert page imports in `router.js` to dynamic imports.
2. Separate FullCalendar into its own chunk.
3. Inspect duplicated auth/logging utilities.
4. Measure route-level loading before adding manual chunk rules.

## Review Checklist

- route access matches backend middleware
- API calls use `utils/api.js`
- loading and error states are visible
- user content is escaped or sanitized
- no environment-specific origin is hard-coded
- keyboard and focus behavior works for dialogs
- mobile layout is usable
- lint and build pass

Last reviewed: 2026-06-13
