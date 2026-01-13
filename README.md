# LINE 預約系統 - 多租戶 B2B 平台

> 基於 Laravel 12 + Vue.js 3 的企業級多租戶預約管理系統

## 專案概述

這是一個現代化的 SaaS 多租戶預約管理平台，深度整合 LINE Messaging API，為企業提供完整的線上預約解決方案。系統支援多企業獨立運營，每個租戶擁有獨立的 LINE Bot、服務項目、時段管理和客戶資料。

### 核心功能

#### 多租戶架構
- 完整的租戶隔離機制 (Tenant Scope)
- 支援無限數量的企業租戶
- 獨立的 LINE Channel 配置
- 租戶級別的資料隔離與安全性
- Webhook Token 驗證機制

#### LINE Bot 自動化
- 完整的 LINE Messaging API 整合
- 智能對話流程管理
- 預約建立、查詢、修改、取消
- Flex Message 互動式卡片
- Quick Reply 快速選單
- Rich Menu 豐富選單支援
- 即時訊息推播與回覆

#### 預約管理系統
- 彈性的服務項目設定
- 可重複時段配置 (每日/每週)
- 虛擬時段智能分配
- 自動/手動預約確認模式
- 預約狀態追蹤 (pending/confirmed/cancelled/completed)
- 客戶資料快照保存
- 軟刪除機制

#### 管理後台
- Vue.js 3 + Tailwind CSS 響應式介面
- 儀表板數據統計與圖表
- 租戶管理 (System Admin)
- 服務項目與時段管理
- 客戶與預約記錄管理
- LINE 訊息日誌查詢
- 操作日誌審計追蹤
- 系統設定與權限控制

#### 企業級功能
- RESTful API (Laravel Sanctum 認證)
- 資料加密服務 (CryptographyService)
- 資料完整性驗證 (DataIntegrityService)
- 活動日誌記錄 (ActivityLogger)
- 效能監控與日誌壓縮
- 完整的單元測試與整合測試

## 技術棧

### 後端技術
- **框架**: Laravel 12 (PHP 8.2+)
- **資料庫**: MySQL 8.0+ / MariaDB 10.6+
- **認證**: Laravel Sanctum (SPA + API Token)
- **API 整合**: LINE Messaging API SDK v11.1
- **快取**: Redis / File-based Cache
- **佇列**: Laravel Queue (Database Driver)

### 前端技術
- **框架**: Vue.js 3.5 (Composition API)
- **建置工具**: Vite 6.0
- **UI 框架**: Tailwind CSS 3.4
- **元件庫**: Headless UI, Heroicons
- **狀態管理**: Pinia 3.0
- **路由**: Vue Router 4.5
- **HTTP 客戶端**: Axios 1.10
- **日曆元件**: FullCalendar 6.1

### 開發與部署
- **Web 伺服器**: Apache 2.4+ / Nginx 1.18+
- **PHP 處理**: PHP-FPM 8.2/8.3
- **SSL**: Let's Encrypt (Certbot)
- **版本控制**: Git
- **測試**: PHPUnit 11.5
- **部署工具**: 自製模組化 Bash 腳本系統 (5,400+ 行)

## 專案文件

完整的技術文件位於 [`docs/`](docs/) 目錄下：

### 架構文件 (Architecture)
- [ARCHITECTURE.md](docs/architecture/ARCHITECTURE.md) - 系統整體架構、多租戶設計、服務層架構
- [DATABASE.md](docs/architecture/DATABASE.md) - 資料庫 Schema、關聯設計、索引策略
- [FRONTEND.md](docs/architecture/FRONTEND.md) - 前端架構、元件設計、狀態管理
- [UI_DESIGN_GUIDE.md](docs/architecture/UI_DESIGN_GUIDE.md) - UI/UX 設計規範

### API 文件 (API Documentation)
- [API_DOCS.md](docs/api/API_DOCS.md) - RESTful API 完整接口文件

### 開發指南 (Development)
- [CONTRIBUTING.md](docs/development/CONTRIBUTING.md) - 貢獻指南、程式碼規範、Git 工作流程
- [TESTING.md](docs/development/TESTING.md) - 測試策略、PHPUnit 測試、測試涵蓋率

