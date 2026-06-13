# Deployment Scripts

原專案使用的模組化 Bash 部署與維護工具。

> Archive warning: 專案目前沒有營運中的 production environment。腳本會安裝系統套件、修改 Apache、建立資料庫帳號、申請 SSL、變更檔案權限並執行 migration/seeder。請先逐行審查並在 disposable staging host 驗證，不要直接對重要主機執行。

## Supported Topologies

### Unified

- Vue build and Laravel API on one server
- Apache serves static assets and PHP application
- local MySQL/MariaDB
- optional Let's Encrypt certificate

### API Only

- Laravel API on Apache/PHP-FPM
- Vue frontend on Cloudflare Pages
- generated frontend environment template
- CORS and frontend/backend domain configuration

## Files

```text
cicd/
├── deploy.sh
├── manage.sh
├── quick-update.sh
├── deploy-apache-optimized.sh
├── config.sh
└── lib/
    ├── apache.sh
    ├── backend.sh
    ├── backup.sh
    ├── cloudflare.sh
    ├── core.sh
    ├── db.sh
    ├── frontend.sh
    ├── git.sh
    ├── menu.sh
    ├── ssl.sh
    └── system.sh
```

`deploy-apache-optimized.sh` and `quick-update.sh` are older all-in-one scripts. Prefer the modular `deploy.sh` and `manage.sh` workflow unless specifically auditing legacy behavior.

## Requirements

The scripts assume:

- Debian/Ubuntu-style host
- Bash
- `sudo` access from a non-root user
- Apache and PHP-FPM package naming compatible with the scripts
- MySQL or MariaDB
- outbound network access for packages, Composer, Node.js and certificates
- DNS already pointing to the target host for SSL

Run from a normal user account. `deploy.sh` and `manage.sh` reject root execution.

## Configuration

Defaults live in `config.sh`.

Important variables:

| Variable | Default | Purpose |
| --- | --- | --- |
| `PROJECT_DIR` | `/var/www/line-reservation` | Installation path |
| `DEPLOYMENT_MODE` | `unified` | `unified` or `api_only` |
| `BACKEND_DOMAIN` | empty | API/domain host |
| `USE_SSL` | `false` | SSL setup |
| `DB_NAME` | `line_reservation` | Database name |
| `DB_USER` | `line_user` | Database user |
| `GIT_REPO_URL` | project GitHub URL | Git source |
| `GIT_BRANCH` | `main` | Git branch |
| `DEPLOY_SOURCE` | `release` | `git` or `release` |
| `GITHUB_RELEASE_TAG` | `latest` | Release selector |

Persisted configuration:

```text
~/.line-reservation-config
```

Generated database credential file:

```text
~/.line-reservation-credentials
```

Both files should be mode `600`, excluded from backups shared with third parties and removed when a host is decommissioned.

## Initial Deployment

```bash
cd docs/deployment/cicd
chmod +x deploy.sh manage.sh quick-update.sh lib/*.sh
./deploy.sh
```

Non-interactive unified deployment:

```bash
./deploy.sh \
  --unified \
  --domain=app.example.com \
  --ssl \
  --source=git \
  --target=/var/www/line-reservation
```

API-only deployment:

```bash
./deploy.sh \
  --api-only \
  --domain=api.example.com \
  --cloudflare=app.pages.dev \
  --ssl \
  --source=git
```

Available arguments:

```text
--unified
--api-only
--domain=<domain>
--cloudflare=<domain>
--ssl
--no-ssl
--source=<git|release>
--tag=<release-tag>
--target=<directory>
--help
```

## Deployment Sequence

The modular deployment performs:

1. system package installation
2. database creation and generated password
3. source checkout/download
4. backend `.env` configuration
5. Composer install and app key generation
6. migration and seeding
7. frontend install/build for unified mode
8. Apache virtual host configuration
9. optional Let's Encrypt setup
10. permissions, storage link and Laravel cache rebuild

Important: the current script calls the default `DatabaseSeeder`. That seeder creates demo accounts with fixed initial credentials. Remove or replace this step before any real production deployment.

## Cloudflare Pages

For API-only mode, set at least:

```dotenv
VITE_API_BASE_URL=https://api.example.com/api
VITE_APP_URL=https://app.pages.dev
VITE_NODE_ENV=production
VITE_DEBUG=false
VITE_ENABLE_SECURITY_LOGS=false
```

Backend values must match:

```dotenv
APP_URL=https://api.example.com
FRONTEND_URL=https://app.pages.dev
CORS_ALLOWED_ORIGINS=https://app.pages.dev
SANCTUM_STATEFUL_DOMAINS=app.pages.dev
SESSION_SECURE_COOKIE=true
```

Use `manage.sh` Cloudflare tools to inspect generated settings, but verify the resulting Apache and Laravel configuration manually.

## Management Commands

```bash
./manage.sh
./manage.sh update
./manage.sh cache
./manage.sh restart
./manage.sh status
./manage.sh backup
./manage.sh help
```

The interactive menu includes:

- domain and SSL configuration
- Cloudflare Pages integration
- frontend rebuild
- Laravel cache clear
- full rebuild
- Git update/branch/restore operations
- service status and logs
- backup/restore

Some Git menu actions are destructive. Read the prompt and inspect the working tree before confirming.

## Backup

`lib/backup.sh` supports:

- environment backup
- project archive
- database backup
- full backup and restore flows

Default base directory:

```text
~/line-reservation-backups
```

Environment backups include secrets. Keep them encrypted at rest and never commit them.

## Verification

After deployment:

```bash
curl -I https://app.example.com/
curl -I https://app.example.com/up
curl -H 'Accept: application/json' https://app.example.com/api/auth/user
```

Expected behavior:

- frontend returns `200`
- Laravel health endpoint `/up` returns success
- unauthenticated `/api/auth/user` returns `401`
- HTTPS certificate matches domain
- webhook endpoint is reachable only at `/api/webhook/{uuid}`

Also verify:

```bash
sudo apache2ctl configtest
sudo systemctl status apache2
sudo systemctl status mysql
sudo systemctl status php8.3-fpm
```

Adjust the PHP service version to the installed host version.

## Known Risks

- Package repository and operating-system assumptions may have changed.
- Release-based deployment requires a matching GitHub Release.
- Seeder behavior is unsafe for production credentials.
- Apache and PHP versions are detected heuristically.
- Backup restore overwrites files and may restore stale secrets.
- Scripts have shell syntax checks but no automated integration test against a clean VM.

## Preflight Checklist

- inspect every changed script
- take an independent host/database backup
- replace demo seeding behavior
- confirm DNS and firewall rules
- confirm database privileges
- set `APP_DEBUG=false`
- protect `APP_KEY`
- verify CORS and Sanctum domains
- test rollback on staging
- verify log and backup retention

Last reviewed: 2026-06-13
