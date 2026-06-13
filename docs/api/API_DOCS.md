# API Reference

Laravel API 的路由與使用約定。本文件依 `backend/routes/api.php` 整理。

> API 目前沒有公開託管環境。以下 URL 使用本機或 placeholder domain。

## Base URL

```text
Local:      http://localhost:8000/api
Production: https://your-domain.example/api
```

所有 JSON request 建議帶上：

```http
Accept: application/json
Content-Type: application/json
```

## Authentication

管理 API 使用 Laravel Sanctum personal access token。

```http
Authorization: Bearer <access_token>
```

登入：

```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -d '{"email":"admin@example.com","password":"your-password"}'
```

成功回應的主要欄位：

```json
{
  "success": true,
  "access_token": "1|...",
  "token_type": "Bearer",
  "user": {
    "id": 1,
    "name": "Tenant Admin",
    "email": "admin@example.com",
    "role": "admin",
    "must_change_password": false,
    "tenant_id": 1
  }
}
```

角色：

| Role | Scope |
| --- | --- |
| `system_admin` | 系統監控及租戶管理 |
| `admin` | 所屬租戶的管理功能 |

## Multi-Tenant Identification

系統有三種租戶識別方式：

1. 管理 API：從 Sanctum 使用者的 `tenant_id` 建立 `currentTenant` context。
2. 公開查詢：URL 中使用 `{tenant_slug}`。
3. LINE webhook：URL 中使用租戶專屬 UUID `{webhook_token}`。

租戶資料模型會套用 `TenantScope`。系統管理員路由不設定 `currentTenant`，需要跨租戶查詢的 controller 應明確處理 scope。

## Response Conventions

常見成功格式：

```json
{
  "success": true,
  "data": {}
}
```

部分既有 endpoint 直接回傳 resource、collection 或 `user` 欄位，因此 client 不應假設所有成功資料都位於 `data`。

驗證錯誤：

```json
{
  "success": false,
  "message": "驗證失敗",
  "errors": {
    "field": ["錯誤訊息"]
  },
  "timestamp": "2026-06-13T12:00:00+08:00"
}
```

常見狀態碼：

| Status | Meaning |
| --- | --- |
| `200` | 成功 |
| `201` | 建立成功 |
| `400` | 請求狀態不允許 |
| `401` | 未認證或 LINE signature 無效 |
| `403` | 權限不足、租戶不可用 |
| `404` | Resource 或租戶不存在 |
| `422` | Validation error |
| `429` | Rate limit exceeded |
| `500` | Server error |

Production 且 `APP_DEBUG=false` 時，500 response 不回傳內部 exception message。

## Public Endpoints

| Method | Path | Purpose |
| --- | --- | --- |
| `POST` | `/auth/login` | 登入並取得 Sanctum token |
| `POST` | `/webhook/{webhook_token}` | 接收 LINE webhook |
| `POST` | `/frontend-logs` | 接收前端一般日誌 |
| `POST` | `/frontend-logs/error` | 接收前端錯誤日誌 |
| `GET` | `/{tenant_slug}/services` | 公開服務列表 |
| `GET` | `/{tenant_slug}/available-times` | 公開可預約時段 |

Webhook token 必須是 UUID：

```text
POST /api/webhook/xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx
```

Webhook 還需要 LINE 提供的簽章：

```http
X-Line-Signature: <base64-hmac-signature>
```

伺服器會使用該租戶加密儲存的 Channel Secret 驗證 request body。

## Authenticated Account Endpoints

需要 `auth:sanctum`。

| Method | Path | Purpose |
| --- | --- | --- |
| `POST` | `/auth/logout` | 刪除目前 access token |
| `GET` | `/auth/user` | 取得目前使用者 |
| `POST` | `/auth/profile` | 更新姓名、Email、頭像 |
| `POST` | `/auth/password` | 更新密碼 |
| `POST` | `/auth/force-change-password` | 首次登入設定新密碼 |
| `GET` | `/subscription` | 取得訂閱資訊 |
| `GET` | `/subscription/usage` | 取得使用量 |

`/auth/profile` 上傳頭像時使用 `multipart/form-data`，檔案限制為 JPEG、PNG、JPG 或 GIF，最大 2 MB。

## System Administrator Endpoints

需要 `auth:sanctum` 與 `system.admin`。

### Monitoring

| Method | Path |
| --- | --- |
| `GET` | `/system/stats` |
| `GET` | `/system/monitoring` |
| `GET` | `/system/alerts` |
| `GET` | `/system/performance-history` |
| `GET` | `/system/tenant-activity` |

### Tenant Management

