# Contributing

Development guide for the archived LINE Reservation Platform repository.

The repository is maintained primarily as a portfolio project. Changes should keep documentation honest, avoid inventing unsupported infrastructure and preserve multi-tenant boundaries.

## Local Setup

### Backend

Requirements:

- PHP 8.2+
- Composer 2
- MySQL 8+ or MariaDB 10.6+

```bash
git clone https://github.com/spencerkuku/line-reservation.git
cd line-reservation/backend
cp .env.example .env
composer install
php artisan key:generate
```

Configure a local database in `.env`, then:

```bash
php artisan migrate
php artisan serve
```

Optional demo data:

```bash
php artisan db:seed
```

The default seeder creates fixed demo credentials and must not be used as a production account provisioning workflow.

### Frontend

```bash
cd frontend
cp .env.example .env
npm ci
npm run dev
```

Set `VITE_API_BASE_URL` when the API is not at `http://localhost:8000/api`.

## Change Workflow

1. Start from an up-to-date `main`.
2. Create a focused branch.
3. Inspect related routes, middleware, models and tests before editing.
4. Keep changes within the requested behavior.
5. Run the relevant checks.
6. Update documentation when contracts or setup change.
7. Review the diff for credentials, generated files and unrelated changes.

Example:

```bash
git switch main
git pull --ff-only
git switch -c fix/webhook-validation
```

No `develop` or release branch is required by the current repository.

## Backend Standards

- Follow PSR-12 and Laravel conventions.
- Prefer Form Request or controller validation over manual string checks.
- Keep controllers focused on HTTP concerns.
- Put reusable domain/integration behavior in services or models.
- Use Eloquent relationships and query builder parameters.
- Do not return raw exception messages in production responses.
- Add explicit return and parameter types when they improve clarity.

Formatting:

```bash
cd backend
./vendor/bin/pint
./vendor/bin/pint --test
```

### Tenant Safety

For tenant-owned data:

- use models with `BelongsToTenant`
- ensure middleware establishes `currentTenant`
- include `tenant_id` in factories and privileged creation code
- never use `withoutGlobalScopes()` without an explicit security reason
- test cross-tenant denial
- keep system administrator routes under `/api/system`

### Credentials And Logs

Never commit or log:

- `.env`
- `APP_KEY`
- database passwords
- LINE Channel Secret
- LINE Channel Access Token
- bearer tokens
- complete webhook tokens in routine logs
- customer message payloads unless required and access controlled

## Frontend Standards

- Use Vue Composition API and `<script setup>`.
- Reuse `src/utils/api.js` for HTTP calls.
- Keep environment origins in Vite variables.
- Use semantic buttons/links and visible focus states.
- Sanitize rendered user-controlled content.
- Provide loading, empty and error states.
- Avoid adding another auth or logging abstraction without removing overlap.

Checks:

```bash
cd frontend
npm run lint
npm run build
npm run format
```

`npm run format` modifies files; review its diff before committing.

## Naming

| Item | Convention | Example |
| --- | --- | --- |
| Vue component | PascalCase | `ReservationCard.vue` |
| Composable | `use` + PascalCase | `useReservationFilter` |
| JavaScript variable | camelCase | `selectedTenant` |
| PHP class | PascalCase | `ReservationController` |
| PHP method | camelCase | `updateSubscription` |
| Database field | snake_case | `tenant_id` |
| Route path | kebab-case | `/available-times` |

## Tests

Backend:

```bash
cd backend
php artisan test
php artisan test --filter=TenantIsolationTest
```

Frontend:

```bash
cd frontend
npm run lint
npm run build
```

There is no committed frontend unit or E2E test suite. Do not document `npm test`, Vitest or Cypress as available until they are added to `package.json` and the repository.

## Commit Messages

Conventional-style messages are preferred:

```text
feat(scope): add tenant-aware report endpoint
fix(webhook): reject invalid LINE signatures
docs(api): align endpoint reference with routes
test(auth): cover suspended tenant login
chore(deps): update frontend lockfile
```

Keep the subject concise and explain security or migration implications in the body.

## Pull Request Checklist

- behavior and scope are described
- tenant isolation was considered
- validation and authorization exist
- no secret or personal data is included
- migrations are reversible where practical
- backend tests pass when backend behavior changed
- frontend lint/build pass when frontend changed
- API and architecture docs match changed contracts
- screenshots are included for meaningful UI changes
- generated build output is not committed

## Documentation Rules

Use these files as source of truth:

- routes: `backend/routes/api.php`
- schema: `backend/database/migrations/`
- frontend routes: `frontend/src/router.js`
- dependencies/scripts: package manifests

Avoid claiming:

- complete test coverage
- a live production environment
- tooling not committed in the repository
- a stable public API contract beyond the current code

## Reporting Security Issues

Do not open a public issue containing credentials, exploitable production details or personal data. Since this is an archived portfolio repository without active service, rotate any accidentally exposed credential immediately and remove it from Git history before making the repository public.

Last reviewed: 2026-06-13
