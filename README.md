# LINE Reservation Platform

以 Laravel 12、Vue 3 與 LINE Messaging API 打造的多租戶預約管理平台。

> Portfolio archive: 這是曾實際開發的作品集專案，目前未提供線上服務，也不再作為商業系統持續營運。程式碼公開用於展示系統設計、全端開發與部署自動化能力。

## Project Highlights

- **Multi-tenant SaaS architecture**：透過 middleware、Eloquent global scope 與 `tenant_id` 實作租戶識別及資料隔離。
- **LINE Bot workflow**：支援 webhook 驗證、服務與時段選擇、預約建立、查詢、修改、取消及訊息紀錄。
- **Operations dashboard**：提供租戶、使用者、服務、時段、預約、客戶、報到與活動日誌管理。
- **Security controls**：Laravel Sanctum、LINE signature 驗證、憑證加密、rate limiting、安全標頭及稽核日誌。
- **Deployment automation**：包含 Apache、PHP-FPM、MySQL、SSL、備份及 Cloudflare Pages 的模組化 Bash 腳本。

## Architecture

```text
LINE Messaging API
        |
        v
Laravel API (Sanctum + tenant middleware)
        |
        +-- MySQL / MariaDB
        +-- Cache and queue
        |
Vue 3 administration dashboard
```

主要租戶資料模型會套用 `TenantScope`，管理 API 則透過認證使用者建立目前租戶 context。LINE webhook 使用每個租戶獨立的 UUID token，再以該租戶的加密 Channel Secret 驗證簽章。

更完整的設計說明：

- [系統架構](docs/architecture/ARCHITECTURE.md)
- [資料庫設計](docs/architecture/DATABASE.md)
- [前端架構](docs/architecture/FRONTEND.md)
- [API 文件](docs/api/API_DOCS.md)
- [部署工具](docs/deployment/cicd/README.md)

## Tech Stack

| Area | Technologies |
| --- | --- |
| Backend | PHP 8.2+, Laravel 12, Laravel Sanctum, Eloquent ORM |
| Frontend | Vue 3, Vue Router, Pinia, Tailwind CSS, Vite |
| Integration | LINE Messaging API SDK, Flex Message, Webhook |
| Data | MySQL 8+ / MariaDB 10.6+, database queue, file/database cache |
| Quality | PHPUnit, Laravel Pint, ESLint, Prettier |
| Deployment | Bash, Apache, PHP-FPM, Let's Encrypt, Cloudflare Pages |

## Core Modules

- 系統管理員與租戶管理
- 租戶管理員及權限控管
- 服務項目與可預約時段
- 預約生命週期與報到管理
- 客戶資料及互動紀錄
- LINE Bot 對話與訊息日誌
- 訂閱狀態、操作稽核與系統監控

## Local Development

### Prerequisites

- PHP 8.2+
- Composer 2
- MySQL 8+ 或 MariaDB 10.6+
- Node.js 18+ 與 npm 8+

### Backend

```bash
cd backend
cp .env.example .env
composer install
php artisan key:generate
```

在 `.env` 設定資料庫連線後：

```bash
php artisan migrate
php artisan serve
```

需要本機展示資料時可執行 `php artisan db:seed`。Seeder 會建立固定的示範帳號，因此**不可直接用於 production**。

### Frontend

```bash
cd frontend
cp .env.example .env
npm ci
npm run dev
```

預設前端位於 `http://localhost:5173`，API 位於 `http://localhost:8000/api`。不同環境請透過 `VITE_API_BASE_URL` 設定。

### LINE Integration

1. 在 [LINE Developers Console](https://developers.line.biz/) 建立 Messaging API Channel。
2. 於租戶設定頁輸入 Channel Access Token 與 Channel Secret。
3. 將後台產生的 UUID webhook URL 設定至 LINE Console。

請勿將真實 LINE 憑證、資料庫密碼或 production `.env` 提交至 Git。

## Testing and Quality

後端目前包含多租戶隔離、登入權限與 webhook 租戶識別等 Feature tests：

```bash
cd backend
php artisan test
./vendor/bin/pint --test
```

前端可執行：

```bash
cd frontend
npm run build
npm run lint
```

目前測試集中於關鍵的多租戶邊界，尚未涵蓋所有管理介面與完整 LINE 對話流程。

## Repository Structure

```text
.
├── backend/     # Laravel API、models、services、migrations、tests
├── frontend/    # Vue administration dashboard
└── docs/        # Architecture、API、testing、deployment documentation
```

## Public Repository Notes

- 此 repository 不包含 production credentials 或使用者資料。
- `.env.example` 僅提供 placeholder，部署前必須逐項檢查。
- 部署腳本來自原專案環境，公開展示時保留作為 DevOps 實作範例；在新主機使用前應先於 staging 驗證。
- 專案目前沒有 live demo，畫面與流程可從前端程式碼及 `docs/` 文件了解。

## License

Copyright (c) Spencer Ku. All rights reserved.

目前未提供開源授權；公開 repository 代表程式碼可供閱讀與作品集評估，不代表授予複製、散布或商業使用權。

