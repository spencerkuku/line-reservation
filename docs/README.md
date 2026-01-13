# LINE 預約系統 - 專案文件目錄

> 完整的技術文件，涵蓋架構設計、API 規範、開發指南、部署流程與維護手冊

---

## 文件組織結構

### 架構文件 (Architecture)
- [ARCHITECTURE.md](architecture/ARCHITECTURE.md) - 系統整體架構、多租戶設計、服務層架構
- [DATABASE.md](architecture/DATABASE.md) - 資料庫 Schema、關聯設計、索引策略、多租戶資料隔離
- [FRONTEND.md](architecture/FRONTEND.md) - Vue.js 前端架構、元件設計、Composition API、狀態管理
- [UI_DESIGN_GUIDE.md](architecture/UI_DESIGN_GUIDE.md) - UI/UX 設計規範、元件樣式指南

### API 文件 (API Documentation)
- [API_DOCS.md](api/API_DOCS.md) - RESTful API 完整接口文件、認證機制、請求/回應格式

### 開發指南 (Development)
- [CONTRIBUTING.md](development/CONTRIBUTING.md) - 貢獻指南、程式碼規範、Git 工作流程、Code Review
- [TESTING.md](development/TESTING.md) - 測試策略、PHPUnit 測試、功能測試、單元測試

### 部署文件 (Deployment)
- [cicd/README.md](deployment/cicd/README.md) - 完整的部署系統文件 (5,400+ 行腳本)
- [cicd/deploy.sh](deployment/cicd/deploy.sh) - 主部署腳本 (統一模式 / API Only 模式)
- [cicd/manage.sh](deployment/cicd/manage.sh) - 生產環境管理控制台 (互動式選單)
- [cicd/config.sh](deployment/cicd/config.sh) - 全域配置變數
- [cicd/lib/](deployment/cicd/lib/) - 11 個模組化腳本
  - `core.sh` - 核心共用函數、日誌、環境變數操作
  - `system.sh` - 系統套件安裝與管理
  - `db.sh` - 資料庫設置、備份與恢復
  - `git.sh` - Git 版本控制操作
  - `backend.sh` - Laravel 後端部署
  - `frontend.sh` - Vue.js 前端建置
  - `apache.sh` - Apache 配置管理
  - `ssl.sh` - SSL 憑證管理 (Let's Encrypt)
  - `backup.sh` - 自動備份與還原
  - `cloudflare.sh` - Cloudflare Pages 整合
  - `menu.sh` - 互動式選單 UI

### 維護文件 (Maintenance)
- [MAINTENANCE.md](maintenance/MAINTENANCE.md) - 系統維護、故障排除、效能優化、日誌管理

---

## 快速導覽

### 新手入門
1. 閱讀根目錄的 [README.md](../README.md) 了解專案概述
2. 查看 [ARCHITECTURE.md](architecture/ARCHITECTURE.md) 理解系統架構
3. 參考 [cicd/README.md](deployment/cicd/README.md) 進行部署

### 開發人員
1. 閱讀 [CONTRIBUTING.md](development/CONTRIBUTING.md) 了解開發規範
2. 查看 [API_DOCS.md](api/API_DOCS.md) 了解 API 規範
3. 參考 [TESTING.md](development/TESTING.md) 撰寫測試
4. 查看 [DATABASE.md](architecture/DATABASE.md) 了解資料結構

### 系統管理員
1. 使用 [cicd/deploy.sh](deployment/cicd/deploy.sh) 進行首次部署
2. 使用 [cicd/manage.sh](deployment/cicd/manage.sh) 進行日常管理
3. 參考 [MAINTENANCE.md](maintenance/MAINTENANCE.md) 處理維護事項
4. 查看 [cicd/README.md](deployment/cicd/README.md) 了解完整部署流程

### API 使用者
1. 查看 [API_DOCS.md](api/API_DOCS.md) 了解所有 API 端點
2. 參考認證機制章節設定 API Token
3. 查看範例請求與回應格式

---

## 文件版本

- **架構文件**: v2.0 (2025-12-12)
- **部署系統**: v2.0 (2026-01-13)
- **API 文件**: v1.0 (持續更新)
- **最後更新**: 2026-01-13