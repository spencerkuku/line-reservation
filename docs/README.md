# Project Documentation

LINE Reservation Platform 的技術文件入口。

> Portfolio archive: 專案目前沒有營運中的正式環境。文件依 repository 在 2026-06-13 的程式碼整理，部署內容保留作為工程實作紀錄，重新使用前必須在 staging 驗證。

## Documentation Map

### Architecture

- [System Architecture](architecture/ARCHITECTURE.md)：系統邊界、多租戶設計、資料流與安全模型。
- [Database](architecture/DATABASE.md)：主要資料表、關聯、租戶隔離及索引策略。
- [Frontend](architecture/FRONTEND.md)：Vue 應用結構、路由、API client 與建置方式。
- [UI Design Guide](architecture/UI_DESIGN_GUIDE.md)：目前介面使用的視覺規範與元件模式。

### API

- [API Reference](api/API_DOCS.md)：認證方式、權限群組及 `routes/api.php` 的端點清單。

### Development

- [Contributing](development/CONTRIBUTING.md)：本機環境、程式碼規範與變更流程。
- [Testing](development/TESTING.md)：目前可執行的測試、靜態檢查與手動驗證清單。

### Operations

- [Deployment Scripts](deployment/cicd/README.md)：封存的 Apache、PHP-FPM、MySQL、SSL 與 Cloudflare Pages 自動化。
- [Maintenance](maintenance/MAINTENANCE.md)：備份、日誌、健康檢查及故障排除。

## Suggested Reading

新讀者：

1. [根目錄 README](../README.md)
2. [System Architecture](architecture/ARCHITECTURE.md)
3. [Frontend](architecture/FRONTEND.md)
4. [API Reference](api/API_DOCS.md)

維護程式碼：

1. [Contributing](development/CONTRIBUTING.md)
2. [Testing](development/TESTING.md)
3. [Database](architecture/DATABASE.md)

評估部署腳本：

1. [Deployment Scripts](deployment/cicd/README.md)
2. [Maintenance](maintenance/MAINTENANCE.md)
3. `backend/.env.example`
4. `frontend/.env.example`

## Source Of Truth

文件與程式碼衝突時，以以下檔案為準：

- API routes：`backend/routes/api.php`
- Middleware registration：`backend/bootstrap/app.php`
- Database schema：`backend/database/migrations/`
- Domain models：`backend/app/Models/`
- Frontend routes：`frontend/src/router.js`
- Dependencies：`backend/composer.json`、`frontend/package.json`
- Deployment behavior：`docs/deployment/cicd/*.sh`

## Status

- Documentation revision: 2026-06-13
- Runtime status: archived, no live demo
- License: source available for portfolio review; no open-source license granted
