# 多租戶 B2B API 文件

## 目錄

- [API 概覽](#api-概覽)
- [多租戶架構](#多租戶架構)
- [認證](#認證)
- [公開端點](#公開端點)
- [認證端點](#認證端點)
- [租戶管理](#租戶管理)
- [客戶管理](#客戶管理)
- [預約管理](#預約管理)
- [服務管理](#服務管理)
- [時段管理](#時段管理)
- [報到管理](#報到管理)
- [儀表板](#儀表板)
- [系統設定](#系統設定)
- [活動日誌](#活動日誌)
- [LINE Webhook](#line-webhook)
- [錯誤碼](#錯誤碼)

## API 概覽

多租戶 B2B LINE 預約系統提供 RESTful API，支援多租戶架構。每個租戶擁有獨立的資料空間和 API 資源。

### Base URL

```
開發環境: http://localhost:8000/api
生產環境: https://your-domain.com/api
```

### 多租戶識別

API 透過以下方式識別租戶：
- **Slug**: URL 中包含 `/tenant/{slug}`
- **Token**: 租戶相關的 API Token
- **Webhook Token**: LINE Webhook 专用 Token

### 通用響應格式

#### 成功響應

```json
{
  "success": true,
  "data": {
    // 資料內容
  },
  "message": "操作成功"
}
```

#### 錯誤響應

```json
{
  "success": false,
  "message": "錯誤訊息",
  "errors": {
    "field": ["錯誤詳情"]
  }
}
```

### HTTP 狀態碼

| 狀態碼 | 說明 |
|--------|------|
| `200` | 請求成功 |
| `201` | 資源創建成功 |
| `204` | 請求成功但無內容返回 |
| `400` | 錯誤的請求 |
| `401` | 未認證 |
| `403` | 無權限 |
| `404` | 資源不存在 |
| `422` | 驗證失敗 |
| `429` | 請求過於頻繁 |
| `500` | 伺服器錯誤 |

### Rate Limiting

| 端點類型 | 限制 |
|---------|------|
| 公開端點 | 60 次/分鐘 |
| 預約創建 | 10 次/分鐘 |
| 預約查詢 | 120 次/分鐘 |
| 其他認證端點 | 60 次/分鐘 |

## 認證

本系統使用 **Laravel Sanctum** 進行 Token 認證。

### 認證流程

1. 使用 `/auth/login` 獲取 Token
2. 在後續請求的 Header 中包含 Token
3. Token 格式: `Bearer {token}`

### Header 格式

```http
Authorization: Bearer your_token_here
Content-Type: application/json
Accept: application/json
```

---

## 🔓 公開端點

### 登入

獲取認證 Token。

**端點**: `POST /auth/login`

**請求體**:
```json
{
  "email": "admin@example.com",
  "password": "password"
}
```

**成功響應** (200 OK):
```json
{
  "success": true,
  "data": {
    "user": {
      "id": 1,
      "name": "Admin User",
      "email": "admin@example.com",
      "role": "admin"
    },
    "token": "1|abcd1234efgh5678..."
  },
  "message": "登入成功"
}
```

**錯誤響應** (401 Unauthorized):
```json
{
  "success": false,
  "message": "帳號或密碼錯誤"
}
```

### 獲取服務列表

獲取所有啟用的服務項目。

**端點**: `GET /services`

**查詢參數**:
- `is_active` (boolean, optional): 篩選啟用狀態

**成功響應** (200 OK):
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "剪髮服務",
      "description": "專業剪髮服務",
      "duration": 60,
      "price": "500.00",
      "image_url": null,
      "is_active": true,
      "created_at": "2025-10-23T10:00:00.000000Z",
      "updated_at": "2025-10-23T10:00:00.000000Z"
    }
  ]
}
```

### 獲取可用時段

獲取可預約的時段。

**端點**: `GET /available-times`

**查詢參數**:
- `start_date` (date, optional): 開始日期 (Y-m-d)
- `end_date` (date, optional): 結束日期 (Y-m-d)
- `is_active` (boolean, optional): 篩選啟用狀態

**成功響應** (200 OK):
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "title": "早上時段",
      "description": "09:00-12:00",
      "start_time": "2025-10-24 09:00:00",
      "end_time": "2025-10-24 12:00:00",
      "max_capacity": 5,
      "current_bookings": 2,
      "available_spots": 3,
      "is_active": true
    }
  ]
}
```

### LINE Webhook

接收 LINE 平台的 Webhook 事件。

**端點**: `POST /line/webhook`

**Header**:
```http
X-Line-Signature: {signature}
Content-Type: application/json
```

**請求體** (範例):
```json
{
  "destination": "U1234567890abcdef",
  "events": [
    {
      "type": "message",
      "message": {
        "type": "text",
        "id": "123456789",
        "text": "預約"
      },
      "timestamp": 1625000000000,
      "source": {
        "type": "user",
        "userId": "U1234567890abcdef"
      },
      "replyToken": "abcdefghijklmnop"
    }
  ]
}
```

**成功響應** (200 OK):
```json
{
  "success": true,
  "message": "Webhook processed successfully"
}
```

---

##  認證端點

**所有端點需要認證 Token**

### 登出

登出並刪除當前 Token。

**端點**: `POST /auth/logout`

**成功響應** (200 OK):
```json
{
  "success": true,
  "message": "登出成功"
}
```

### 獲取當前用戶資訊

**端點**: `GET /auth/user`

**成功響應** (200 OK):
```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "Admin User",
    "email": "admin@example.com",
    "role": "admin",
    "status": "Active",
    "created_at": "2025-10-23T10:00:00.000000Z"
  }
}
```

### 更新個人資料

**端點**: `POST /auth/profile`

**請求體**:
```json
{
  "name": "New Name",
  "email": "newemail@example.com",
  "phone": "0912345678"
}
```

**成功響應** (200 OK):
```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "New Name",
    "email": "newemail@example.com",
    "phone": "0912345678"
  },
  "message": "個人資料更新成功"
}
```

### 更改密碼

**端點**: `POST /auth/password`

**請求體**:
```json
{
  "current_password": "oldpassword",
  "password": "newpassword",
  "password_confirmation": "newpassword"
}
```

**成功響應** (200 OK):
```json
{
  "success": true,
  "message": "密碼更新成功"
}
```

---

## 客戶管理

**需要管理員權限**

### 獲取客戶列表

**端點**: `GET /customers`

**查詢參數**:
- `page` (integer): 頁碼
- `per_page` (integer): 每頁數量 (預設: 15)
- `search` (string): 搜尋關鍵字 (姓名、電話、LINE ID)
- `status` (string): 狀態篩選 (`active`, `inactive`, `blocked`)
- `sort_by` (string): 排序欄位 (`created_at`, `name`, `total_reservations`)
- `sort_order` (string): 排序方向 (`asc`, `desc`)

**成功響應** (200 OK):
```json
{
  "success": true,
  "data": {
    "data": [
      {
        "id": 1,
        "line_user_id": "U1234567890abcdef",
        "name": "王小明",
        "phone": "0912345678",
        "email": "customer@example.com",
        "gender": "male",
        "birthday": "1990-01-01",
        "status": "active",
        "total_reservations": 5,
        "total_spent": "2500.00",
        "last_interaction_at": "2025-10-23T10:00:00.000000Z",
        "created_at": "2025-10-01T10:00:00.000000Z"
      }
    ],
    "current_page": 1,
    "last_page": 10,
    "per_page": 15,
    "total": 150
  }
}
```

### 創建客戶

**端點**: `POST /customers`

**請求體**:
```json
{
  "name": "王小明",
  "phone": "0912345678",
  "email": "customer@example.com",
  "gender": "male",
  "birthday": "1990-01-01",
  "line_user_id": "U1234567890abcdef",
  "notes": "VIP 客戶"
}
```

**成功響應** (201 Created):
```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "王小明",
    "phone": "0912345678",
    "email": "customer@example.com",
    "status": "active"
  },
  "message": "客戶創建成功"
}
```

### 獲取客戶詳情

**端點**: `GET /customers/{id}`

**成功響應** (200 OK):
```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "王小明",
    "phone": "0912345678",
    "email": "customer@example.com",
    "gender": "male",
    "birthday": "1990-01-01",
    "status": "active",
    "total_reservations": 5,
    "total_spent": "2500.00",
    "reservations": [
      {
        "id": 10,
        "service": {
          "name": "剪髮服務"
        },
        "reservation_date": "2025-10-25",
        "reservation_time": "10:00:00",
        "status": "confirmed"
      }
    ]
  }
}
```

### 更新客戶

**端點**: `PUT /customers/{id}`

**請求體**:
```json
{
  "name": "王小明",
  "phone": "0912345678",
  "email": "customer@example.com",
  "status": "active"
}
```

**成功響應** (200 OK):
```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "王小明",
    "phone": "0912345678"
  },
  "message": "客戶資料更新成功"
}
```

### 刪除客戶

**端點**: `DELETE /customers/{id}`

**成功響應** (204 No Content)

### 封鎖客戶

**端點**: `POST /customers/{id}/block`

**請求體**:
```json
{
  "reason": "違反使用條款"
}
```

**成功響應** (200 OK):
```json
{
  "success": true,
  "message": "客戶已封鎖"
}
```

### 解除封鎖

**端點**: `POST /customers/{id}/unblock`

**成功響應** (200 OK):
```json
{
  "success": true,
  "message": "客戶已解除封鎖"
}
```

### 客戶統計

**端點**: `GET /customers/statistics`

**成功響應** (200 OK):
```json
{
  "success": true,
  "data": {
    "total_customers": 150,
    "active_customers": 120,
    "blocked_customers": 5,
    "new_this_month": 10,
    "average_spent": "1500.00"
  }
}
```

---

## 預約管理

**需要管理員權限**

### 獲取預約列表

**端點**: `GET /reservations`

**查詢參數**:
- `page` (integer): 頁碼
- `per_page` (integer): 每頁數量
- `status` (string): 狀態篩選 (`pending`, `confirmed`, `completed`, `cancelled`)
- `date_from` (date): 開始日期
- `date_to` (date): 結束日期
- `service_id` (integer): 服務 ID
- `customer_id` (integer): 客戶 ID

**成功響應** (200 OK):
```json
{
  "success": true,
  "data": {
    "data": [
      {
        "id": 1,
        "customer": {
          "id": 1,
          "name": "王小明",
          "phone": "0912345678"
        },
        "service": {
          "id": 1,
          "name": "剪髮服務",
          "price": "500.00"
        },
        "reservation_date": "2025-10-25",
        "reservation_time": "10:00:00",
        "status": "confirmed",
        "check_in_status": "pending",
        "payment_status": "unpaid",
        "reservation_name": "王小明",
        "reservation_phone": "0912345678",
        "notes": "請準時",
        "created_at": "2025-10-23T10:00:00.000000Z"
      }
    ],
    "current_page": 1,
    "last_page": 5,
    "total": 75
  }
}
```

### 創建預約

**端點**: `POST /reservations`

**請求體**:
```json
{
  "customer_id": 1,
  "service_id": 1,
  "available_time_id": 1,
  "reservation_date": "2025-10-25",
  "reservation_time": "10:00:00",
  "reservation_name": "王小明",
  "reservation_phone": "0912345678",
  "reservation_notes": "請準時",
  "notes": "管理員備註"
}
```

**成功響應** (201 Created):
```json
{
  "success": true,
  "data": {
    "id": 1,
    "customer_id": 1,
    "service_id": 1,
    "reservation_date": "2025-10-25",
    "reservation_time": "10:00:00",
    "status": "pending"
  },
  "message": "預約創建成功"
}
```

### 確認預約

**端點**: `PUT /reservations/{id}/confirm`

**成功響應** (200 OK):
```json
{
  "success": true,
  "data": {
    "id": 1,
    "status": "confirmed",
    "confirmed_at": "2025-10-23T10:00:00.000000Z"
  },
  "message": "預約已確認"
}
```

### 取消預約

**端點**: `PUT /reservations/{id}/cancel`

**請求體**:
```json
{
  "reason": "客戶要求取消"
}
```

**成功響應** (200 OK):
```json
{
  "success": true,
  "data": {
    "id": 1,
    "status": "cancelled",
    "cancelled_at": "2025-10-23T10:00:00.000000Z"
  },
  "message": "預約已取消"
}
```

---

## 服務管理

**需要管理員權限**

### 創建服務

**端點**: `POST /services`

**請求體**:
```json
{
  "name": "剪髮服務",
  "description": "專業剪髮服務",
  "duration": 60,
  "price": 500,
  "is_active": true
}
```

**成功響應** (201 Created):
```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "剪髮服務",
    "duration": 60,
    "price": "500.00",
    "is_active": true
  },
  "message": "服務創建成功"
}
```

### 更新服務

**端點**: `PUT /services/{id}`

**請求體**:
```json
{
  "name": "剪髮服務 (更新)",
  "price": 550,
  "is_active": true
}
```

**成功響應** (200 OK):
```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "剪髮服務 (更新)",
    "price": "550.00"
  },
  "message": "服務更新成功"
}
```

### 刪除服務

**端點**: `DELETE /services/{id}`

**成功響應** (204 No Content)

---

## ⏰ 時段管理

**需要管理員權限**

### 創建時段

**端點**: `POST /available-times`

**請求體**:
```json
{
  "title": "早上時段",
  "description": "09:00-12:00",
  "start_time": "2025-10-24 09:00:00",
  "end_time": "2025-10-24 12:00:00",
  "max_capacity": 5,
  "is_active": true
}
```

**成功響應** (201 Created):
```json
{
  "success": true,
  "data": {
    "id": 1,
    "title": "早上時段",
    "max_capacity": 5,
    "current_bookings": 0
  },
  "message": "時段創建成功"
}
```

### 更新時段

**端點**: `PUT /available-times/{id}`

**請求體**:
```json
{
  "max_capacity": 10,
  "is_active": true
}
```

**成功響應** (200 OK):
```json
{
  "success": true,
  "data": {
    "id": 1,
    "max_capacity": 10
  },
  "message": "時段更新成功"
}
```

### 刪除時段

**端點**: `DELETE /available-times/{id}`

**成功響應** (204 No Content)

---

## ✅ 報到管理

**需要管理員權限**

### 客戶報到

**端點**: `POST /check-in/reservations/{id}/check-in`

**成功響應** (200 OK):
```json
{
  "success": true,
  "data": {
    "id": 1,
    "check_in_status": "checked_in",
    "check_in_time": "2025-10-25T10:05:00.000000Z"
  },
  "message": "報到成功"
}
```

### 標記爽約

**端點**: `POST /check-in/reservations/{id}/no-show`

**成功響應** (200 OK):
```json
{
  "success": true,
  "data": {
    "id": 1,
    "check_in_status": "no_show",
    "no_show": true
  },
  "message": "已標記為爽約"
}
```

### 記錄付款

**端點**: `POST /check-in/reservations/{id}/payment`

**請求體**:
```json
{
  "amount": 500,
  "method": "cash",
  "note": "已付現金"
}
```

**成功響應** (200 OK):
```json
{
  "success": true,
  "data": {
    "id": 1,
    "payment_status": "paid",
    "payment_amount": "500.00",
    "payment_method": "cash",
    "payment_time": "2025-10-25T11:00:00.000000Z"
  },
  "message": "付款記錄成功"
}
```

### 今日報到列表

**端點**: `GET /check-in/today`

**成功響應** (200 OK):
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "customer": {
        "name": "王小明"
      },
      "service": {
        "name": "剪髮服務"
      },
      "reservation_time": "10:00:00",
      "check_in_status": "checked_in",
      "payment_status": "paid"
    }
  ]
}
```

---

## 儀表板

**需要管理員權限**

### 統計數據

**端點**: `GET /dashboard/stats`

**成功響應** (200 OK):
```json
{
  "success": true,
  "data": {
    "today_reservations": 15,
    "pending_reservations": 5,
    "total_customers": 150,
    "monthly_revenue": "50000.00",
    "today_check_ins": 10,
    "no_shows_today": 1
  }
}
```

### 近期預約

**端點**: `GET /dashboard/reservations`

**查詢參數**:
- `limit` (integer): 返回數量 (預設: 10)

**成功響應** (200 OK):
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "customer_name": "王小明",
      "service_name": "剪髮服務",
      "reservation_date": "2025-10-25",
      "reservation_time": "10:00:00",
      "status": "confirmed"
    }
  ]
}
```

### 熱門服務

**端點**: `GET /dashboard/popular-services`

**成功響應** (200 OK):
```json
{
  "success": true,
  "data": [
    {
      "service_id": 1,
      "service_name": "剪髮服務",
      "reservation_count": 50,
      "total_revenue": "25000.00"
    }
  ]
}
```

---

##  系統設定

**需要管理員權限**

### 獲取所有設定

**端點**: `GET /settings`

**成功響應** (200 OK):
```json
{
  "success": true,
  "data": {
    "line_channel_access_token": "your_token",
    "line_channel_secret": "your_secret",
    "business_hours": {
      "monday": "09:00-18:00",
      "tuesday": "09:00-18:00"
    }
  }
}
```

### 更新設定

**端點**: `POST /settings`

**請求體**:
```json
{
  "key": "business_hours",
  "value": {
    "monday": "09:00-19:00"
  }
}
```

**成功響應** (200 OK):
```json
{
  "success": true,
  "message": "設定更新成功"
}
```

---

## 活動日誌

**需要管理員權限**

### 獲取日誌列表

**端點**: `GET /admin/activity-logs`

**查詢參數**:
- `page` (integer): 頁碼
- `per_page` (integer): 每頁數量
- `module` (string): 模組篩選
- `action` (string): 動作篩選
- `user_id` (integer): 用戶篩選

**成功響應** (200 OK):
```json
{
  "success": true,
  "data": {
    "data": [
      {
        "id": 1,
        "user": {
          "name": "Admin User"
        },
        "module": "customer",
        "action": "create",
        "description": "創建客戶: 王小明",
        "ip_address": "127.0.0.1",
        "created_at": "2025-10-23T10:00:00.000000Z"
      }
    ],
    "total": 100
  }
}
```

---

## ❌ 錯誤碼

### 常見錯誤

| 錯誤碼 | 說明 | 解決方法 |
|--------|------|----------|
| `UNAUTHORIZED` | 未認證 | 請先登入獲取 Token |
| `FORBIDDEN` | 無權限 | 需要管理員權限 |
| `VALIDATION_ERROR` | 驗證失敗 | 檢查請求參數 |
| `NOT_FOUND` | 資源不存在 | 確認資源 ID 正確 |
| `RATE_LIMIT_EXCEEDED` | 請求過於頻繁 | 稍後再試 |

### 驗證錯誤範例

```json
{
  "success": false,
  "message": "驗證失敗",
  "errors": {
    "email": [
      "email 欄位為必填。",
      "email 必須是有效的電子郵件地址。"
    ],
    "password": [
      "password 欄位為必填。"
    ]
  }
}
```

---

## 📌 注意事項

1. **Token 安全**: 請妥善保管 Token，不要在客戶端暴露
2. **HTTPS**: 生產環境務必使用 HTTPS
3. **Rate Limiting**: 遵守 API 請求頻率限制
4. **日期格式**: 所有日期使用 ISO 8601 格式 (Y-m-d H:i:s)
5. **時區**: 系統使用 Asia/Taipei 時區

## 🧪 測試工具

### Postman Collection

可匯入 Postman Collection 進行測試：

```bash
# 下載 Collection（如有提供）
curl -o line-reservation-api.postman_collection.json \
  https://your-domain.com/api-docs/postman
```

### cURL 範例

```bash
# 登入
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"password"}'

# 獲取客戶列表（需要 Token）
curl -X GET http://localhost:8000/api/customers \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"
```

---

**文件版本**: v1.0.0  
**最後更新**: 2025-10-23  
**維護者**: 傅盛祥 (Spencer Kuku)