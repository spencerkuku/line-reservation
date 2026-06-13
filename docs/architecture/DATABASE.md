# Database Architecture

資料庫以 MySQL 8+ 或 MariaDB 10.6+ 為目標，schema 由 `backend/database/migrations/` 管理。

## Design Goals

- 每個 business record 明確關聯 `tenant_id`
- 使用 Eloquent global scope 降低跨租戶讀取風險
- 保留預約歷史快照
- 支援軟刪除、活動稽核與 LINE 訊息追蹤
- 對常見租戶、日期、狀態查詢建立複合索引

## Core Relationships

```text
tenants
  ├── users
  ├── customers
  │     └── reservations
  ├── services
  │     └── reservations
  ├── available_times
  │     └── reservations
  ├── settings
  ├── line_message_logs
  └── admin_activity_logs
```

Laravel framework tables additionally include personal access tokens, sessions, cache, jobs, failed jobs and password reset tokens.

## Business Tables

### `tenants`

租戶與訂閱狀態。

| Field | Purpose |
| --- | --- |
| `id` | Primary key |
| `name`, `slug` | Display name and unique URL identifier |
| `email`, `phone`, `address` | Contact information |
| `logo` | Tenant branding asset |
| `webhook_token` | Unique UUID used in LINE webhook URL |
| `status` | Tenant lifecycle status |
| `trial_ends_at` | Trial expiry |
| `subscription_ends_at` | Subscription expiry |
| `plan` | Plan identifier |
| `settings` | Tenant-level JSON settings |
| `deleted_at` | Soft delete |

LINE Channel credentials are not stored on this table. They live in `settings`.

### `users`

後台帳號。

| Field | Purpose |
| --- | --- |
| `tenant_id` | Nullable for system administrator |
| `name`, `email`, `password` | Identity and credentials |
| `role` | `system_admin` or tenant role such as `admin` |
| `status` | Account status; active login expects `Active` |
| `avatar` | Public storage path |
| `must_change_password` | Force-change flow flag |

`email` is globally unique.

### `customers`

租戶客戶及 LINE profile。

Important fields:

- `tenant_id`
- `line_user_id`
- `name`, `phone`, `email`
- `line_display_name`, `line_picture_url`, `line_status_message`
- `gender`, `birthday`, `address`
- `notes`, `preferences`, `referral_source`
- `status`, `last_interaction_at`
- block/unblock audit fields
- `deleted_at`

The original global uniqueness of `line_user_id` comes from an early migration. Multi-tenant code therefore queries with tenant context, but deployments should verify whether the intended database constraint is global or tenant-scoped before importing the same LINE user into multiple tenants.

### `services`

| Field | Purpose |
| --- | --- |
| `tenant_id` | Owner |
| `name`, `description` | Service content |
| `duration` | Duration in minutes |
| `price` | Decimal price |
| `image_url` | Optional image |
| `is_active` | Availability flag |

### `available_times`

| Field | Purpose |
| --- | --- |
| `tenant_id` | Owner |
| `title`, `description` | Display content |
| `start_time`, `end_time` | Slot range |
| `max_capacity` | Maximum bookings |
| `is_active` | Availability flag |

Current booking count is computed from reservations rather than stored as a mutable counter.

### `reservations`

Main booking record.

Relationships:

- belongs to tenant
- belongs to customer
- belongs to service
- belongs to available time
- optionally references users who performed check-in/payment actions

Important fields:

| Group | Fields |
| --- | --- |
| Booking | `reservation_date`, `reservation_time`, `status`, `notes` |
| Snapshot | `reservation_name`, `reservation_phone`, `reservation_notes` |
| Lifecycle | `confirmed_at`, `cancelled_at` |
| Check-in | `check_in_status`, `check_in_time`, `check_in_by`, `no_show` |
| Payment | `payment_status`, `payment_method`, `payment_amount`, `payment_time`, `payment_note` |
| Retention | `deleted_at` |

### `settings`

Tenant key-value settings.

| Field | Purpose |
| --- | --- |
| `tenant_id` | Owner |
| `key` | Setting name |
| `value` | Serialized or encrypted value |
| `type` | Value type metadata |

`tenant_id + key` is unique.

Encrypted keys:

- `line_channel_access_token`
- `line_channel_secret`

Encryption uses Laravel's application key. Losing `APP_KEY` makes existing encrypted settings unreadable.

### `line_message_logs`

LINE inbound/outbound audit data:

- tenant
- LINE user ID
- message type
- message content
- bot response
- direction
- timestamps

Message payloads may contain personal data. Production retention and access controls should be defined before operation.

### `admin_activity_logs`

Administrative audit trail:

- tenant and user identity snapshot
- action and module
- polymorphic subject
- old/new values
- request URL, method, IP and user agent
- status and error message

## Tenant Isolation

Models using `BelongsToTenant` register `TenantScope`.

```php
if (app()->bound('currentTenant')) {
    $builder->where(
        $model->getTable().'.tenant_id',
        app('currentTenant')->id
    );
}
```

Creation hooks assign `tenant_id` from `currentTenant` when it is not supplied.

Rules for privileged code:

1. Treat `withoutGlobalScopes()` as a security-sensitive operation.
2. Always add an explicit `tenant_id` condition unless the operation is intentionally cross-tenant.
3. Keep system administrator routes separate from tenant administrator routes.
4. Test both allowed access and cross-tenant denial.

## Index Strategy

The migrations add indexes for frequent access paths, including:

- tenant and creation date on logs
- tenant, direction and date on LINE logs
- tenant and reservation date
- customer and reservation status
- tenant and customer status
- tenant and LINE user ID
- tenant and active status for services/times
- reservation date, status, check-in status and payment status

Before adding an index, inspect production query plans and duplicate indexes created by earlier migrations.

## Migration Commands

```bash
cd backend

php artisan migrate:status
php artisan migrate
php artisan migrate --pretend
```

Development reset:

```bash
php artisan migrate:fresh
php artisan db:seed
```

`migrate:fresh` destroys data and must never be used against an environment containing required records.

## Seed Data

`DatabaseSeeder` creates portfolio/demo records and fixed initial credentials. It is suitable only for local demonstration.

Do not run the default seeder in production without first replacing its credential strategy and reviewing every inserted record.

## Backup

Minimum database backup:

```bash
mysqldump \
  --single-transaction \
  --routines \
  --triggers \
  -u <user> -p <database> > backup.sql
```

Restore:

```bash
mysql -u <user> -p <database> < backup.sql
```

Backups containing customer, LINE or audit data must be encrypted and access controlled.

## Schema Review Checklist

- `tenant_id` exists on every tenant-owned table
- foreign keys and delete behavior match business retention rules
- unique constraints reflect multi-tenant behavior
- encrypted settings can be decrypted with the deployed `APP_KEY`
- indexes support actual list/filter queries
- soft-deleted personal data has a documented retention policy

Last reviewed: 2026-06-13