| Method | Path | Purpose |
| --- | --- | --- |
| `GET` | `/system/tenants` | 租戶列表 |
| `GET` | `/system/tenants/statistics` | 租戶統計 |
| `POST` | `/system/tenants` | 建立租戶 |
| `GET` | `/system/tenants/{tenant}` | 租戶詳情 |
| `PUT` | `/system/tenants/{tenant}` | 更新租戶 |
| `DELETE` | `/system/tenants/{tenant}` | 刪除租戶 |
| `PUT` | `/system/tenants/{tenant}/status` | 更新狀態 |
| `PUT` | `/system/tenants/{tenant}/subscription` | 更新方案或到期日 |
| `POST` | `/system/tenants/{tenant}/reset-password` | 重設租戶管理員密碼 |

## Tenant Administrator Endpoints

需要 `auth:sanctum`、`admin` 與 `tenant` middleware。

### Dashboard

| Method | Path |
| --- | --- |
| `GET` | `/dashboard/stats` |
| `GET` | `/dashboard/reservations` |
| `GET` | `/dashboard/popular-services` |
| `GET` | `/dashboard/notices` |

### Services

| Method | Path |
| --- | --- |
| `GET` | `/services` |
| `POST` | `/services` |
| `PUT` | `/services/{service}` |
| `DELETE` | `/services/{service}` |
| `GET` | `/services/{service}/reservations` |

### Available Times

| Method | Path |
| --- | --- |
| `GET` | `/available-times` |
| `POST` | `/available-times` |
| `PUT` | `/available-times/{availableTime}` |
| `DELETE` | `/available-times/{availableTime}` |
| `POST` | `/available-times/{availableTime}/toggle-status` |

### Users

| Method | Path |
| --- | --- |
| `GET` | `/users` |
| `POST` | `/users` |
| `PUT` | `/users/{user}` |
| `PUT` | `/users/{user}/status` |
| `DELETE` | `/users/{user}` |

### Reservations

| Method | Path | Route limit |
| --- | --- | --- |
| `GET` | `/reservations` | 120/minute |
| `POST` | `/reservations` | 10/minute |
| `PUT` | `/reservations/{reservation}/confirm` | 30/minute |
| `PUT` | `/reservations/{reservation}/cancel` | 30/minute |
| `PUT` | `/reservations/{reservation}/reschedule` | 30/minute |

### Customers

| Method | Path |
| --- | --- |
| `GET` | `/customers` |
| `POST` | `/customers` |
| `GET` | `/customers/statistics` |
| `POST` | `/customers/recalculate-stats` |
| `GET` | `/customers/{customer}` |
| `PUT` | `/customers/{customer}` |
| `DELETE` | `/customers/{customer}` |
| `POST` | `/customers/{customer}/interaction` |
| `POST` | `/customers/{customer}/recalculate-stats` |
| `POST` | `/customers/{customer}/block` |
| `POST` | `/customers/{customer}/unblock` |

### Check-In And Payment

| Method | Path |
| --- | --- |
| `GET` | `/check-in/today` |
| `POST` | `/check-in/reservations/{reservation}/check-in` |
| `POST` | `/check-in/reservations/{reservation}/no-show` |
| `POST` | `/check-in/reservations/{reservation}/payment` |

### Settings

| Method | Path |
| --- | --- |
| `GET` | `/settings` |
| `POST` | `/settings` |
| `GET` | `/settings/line` |
| `POST` | `/settings/line` |
| `GET` | `/settings/webhook-url` |

LINE Channel Access Token 與 Channel Secret 寫入 `settings` table，透過 Laravel encryption at rest。

### Activity Logs

Base path: `/admin/activity-logs`

| Method | Path |
| --- | --- |
| `GET` | `/admin/activity-logs` |
| `GET` | `/admin/activity-logs/stats` |
| `GET` | `/admin/activity-logs/trends` |
| `GET` | `/admin/activity-logs/modules` |
| `GET` | `/admin/activity-logs/actions` |
| `GET` | `/admin/activity-logs/{log}` |

### LINE Message Logs

Base path: `/line-message-logs`

| Method | Path |
| --- | --- |
| `GET` | `/line-message-logs` |
| `GET` | `/line-message-logs/stats` |
| `GET` | `/line-message-logs/trends` |
| `GET` | `/line-message-logs/types` |
| `GET` | `/line-message-logs/tenants` |
| `GET` | `/line-message-logs/{log}` |

## Client Example

```bash
TOKEN='1|replace-with-token'

curl http://localhost:8000/api/customers \
  -H 'Accept: application/json' \
  -H "Authorization: Bearer ${TOKEN}"
```

## Maintenance Rule

新增或移除 endpoint 時，先修改 `backend/routes/api.php` 與測試，再同步更新本文件。Controller validation rules 是 request payload 的最終依據。

Last reviewed: 2026-06-13