### 部署文件 (Deployment)
- [cicd/README.md](docs/deployment/cicd/README.md) - 完整的部署系統文件
- [cicd/deploy.sh](docs/deployment/cicd/deploy.sh) - 主部署腳本 (支援統一模式/API Only 模式)
- [cicd/manage.sh](docs/deployment/cicd/manage.sh) - 生產環境管理控制台
- [cicd/lib/](docs/deployment/cicd/lib/) - 11 個模組化腳本 (core, system, db, git, backend, frontend, apache, ssl, backup, cloudflare, menu)

### 維護文件 (Maintenance)
- [MAINTENANCE.md](docs/maintenance/MAINTENANCE.md) - 系統維護、故障排除、效能優化

## 快速開始

### 系統需求

**伺服器環境**
- Ubuntu 20.04 LTS / 22.04 LTS (推薦) 或 Debian 11/12
- 最低配置: 2 CPU, 4GB RAM, 20GB SSD
- 推薦配置: 4 CPU, 8GB RAM, 40GB SSD

**軟體需求**
- PHP 8.2+ (推薦 8.3) + FPM
- MySQL 8.0+ 或 MariaDB 10.6+
- Apache 2.4+ 或 Nginx 1.18+
- Node.js 18+ (統一模式) 或不需要 (API Only 模式)
- Composer 2.x
- Git 2.x

### 安裝步驟

#### 1. 環境準備

```bash
# 克隆專案
git clone https://github.com/spencerkuku/line-reservation.git
cd line-reservation

# 進入部署腳本目錄
cd docs/deployment/cicd

# 賦予執行權限
chmod +x deploy.sh manage.sh lib/*.sh
```

#### 2. 選擇部署模式

系統支援兩種部署模式：

**統一模式 (Unified)** - 前後端同伺服器
```bash
./deploy.sh
# 選擇 [1] 統一部署
```

**API Only 模式 (Headless)** - 後端 API + Cloudflare Pages 前端
```bash
./deploy.sh
# 選擇 [2] 純後端 API 部署
```

詳細說明請參閱 [cicd/README.md](docs/deployment/cicd/README.md)

#### 3. 配置 LINE Channel

