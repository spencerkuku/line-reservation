# LINE 預約系統 - 多租戶 B2B 平台

> 基於 Laravel 11 + Vue.js 的企業級多租戶預約管理系統

## 專案概述

這是一個現代化的多租戶預約管理平台，整合 LINE Bot 自動化服務，為企業提供完整的預約解決方案。

### 核心功能
- **多租戶架構** - 支援多企業獨立運營
- **LINE Bot 整合** - 自動化客戶互動
- **彈性預約管理** - 可自訂服務項目與時段
- **用戶權限管理** - 精細的角色權限控制
- **企業級安全** - 資料加密與安全日誌
- **營運分析** - 預約數據統計與報表

## 專案文件

### Architecture (架構文件)
- [`ARCHITECTURE.md`](docs/architecture/ARCHITECTURE.md) - 系統整體架構設計
- [`DATABASE.md`](docs/architecture/DATABASE.md) - 資料庫設計與 Schema
- [`FRONTEND.md`](docs/architecture/FRONTEND.md) - 前端架構與技術棧

### API Documentation (API 文件)
- [`API_DOCS.md`](docs/api/API_DOCS.md) - API 接口完整文件

### Development (開發相關)
- [`CONTRIBUTING.md`](docs/development/CONTRIBUTING.md) - 貢獻指南與開發規範
- [`TESTING.md`](docs/development/TESTING.md) - 測試策略與執行方式

### Deployment (部署相關)
- [`Deploy.md`](docs/deployment/Deploy.md) - 部署流程與說明
- [`SETUP.md`](docs/deployment/SETUP.md) - 環境安裝與設定

#### CI/CD Scripts (持續整合部署)
- [`deploy-apache-optimized.sh`](docs/deployment/cicd/deploy-apache-optimized.sh) - Apache 優化部署腳本
- [`quick-update.sh`](docs/deployment/cicd/quick-update.sh) - 快速更新腳本

### Maintenance (維護相關)
- [`MAINTENANCE.md`](docs/maintenance/MAINTENANCE.md) - 系統維護與故障排除

## 快速開始

1. **完整文件**: 瀏覽 [`docs/README.md`](docs/README.md) 
2. **環境設置**: 參考 [`SETUP.md`](docs/deployment/SETUP.md)
3. **API 使用**: 查看 [`API_DOCS.md`](docs/api/API_DOCS.md)
4. **參與開發**: 閱讀 [`CONTRIBUTING.md`](docs/development/CONTRIBUTING.md)

## 技術棧

**後端**: Laravel 11, PHP 8.2+, MySQL 8.0+  
**前端**: Vue.js 3, Vite, Tailwind CSS  
**整合**: LINE Messaging API, Apache/Nginx

