# Maintenance Guide

Operational notes for the archived LINE Reservation Platform.

> The project is not currently operated. Commands below describe the intended Laravel/Apache environment and must be adapted to the actual host before use.

## Routine Commands

From `docs/deployment/cicd`:

```bash
./manage.sh status
./manage.sh update
./manage.sh cache
./manage.sh restart
./manage.sh backup
```

Manual Laravel operations:

```bash
cd /var/www/line-reservation/backend
php artisan about
php artisan migrate:status
php artisan queue:failed
php artisan schedule:list
```

## Health Checks

Laravel health endpoint:

```bash
curl -fsS https://your-domain.example/up
```

API authentication behavior:

```bash
curl -i \
  -H 'Accept: application/json' \
  https://your-domain.example/api/auth/user
```

An unauthenticated request should return `401`, proving the API is reachable and middleware is active.

Service checks:

```bash
sudo systemctl status apache2
sudo systemctl status mysql
sudo systemctl status php8.3-fpm
sudo apache2ctl configtest
```

Use the PHP-FPM service name installed on the host.

## Logs

Laravel:

```text
backend/storage/logs/
```

Apache:

```text
/var/log/apache2/access.log
/var/log/apache2/error.log
```

Common commands:

```bash
tail -f backend/storage/logs/laravel.log
tail -n 200 /var/log/apache2/error.log
journalctl -u php8.3-fpm --since today
```

Log review must account for personal data. Customer details, LINE messages, IP addresses and user agents should have a retention policy and restricted access.

## Deployment Update

Before updating:

1. inspect pending commits/releases
2. create database and environment backups
3. check disk space
4. verify rollback source
5. announce maintenance window if users exist

The modular update:

```bash
./manage.sh update
```

It pulls source, installs Composer dependencies, runs migrations, rebuilds the frontend in unified mode, rebuilds caches and restarts services.

For an archived portfolio deployment, prefer a fresh staging host over updating an old unknown server in place.

## Laravel Cache

Clear:

```bash
php artisan optimize:clear
```

Build production caches:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

After changing `.env`, clear and rebuild configuration cache.

## Queue And Scheduler

The default project configuration uses a database queue.

Inspect:

```bash
php artisan queue:failed
php artisan queue:retry all
php artisan queue:work --tries=3
```

A real deployment should run queue workers under systemd or Supervisor instead of an interactive shell.

Laravel scheduler requires:

```cron
* * * * * cd /var/www/line-reservation/backend && php artisan schedule:run >> /dev/null 2>&1
```

Review `routes/console.php` and command classes before enabling scheduled jobs.

## Backup

Minimum backup set:

- database dump
- `backend/.env`
- `APP_KEY`
- uploaded files under `backend/storage/app/public`
- persistent deployment configuration
- web server configuration

Database:

```bash
mysqldump --single-transaction \
  -u <user> -p <database> \
  > database-$(date +%Y%m%d-%H%M%S).sql
```

Environment and uploaded files contain secrets or personal data. Encrypt backups and store them outside the application host.

### Restore Test

A backup is not complete until restore is tested:

1. create an isolated database
2. restore the dump
3. deploy matching application version
4. restore the same `APP_KEY`
5. verify encrypted LINE settings can be read
6. verify uploaded assets
7. run smoke tests

## Database Maintenance

```bash
mysqlcheck -u <user> -p --analyze <database>
```

Use application queries or migration-safe tooling for cleanup. Avoid direct deletes that bypass soft-delete or audit behavior.

Review growth of:

- `line_message_logs`
- `admin_activity_logs`
- `jobs`
- `failed_jobs`
- `sessions`
- soft-deleted customers/reservations

Define retention before deleting records.

## LINE Webhook Troubleshooting

Expected URL:

```text
https://your-domain.example/api/webhook/{webhook_token}
```

Checklist:

1. tenant is active and unexpired
2. UUID token matches tenant record
3. encrypted Channel Secret and Access Token exist
4. deployed `APP_KEY` can decrypt settings
5. LINE Console uses HTTPS URL
6. `X-Line-Signature` is calculated from the exact raw body
7. server time and TLS certificate are valid
8. logs contain no credential values

Do not disable signature verification as a troubleshooting shortcut on a reachable environment.

## CORS Troubleshooting

Check:

```dotenv
FRONTEND_URL=https://app.example.com
CORS_ALLOWED_ORIGINS=https://app.example.com
SANCTUM_STATEFUL_DOMAINS=app.example.com
SESSION_SECURE_COOKIE=true
```

Then:

```bash
php artisan optimize:clear
curl -i \
  -H 'Origin: https://app.example.com' \
  https://api.example.com/api/auth/user
```

Do not use wildcard origins with credentials.

## Common Failures

### 500 Response

```bash
tail -n 200 backend/storage/logs/laravel.log
php artisan about
php artisan config:show app
```

Check permissions on `storage` and `bootstrap/cache`, database connectivity and required PHP extensions.

### Database Connection

```bash
mysql -h <host> -P <port> -u <user> -p <database>
php artisan migrate:status
```

Never print the database password into shared logs or shell history.

### Frontend Uses Wrong API

Inspect build-time environment:

```dotenv
VITE_API_BASE_URL=https://api.example.com/api
```

Vite variables are embedded at build time. Rebuild after changing them:

```bash
cd frontend
npm ci
npm run build
```

### Avatar Or Storage 404

```bash
cd backend
php artisan storage:link
ls -la public/storage
```

Confirm the frontend API origin and Laravel public disk URL match.

## Security Maintenance

Regular checks:

```bash
cd backend
composer audit

cd ../frontend
npm audit --omit=dev
```

Also:

- apply operating-system security updates
- renew and test TLS certificates
- rotate credentials after any suspected exposure
- review administrator accounts and active Sanctum tokens
- review failed logins and unexpected webhook traffic
- verify `APP_DEBUG=false`
- verify backups are encrypted and restorable

## Decommissioning

When shutting down a deployment:

1. disable DNS and webhook delivery
2. revoke LINE tokens
3. revoke Sanctum tokens
4. archive or securely delete customer data according to policy
5. destroy database and backup credentials
6. remove `.env`, `APP_KEY` and deployment credential files
7. retain only sanitized artifacts needed for portfolio demonstration

Last reviewed: 2026-06-13