1. 前往 [LINE Developers Console](https://developers.line.biz/)
2. 建立 Messaging API Channel
3. 取得 Channel Secret 和 Channel Access Token
4. 在管理後台設定 LINE 憑證 (租戶管理 → 編輯租戶)
5. 設定 Webhook URL: `https://your-domain.com/api/line/webhook/{tenant_id}/{webhook_token}`

#### 4. 初始化資料

```bash
cd /var/www/line-reservation/backend

# 執行資料庫遷移
php artisan migrate

# 匯入測試資料 (選用)
php artisan db:seed --class=MultiTenantSeeder

# 建立 System Admin 帳號
php artisan tinker
>>> User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => bcrypt('password'), 'role' => 'system_admin'])
```

### 日常維護

```bash
cd /var/www/line-reservation/docs/deployment/cicd

# 快速更新 (Git Pull + 重建快取)
./manage.sh update

# 清除快取
./manage.sh cache

# 重啟服務
./manage.sh restart

# 查看系統狀態
./manage.sh status

# 進入互動式管理選單
./manage.sh
```

### 日常維護

```bash
cd /var/www/line-reservation/docs/deployment/cicd

# 快速更新 (Git Pull + 重建快取)
./manage.sh update

# 清除快取
./manage.sh cache

# 重啟服務
./manage.sh restart

# 查看系統狀態
./manage.sh status

# 進入互動式管理選單
./manage.sh
```

## 專案結構

```
line-reservation/
├── backend/                    # Laravel 後端
│   ├── app/
│   │   ├── Console/           # Artisan 指令
│   │   ├── Http/
│   │   │   ├── Controllers/   # API 控制器
│   │   │   │   └── Api/       # RESTful API
│   │   │   └── Middleware/    # 中間件 (認證、租戶識別)
│   │   ├── Models/            # Eloquent 模型
│   │   │   ├── Scopes/        # Global Scopes (TenantScope)
│   │   │   └── Traits/        # Model Traits
│   │   ├── Observers/         # 模型觀察者
│   │   └── Services/          # 業務邏輯服務
│   │       ├── LineBotService.php      # LINE Bot 核心服務
│   │       ├── ActivityLogger.php      # 活動日誌
│   │       ├── CryptographyService.php # 加密服務
│   │       └── DataIntegrityService.php # 資料完整性
│   ├── database/
│   │   ├── migrations/        # 資料庫遷移
│   │   ├── seeders/           # 資料填充
│   │   └── factories/         # 模型工廠
│   ├── routes/
│   │   ├── api.php            # API 路由
│   │   ├── web.php            # Web 路由
│   │   └── auth.php           # 認證路由
│   ├── tests/                 # 測試檔案
│   │   ├── Feature/           # 功能測試
│   │   └── Unit/              # 單元測試
│   └── composer.json          # PHP 依賴
│
├── frontend/                   # Vue.js 前端
│   ├── src/
│   │   ├── components/        # Vue 元件
│   │   ├── composables/       # Composition API
│   │   ├── pages/             # 頁面元件
│   │   │   ├── Dashboard.vue  # 儀表板
│   │   │   ├── Tenants.vue    # 租戶管理
│   │   │   ├── Services.vue   # 服務項目
│   │   │   ├── AvailableTimes.vue # 時段管理
│   │   │   ├── Reservations.vue # 預約管理
│   │   │   └── ...
│   │   ├── utils/             # 工具函數
│   │   ├── router.js          # 路由配置
│   │   └── main.js            # 應用入口
│   ├── public/                # 靜態資源
│   ├── package.json           # NPM 依賴
│   └── vite.config.js         # Vite 配置
│
└── docs/                       # 專案文件
    ├── api/                   # API 文件
    ├── architecture/          # 架構文件
    ├── deployment/            # 部署文件
    │   └── cicd/              # CI/CD 腳本系統
    │       ├── deploy.sh      # 主部署腳本
    │       ├── manage.sh      # 管理控制台
    │       ├── config.sh      # 全域配置
    │       └── lib/           # 模組化腳本
    │           ├── core.sh    # 核心函數
    │           ├── system.sh  # 系統套件
    │           ├── db.sh      # 資料庫管理
    │           ├── git.sh     # Git 操作
    │           ├── backend.sh # Laravel 部署
    │           ├── frontend.sh # Vue 建置
    │           ├── apache.sh  # Apache 配置
    │           ├── ssl.sh     # SSL 憑證
    │           ├── backup.sh  # 備份還原
    │           ├── cloudflare.sh # Cloudflare 整合
    │           └── menu.sh    # 選單 UI
    ├── development/           # 開發文件
    └── maintenance/           # 維護文件
```

## 測試

```bash
cd backend

# 執行所有測試
php artisan test

# 執行特定測試套件
php artisan test --testsuite=Feature

# 執行單一測試
php artisan test --filter=TenantIsolationTest

# 產生覆蓋率報告
php artisan test --coverage
```

## 安全性

- Laravel Sanctum SPA 認證
- CSRF 保護
- SQL Injection 防護 (Eloquent ORM)
- XSS 防護 (DOMPurify)
- 敏感資料加密 (CryptographyService)
- LINE Signature 驗證
- Webhook Token 驗證
- 租戶資料隔離 (Global Scope)
- 操作日誌審計
- 安全標頭設定
- Rate Limiting

## 效能優化

- Redis/File-based 快取系統
- Database Query 優化與索引
- Eager Loading 減少 N+1 查詢
- API Response 快取
- Vite 前端資源優化
- PHP-FPM 與 Apache/Nginx 調校
- 舊日誌自動壓縮

## 授權

此專案為個人使用專案，保留所有權利。未經許可，請勿用於商業用途或公開發布。

## 聯絡資訊

- 專案維護者: Spencer Ku
- GitHub: [@spencerkuku](https://github.com/spencerkuku)
- 專案網址: [https://github.com/spencerkuku/line-reservation](https://github.com/spencerkuku/line-reservation)

## 致謝

- [Laravel](https://laravel.com/) - 優雅的 PHP 框架
- [Vue.js](https://vuejs.org/) - 漸進式 JavaScript 框架
- [LINE Developers](https://developers.line.biz/) - LINE Messaging API
- [Tailwind CSS](https://tailwindcss.com/) - 實用優先的 CSS 框架
- [FullCalendar](https://fullcalendar.io/) - 全功能日曆元件

---

**專案版本**: 2.0  
**最後更新**: 2026-01-13

