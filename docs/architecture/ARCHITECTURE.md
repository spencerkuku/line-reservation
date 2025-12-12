# 多租戶 B2B 預約系統架構文件

## 目錄

- [系統概覽](#系統概覽)
- [多租戶架構](#多租戶架構)
- [技術架構](#技術架構)
- [前端架構](#前端架構)
- [後端架構](#後端架構)
- [資料流](#資料流)
- [第三方整合](#第三方整合)
- [安全架構](#安全架構)
- [部署架構](#部署架構)

## 系統概覽

多租戶 B2B LINE 預約系統採用現代化的前後端分離架構，透過 RESTful API 進行通訊。系統支援多個企業租戶獨立運營，每個租戶擁有獨立的資料空間和 LINE Bot 配置。

### 主要組件

1. **前端管理介面** (Vue.js SPA) - 企業管理後台
2. **後端 API 服務** (Laravel) - 多租戶業務邏輯
3. **LINE Messaging API** - 第三方整合服務
4. **多租戶管理系統** - 租戶生命週期管理

### 核心特性

- **多租戶架構**：完全隔離的企業資料空間
- **前後端分離**：獨立開發、部署、擴展
- **安全認證**：Laravel Sanctum + 租戶驗證
- **響應式設計**：跨設備支援
- **LINE Bot 整合**：每租戶獨立 Bot 配置
- **企業級安全**：資料加密與安全審計

##  架構圖

### 整體系統架構

```
┌─────────────────────────────────────────────────────────────────┐
│                         使用者層                                  │
├─────────────────────────────────────────────────────────────────┤
│                                                                   │
│  ┌──────────────────┐              ┌──────────────────┐        │
│  │   LINE 用戶端     │              │  Web 管理後台    │        │
│  │   (LINE App)     │              │  (Browser)       │        │
│  └────────┬─────────┘              └────────┬─────────┘        │
│           │                                  │                   │
└───────────┼──────────────────────────────────┼───────────────────┘
            │                                  │
            │ HTTPS                            │ HTTPS
            │                                  │
┌───────────▼──────────────────────────────────▼───────────────────┐
│                         應用層                                    │
├───────────────────────────────────────────────────────────────────┤
│                                                                   │
│  ┌──────────────────┐              ┌──────────────────┐        │
│  │  LINE Messaging  │              │   Vue.js SPA     │        │
│  │      API         │◄─────────────┤   (Frontend)     │        │
│  │   (Webhook)      │   Webhook    │                  │        │
│  └────────┬─────────┘              └────────┬─────────┘        │
│           │                                  │                   │
│           │ Push/Reply Messages              │ RESTful API       │
│           │                                  │                   │
│           ▼                                  ▼                   │
│  ┌──────────────────────────────────────────────────────┐       │
│  │              Laravel Backend                          │       │
│  │  ┌──────────┐  ┌──────────┐  ┌───────────────┐     │       │
│  │  │ LINE Bot │  │   API    │  │  Auth/Admin   │     │       │
│  │  │ Handler  │  │Controller│  │  Middleware   │     │       │
│  │  └──────────┘  └──────────┘  └───────────────┘     │       │
│  │  ┌──────────────────────────────────────────────┐  │       │
│  │  │         Business Logic Services               │  │       │
│  │  │  (ActivityLogger, LineBotService, etc.)      │  │       │
│  │  └──────────────────────────────────────────────┘  │       │
│  │  ┌──────────────────────────────────────────────┐  │       │
│  │  │            Eloquent ORM                       │  │       │
│  │  └──────────────────────────────────────────────┘  │       │
│  └─────────────────────────┬────────────────────────────┘      │
│                             │                                    │
└─────────────────────────────┼────────────────────────────────────┘
                              │
                              │ SQL
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                         資料層                                    │
├─────────────────────────────────────────────────────────────────┤
│                                                                   │
│  ┌──────────────────────────────────────────────────────┐       │
│  │                  MySQL Database                       │       │
│  │  ┌────────┐ ┌────────┐ ┌──────────┐ ┌──────────┐   │       │
│  │  │ Users  │ │Services│ │Customers │ │Reserv-   │   │       │
│  │  │        │ │        │ │          │ │ations    │   │       │
│  │  └────────┘ └────────┘ └──────────┘ └──────────┘   │       │
│  │  ┌────────────────┐ ┌─────────────┐ ┌──────────┐   │       │
│  │  │Available Times │ │   Settings  │ │   Logs   │   │       │
│  │  └────────────────┘ └─────────────┘ └──────────┘   │       │
│  └──────────────────────────────────────────────────────┘       │
│                                                                   │
└───────────────────────────────────────────────────────────────────┘
```

### 服務互動流程

```
┌──────────┐                                          ┌──────────┐
│          │  1. 發送訊息到 LINE Bot                   │          │
│  LINE    ├──────────────────────────────────────────►  LINE    │
│  User    │                                          │ Platform │
│          │  8. 接收回覆訊息                          │          │
│          ◄──────────────────────────────────────────┤          │
└──────────┘                                          └────┬─────┘
                                                           │
                                                           │ 2. Webhook
                                                           │    Event
                                                           ▼
┌──────────┐                                          ┌──────────┐
│          │  7. 推送訊息給用戶                        │          │
│  Admin   │                                          │  Laravel │
│  User    │  4. 管理預約/客戶                         │  Backend │
│          ├──────────────────────────────────────────►          │
│          │     (HTTP API Request)                   │          │
│          ◄──────────────────────────────────────────┤          │
└──────────┘  5. 返回資料 (JSON)                      └────┬─────┘
      ▲                                                    │
      │                                                    │ 3. 處理訊息
      │  6. 更新 UI                                        │    存儲資料
      │                                                    │
┌─────┴─────┐                                            ▼
│           │                                     ┌──────────────┐
│  Vue.js   │                                     │    MySQL     │
│  Frontend │                                     │   Database   │
│           │                                     │              │
└───────────┘                                     └──────────────┘
```

##  技術架構

### 技術棧概覽

| 層級 | 技術 | 版本 | 用途 |
|------|------|------|------|
| **前端** | Vue.js | 3.5.17 | 前端框架 |
| | Vite | 7.0.0 | 構建工具 |
| | Vue Router | 4.5.1 | 路由管理 |
| | Pinia | 3.0.3 | 狀態管理 |
| | Tailwind CSS | 3.4 | UI 框架 |
| | Axios | 1.10.0 | HTTP 客戶端 |
| **後端** | Laravel | 12.0 | 後端框架 |
| | PHP | 8.2+ | 程式語言 |
| | Laravel Sanctum | 4.1 | API 認證 |
| | LINE Bot SDK | 11.1 | LINE 整合 |
| **資料庫** | MySQL | 8.0+ | 關聯式資料庫 |
| **伺服器** | Apache | 2.4+ | Web 伺服器 |
| | PHP-FPM | 8.3 | PHP 處理器 |

### 環境配置

```
┌──────────────────────────────────────┐
│       Development Environment        │
├──────────────────────────────────────┤
│  Frontend: http://localhost:5173    │
│  Backend:  http://localhost:8000    │
│  Database: MySQL (localhost:3306)   │
└──────────────────────────────────────┘

┌──────────────────────────────────────┐
│       Production Environment         │
├──────────────────────────────────────┤
│  Frontend: https://your-domain.com   │
│  Backend:  https://your-domain.com   │
│  Database: MySQL (internal)          │
│  SSL:      Let's Encrypt             │
└──────────────────────────────────────┘
```

## 前端架構

### Vue.js 應用結構

```
frontend/
├── src/
│   ├── main.js                    # 應用入口
│   ├── App.vue                    # 根組件
│   ├── router.js                  # 路由配置
│   ├── style.css                  # 全局樣式
│   │
│   ├── pages/                     # 頁面組件
│   │   ├── Dashboard.vue          # 儀表板
│   │   ├── Login.vue              # 登入頁
│   │   ├── Customers.vue          # 客戶管理
│   │   ├── Reservations.vue       # 預約管理
│   │   ├── CheckIn.vue            # 報到管理
│   │   ├── Services.vue           # 服務管理
│   │   ├── AvailableTimes.vue     # 時段管理
│   │   ├── Settings.vue           # 系統設定
│   │   └── Profile.vue            # 個人資料
│   │
│   ├── components/                # 可重用組件
│   │   ├── AdminLayout.vue        # 後台佈局
│   │   ├── Sidebar.vue            # 側邊欄
│   │   ├── TopBar.vue             # 頂部導航
│   │   ├── Modal.vue              # 對話框
│   │   └── ...
│   │
│   ├── composables/               # 組合式函數
│   │   ├── useAuth.js             # 認證邏輯
│   │   ├── useApi.js              # API 調用
│   │   └── useNotification.js     # 通知邏輯
│   │
│   ├── utils/                     # 工具函數
│   │   ├── api.js                 # API 配置
│   │   ├── constants.js           # 常數定義
│   │   └── helpers.js             # 輔助函數
│   │
│   └── assets/                    # 靜態資源
│       ├── images/
│       └── icons/
```

### 前端路由架構

```javascript
Router Structure:
├── / (AdminLayout)
│   ├── /                         # Dashboard
│   ├── /customers                # 客戶管理
│   ├── /check-in                 # 報到管理
│   ├── /services                 # 服務管理
│   ├── /available-times          # 時段管理
│   ├── /reservations             # 預約管理
│   ├── /profile                  # 個人資料
│   └── /settings                 # 系統設定
├── /login                        # 登入頁（無佈局）
└── /* (404)                      # 404 頁面
```

### 狀態管理

使用 **Pinia** 進行狀態管理：

```javascript
Stores:
├── authStore               # 認證狀態
│   ├── user               # 當前用戶
│   ├── token              # API Token
│   └── isAuthenticated    # 認證狀態
│
├── reservationStore        # 預約狀態
│   ├── reservations       # 預約列表
│   └── filters            # 篩選條件
│
└── customerStore           # 客戶狀態
    ├── customers          # 客戶列表
    └── selectedCustomer   # 當前選中客戶
```

## 後端架構

### Laravel 應用結構

```
backend/
├── app/
│   ├── Console/
│   │   └── Commands/              # 自訂命令
│   │
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── Api/               # API 控制器
│   │   │       ├── AuthController.php
│   │   │       ├── CustomerController.php
│   │   │       ├── ReservationController.php
│   │   │       ├── ServiceController.php
│   │   │       ├── AvailableTimeController.php
│   │   │       ├── CheckInController.php
│   │   │       ├── DashboardController.php
│   │   │       ├── SettingController.php
│   │   │       └── LineWebhookController.php
│   │   │
│   │   ├── Middleware/            # 中間件
│   │   │   ├── AdminMiddleware.php
│   │   │   ├── VerifyLineSignature.php
│   │   │   └── ...
│   │   │
│   │   └── Requests/              # 表單驗證
│   │       ├── LoginRequest.php
│   │       ├── StoreReservationRequest.php
│   │       └── ...
│   │
│   ├── Models/                    # Eloquent 模型
│   │   ├── User.php
│   │   ├── Customer.php
│   │   ├── Reservation.php
│   │   ├── Service.php
│   │   ├── AvailableTime.php
│   │   ├── Setting.php
│   │   ├── AdminActivityLog.php
│   │   └── LineMessageLog.php
│   │
│   ├── Observers/                 # 模型觀察者
│   │   └── ReservationObserver.php
│   │
│   ├── Providers/                 # 服務提供者
│   │   └── AppServiceProvider.php
│   │
│   └── Services/                  # 業務邏輯服務
│       ├── LineBotService.php     # LINE Bot 服務
│       ├── ActivityLogger.php     # 活動日誌
│       ├── CryptographyService.php
│       ├── DataIntegrityService.php
│       ├── LoggingService.php
│       ├── SecureHttpClientService.php
│       └── SecurityLoggingService.php
│
├── config/                        # 配置文件
│   ├── app.php
│   ├── database.php
│   ├── linebot.php                # LINE Bot 配置
│   ├── security.php               # 安全配置
│   └── ...
│
├── database/
│   ├── migrations/                # 資料庫遷移
│   └── seeders/                   # 資料填充
│
└── routes/                        # 路由定義
    ├── api.php                    # API 路由
    ├── web.php                    # Web 路由
    └── auth.php                   # 認證路由
```

### API 路由結構

```
API Routes (/api/):
├── Public Routes
│   ├── POST   /auth/login                    # 登入
│   ├── POST   /line/webhook                  # LINE Webhook
│   ├── GET    /services                      # 服務列表
│   ├── GET    /available-times               # 可用時段
│   └── POST   /frontend-logs                 # 前端日誌
│
├── Authenticated Routes (auth:sanctum)
│   ├── POST   /auth/logout                   # 登出
│   ├── GET    /auth/user                     # 用戶資訊
│   ├── POST   /auth/profile                  # 更新資料
│   └── POST   /auth/password                 # 更改密碼
│
└── Admin Routes (auth:sanctum + admin)
    ├── Dashboard
    │   ├── GET    /dashboard/stats           # 統計數據
    │   ├── GET    /dashboard/reservations    # 預約概覽
    │   └── GET    /dashboard/popular-services # 熱門服務
    │
    ├── Customers
    │   ├── GET    /customers                 # 列表
    │   ├── POST   /customers                 # 新增
    │   ├── GET    /customers/{id}            # 詳情
    │   ├── PUT    /customers/{id}            # 更新
    │   ├── DELETE /customers/{id}            # 刪除
    │   ├── POST   /customers/{id}/block      # 封鎖
    │   └── POST   /customers/{id}/unblock    # 解除封鎖
    │
    ├── Reservations
    │   ├── GET    /reservations              # 列表
    │   ├── POST   /reservations              # 新增
    │   ├── PUT    /reservations/{id}/confirm # 確認
    │   └── PUT    /reservations/{id}/cancel  # 取消
    │
    ├── Services
    │   ├── POST   /services                  # 新增
    │   ├── PUT    /services/{id}             # 更新
    │   └── DELETE /services/{id}             # 刪除
    │
    ├── Available Times
    │   ├── POST   /available-times           # 新增
    │   ├── PUT    /available-times/{id}      # 更新
    │   └── DELETE /available-times/{id}      # 刪除
    │
    ├── Check-in
    │   ├── POST   /check-in/reservations/{id}/check-in   # 報到
    │   ├── POST   /check-in/reservations/{id}/no-show    # 爽約
    │   ├── POST   /check-in/reservations/{id}/payment    # 付款
    │   └── GET    /check-in/today                        # 今日報到
    │
    ├── Settings
    │   ├── GET    /settings                  # 所有設定
    │   ├── POST   /settings                  # 更新設定
    │   ├── GET    /settings/line             # LINE 設定
    │   └── POST   /settings/line             # 更新 LINE 設定
    │
    └── Activity Logs
        ├── GET    /admin/activity-logs       # 日誌列表
        ├── GET    /admin/activity-logs/stats # 統計
        └── GET    /admin/activity-logs/{id}  # 詳情
```

### 服務層架構

```
Services Layer:
├── LineBotService
│   ├── handleMessage()           # 處理 LINE 訊息
│   ├── sendMessage()             # 發送訊息
│   ├── pushMessage()             # 推播訊息
│   └── replyMessage()            # 回覆訊息
│
├── ActivityLogger
│   ├── log()                     # 記錄活動
│   ├── logCustomerAction()       # 客戶操作
│   └── logReservationAction()    # 預約操作
│
├── CryptographyService
│   ├── encrypt()                 # 加密
│   └── decrypt()                 # 解密
│
└── DataIntegrityService
    ├── validateData()            # 驗證資料
    └── sanitizeData()            # 清理資料
```

## 資料流

### 預約流程資料流

```
1. 用戶透過 LINE 發起預約
   ┌─────────┐
   │ LINE    │ ─── 訊息 ───►
   │ User    │
   └─────────┘

2. LINE Platform 接收並轉發
   ┌─────────┐
   │  LINE   │ ─── Webhook Event ───►
   │Platform │
   └─────────┘

3. Laravel 處理 Webhook
   ┌─────────────────────┐
   │ LineWebhookController│
   │  ↓                   │
   │ LineBotService      │
   │  ↓                   │
   │ 解析訊息內容         │
   │  ↓                   │
   │ 查詢可用時段         │
   │  ↓                   │
   │ 建立預約記錄         │
   └─────────────────────┘
            │
            ▼
   ┌─────────────────────┐
   │  MySQL Database     │
   │  ↓                   │
   │ reservations 表      │
   │ customers 表         │
   │ line_message_logs 表 │
   └─────────────────────┘
            │
            ▼
4. 回覆用戶確認訊息
   ┌─────────┐
   │  LINE   │ ◄─── Push Message ───
   │ User    │
   └─────────┘

5. 管理員在後台查看
   ┌─────────┐
   │ Admin   │ ─── API Request ───►
   │ Frontend│
   └─────────┘
            │
            ▼
   ┌─────────────────────┐
   │ ReservationController│
   │  ↓                   │
   │ 查詢預約列表         │
   │  ↓                   │
   │ 返回 JSON 資料       │
   └─────────────────────┘
            │
            ▼
   ┌─────────┐
   │ Vue.js  │ ◄─── JSON Response ───
   │ UI      │
   └─────────┘
```

### 認證流程

```
1. 登入請求
   Frontend ──► POST /api/auth/login
                {email, password}
                        │
                        ▼
                 AuthController
                        │
                  驗證憑證
                        │
                  生成 Token
                        │
                        ▼
   Frontend ◄── {user, token}

2. 後續 API 請求
   Frontend ──► GET /api/customers
                Header: Authorization: Bearer {token}
                        │
                        ▼
                  Sanctum 中間件
                        │
                  驗證 Token
                        │
                  載入 User
                        │
                        ▼
                  Controller
                        │
                  處理請求
                        │
                        ▼
   Frontend ◄── JSON Response
```

## 第三方整合

### LINE Messaging API

#### Webhook 設定

```
Webhook URL: https://your-domain.com/api/line/webhook
HTTP Method: POST
Content-Type: application/json
Signature Verification: X-Line-Signature Header
```

#### 訊息類型處理

```php
支援的訊息類型:
├── text          # 文字訊息
├── sticker       # 貼圖
├── image         # 圖片
├── location      # 位置
├── postback      # 按鈕回傳
└── follow        # 用戶關注事件
```

#### LINE Bot 互動流程

```
用戶操作             LINE Bot 回應              後端處理
───────────────────────────────────────────────────────
發送 "預約"     →   顯示服務選單          →   查詢 services 表
                    (Quick Reply)

選擇服務        →   顯示可用時段          →   查詢 available_times 表
                    (Template Message)

選擇時段        →   要求確認資料          →   準備建立預約
                    (Confirm Template)

確認預約        →   預約成功通知          →   建立 reservation 記錄
                    (Text + 預約編號)          發送確認訊息
```

### 資料庫整合

#### MySQL 連線配置

```php
DB Connection Pool:
├── Max Connections: 100
├── Connection Timeout: 10s
├── Charset: utf8mb4
├── Collation: utf8mb4_unicode_ci
└── Timezone: Asia/Taipei
```

## 安全架構

### 安全層級

```
┌─────────────────────────────────────────────┐
│         Application Security Layers          │
├─────────────────────────────────────────────┤
│                                              │
│  Layer 1: Network Security                  │
│  ├── HTTPS/TLS 1.3                          │
│  ├── Firewall (UFW)                         │
│  └── Rate Limiting                          │
│                                              │
│  Layer 2: Authentication & Authorization    │
│  ├── Laravel Sanctum (Token-based)          │
│  ├── Password Hashing (bcrypt)              │
│  └── Role-based Access Control              │
│                                              │
│  Layer 3: Data Security                     │
│  ├── SQL Injection Protection (PDO)         │
│  ├── XSS Prevention (DOMPurify)             │
│  ├── CSRF Protection (Token)                │
│  └── Data Encryption (AES-256)              │
│                                              │
│  Layer 4: Application Logic                 │
│  ├── Input Validation                       │
│  ├── Output Sanitization                    │
│  ├── Business Logic Verification            │
│  └── Activity Logging                       │
│                                              │
│  Layer 5: Monitoring & Auditing             │
│  ├── Error Logging                          │
│  ├── Access Logging                         │
│  ├── Admin Activity Logs                    │
│  └── Security Event Monitoring              │
│                                              │
└─────────────────────────────────────────────┘
```

### 認證與授權

```
Role Hierarchy:
├── Admin
│   ├── 完整系統存取權
│   ├── 所有管理功能
│   ├── 用戶管理
│   └── 系統設定
│
└── User
    ├── 基本資訊查看
    ├── 個人資料管理
    └── 無後台存取權
```

### API 安全措施

| 安全措施 | 實作方式 | 保護對象 |
|----------|----------|----------|
| **Rate Limiting** | Laravel Throttle | 防止 API 濫用 |
| **CORS** | Laravel CORS | 跨域請求控制 |
| **Token 認證** | Sanctum | API 存取驗證 |
| **Input Validation** | Form Requests | 防止無效資料 |
| **SQL Injection** | Eloquent ORM | 資料庫安全 |
| **XSS Protection** | DOMPurify | 前端資料清理 |
| **CSRF Protection** | Laravel CSRF | 跨站請求偽造 |

## 部署架構

### 生產環境架構

```
┌─────────────────────────────────────────────────────────┐
│                      Internet                            │
└────────────────────────┬────────────────────────────────┘
                         │ HTTPS (443)
                         ▼
┌─────────────────────────────────────────────────────────┐
│                  Firewall (UFW)                          │
│         Allow: 80, 443, 22 (SSH)                        │
└────────────────────────┬────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────┐
│              Apache Web Server (2.4+)                    │
│  ┌──────────────────────────────────────────────────┐  │
│  │  VirtualHost: your-domain.com                     │  │
│  │  ├── DocumentRoot: /var/www/line-reservation/    │  │
│  │  │                  frontend/dist                 │  │
│  │  │                                                 │  │
│  │  ├── /api/* → Laravel Backend                     │  │
│  │  │    (PHP-FPM via proxy)                         │  │
│  │  │                                                 │  │
│  │  └── /storage/* → Laravel Storage                 │  │
│  └──────────────────────────────────────────────────┘  │
└────────────────────────┬────────────────────────────────┘
                         │
         ┌───────────────┴───────────────┐
         │                               │
         ▼                               ▼
┌────────────────────┐         ┌────────────────────┐
│   PHP-FPM 8.3      │         │   MySQL 8.0        │
│   Laravel App      │◄────────│   Database         │
│                    │  Query  │                    │
│  ┌──────────────┐ │         │  ┌──────────────┐ │
│  │ .env config  │ │         │  │ line_        │ │
│  │ storage/     │ │         │  │ reservation  │ │
│  │ logs/        │ │         │  │ DB           │ │
│  └──────────────┘ │         │  └──────────────┘ │
└────────────────────┘         └────────────────────┘
```

### 檔案結構部署

```
Production Server:
/var/www/line-reservation/
├── backend/
│   ├── app/
│   ├── config/
│   ├── database/
│   ├── public/          # Laravel 入口
│   ├── routes/
│   ├── storage/         # 日誌、快取、上傳
│   │   ├── logs/
│   │   ├── framework/
│   │   └── app/
│   └── .env             # 環境變數 (生產)
│
└── frontend/
    └── dist/            # 編譯後的前端檔案
        ├── index.html
        ├── assets/
        │   ├── js/
        │   └── css/
        └── storage → ../../backend/storage/app/public
```

### 負載與效能

```
預期負載:
├── 並發用戶: 100-500
├── API 請求/秒: 50-200
├── 資料庫連線池: 20-50
└── 回應時間: <500ms

效能優化:
├── Laravel OPcache: 啟用
├── Query Cache: 啟用
├── Frontend Compression: Gzip/Brotli
├── Static Assets: CDN (可選)
└── Database Indexes: 已優化
```

## 擴展性考量

### 水平擴展

未來可考慮的擴展方案：

```
1. 負載平衡
   ┌────────────┐
   │   Nginx    │
   │   Load     │
   │  Balancer  │
   └──────┬─────┘
          │
    ┌─────┴─────┐
    │           │
    ▼           ▼
┌───────┐   ┌───────┐
│ App 1 │   │ App 2 │
└───────┘   └───────┘

2. 資料庫分離
   ┌─────────────┐
   │   Master    │ (Write)
   │   MySQL     │
   └──────┬──────┘
          │
    ┌─────┴─────┐
    │           │
    ▼           ▼
┌───────┐   ┌───────┐
│Slave 1│   │Slave 2│
│(Read) │   │(Read) │
└───────┘   └───────┘

3. 快取層
   ┌────────────┐
   │   Redis    │
   │   Cache    │
   └────────────┘
        │
        ▼
   Session / Cache / Queue
```

### 監控與維護

```
Monitoring Stack:
├── Application Logs
│   └── storage/logs/laravel.log
├── Web Server Logs
│   ├── /var/log/apache2/access.log
│   └── /var/log/apache2/error.log
├── Database Logs
│   └── /var/log/mysql/error.log
└── Activity Logs
    └── admin_activity_logs 表
```

## 技術決策記錄

### 為何選擇此架構？

| 決策 | 理由 |
|------|------|
| **前後端分離** | 獨立開發、部署靈活、易於擴展 |
| **Laravel** | 成熟穩定、生態豐富、開發效率高 |
| **Vue.js 3** | 響應式、組件化、效能優秀 |
| **MySQL** | ACID 保證、關聯完整、成熟可靠 |
| **Sanctum** | 輕量級、適合 SPA、易於整合 |
| **Tailwind CSS** | 快速開發、一致性高、易於維護 |

---

**文件版本**: v1.0.0  
**最後更新**: 2025-10-23  
**維護者**: 傅盛祥 (Spencer Kuku)
