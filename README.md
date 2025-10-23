# LINE 預約系統 (LINE Reservation System)

[![Laravel](https://img.shields.io/badge/Laravel-12.0-red.svg)](https://laravel.com)
[![Vue.js](https://img.shields.io/badge/Vue.js-3.5-green.svg)](https://vuejs.org)
[![PHP](https://img.shields.io/badge/PHP-8.2+-blue.svg)](https://php.net)
[![License](https://img.shields.io/badge/License-Proprietary-yellow.svg)](#授權)

一個功能完整的 LINE Bot 整合預約管理系統，提供用戶透過 LINE 進行預約，並配有完善的後台管理介面。

## 📋 目錄

- [專案簡介](#專案簡介)
- [主要功能](#主要功能)
- [技術棧](#技術棧)
- [專案結構](#專案結構)
- [快速開始](#快速開始)
- [相關文件](#相關文件)
- [授權](#授權)

## 📖 專案簡介

LINE 預約系統是一個企業級的預約管理解決方案，整合 LINE Messaging API，讓用戶可以透過 LINE 聊天機器人輕鬆完成預約流程。系統包含前端管理介面（Vue.js）和後端 API（Laravel），提供完整的預約、客戶、服務管理功能。

### 系統目的

- **提升用戶體驗**：透過熟悉的 LINE 介面，簡化預約流程
- **提高管理效率**：集中化的後台管理，即時追蹤預約狀態
- **數據分析**：完整的客戶資料與預約記錄，支援商業決策
- **自動化流程**：自動化預約確認、提醒與狀態更新

### 適用場景

- 美容美髮沙龍
- 醫療診所
- 餐廳訂位
- 健身房預約
- 諮詢服務
- 活動報名

## ✨ 主要功能

### 用戶端功能（LINE Bot）
- 🤖 **智能對話**：自然語言互動，引導用戶完成預約
- 📅 **即時預約**：查看可用時段，選擇服務項目
- 📝 **預約管理**：查詢、取消、修改預約
- 🔔 **自動提醒**：預約前自動發送提醒訊息
- 👤 **個人資料**：管理個人聯絡資訊

### 管理端功能（Web 後台）
- 📊 **儀表板**：即時統計數據與預約概覽
- 👥 **客戶管理**：完整的客戶資料庫與互動歷史
- 🗓️ **預約管理**：查看、確認、取消、完成預約
- 🛠️ **服務管理**：新增、編輯、刪除服務項目
- ⏰ **時段管理**：彈性設定可預約時段與容量
- ✅ **報到管理**：客戶報到、付款記錄
- 📝 **活動日誌**：完整的管理員操作記錄
- ⚙️ **系統設定**：LINE Bot 設定、營業時間設定

### 安全性功能
- 🔐 **身份驗證**：Laravel Sanctum Token 驗證
- 👨‍💼 **角色權限**：管理員與一般用戶權限分離
- 🛡️ **資料加密**：敏感資料加密存儲
- 📋 **操作日誌**：所有管理操作完整記錄
- 🚫 **API 限流**：防止 API 濫用

## 🛠️ 技術棧

### 後端 (Backend)
- **框架**: Laravel 12.0
- **語言**: PHP 8.2+
- **資料庫**: MySQL 8.0+
- **認證**: Laravel Sanctum
- **LINE SDK**: LINE Bot SDK 11.1
- **API**: RESTful API

### 前端 (Frontend)
- **框架**: Vue.js 3.5
- **構建工具**: Vite 7.0
- **UI 框架**: Tailwind CSS 3.4
- **路由**: Vue Router 4.5
- **狀態管理**: Pinia 3.0
- **HTTP 客戶端**: Axios 1.10
- **圖示**: Heroicons
- **日曆**: FullCalendar 6.1

### 開發工具
- **版本控制**: Git
- **API 測試**: Postman / Insomnia
- **代碼格式**: Laravel Pint, Prettier

## 📁 專案結構

```
line-reservation/
├── backend/                    # Laravel 後端
│   ├── app/
│   │   ├── Console/           # 命令行指令
│   │   ├── Http/
│   │   │   ├── Controllers/   # 控制器
│   │   │   ├── Middleware/    # 中間件
│   │   │   └── Requests/      # 表單驗證
│   │   ├── Models/            # Eloquent 模型
│   │   ├── Observers/         # 模型觀察者
│   │   ├── Providers/         # 服務提供者
│   │   └── Services/          # 業務邏輯服務
│   ├── config/                # 配置文件
│   ├── database/
│   │   ├── migrations/        # 資料庫遷移
│   │   └── seeders/          # 資料填充
│   ├── routes/                # 路由定義
│   │   ├── api.php           # API 路由
│   │   ├── web.php           # Web 路由
│   │   └── auth.php          # 認證路由
│   ├── storage/               # 文件存儲
│   │   └── logs/             # 日誌文件
│   └── tests/                 # 測試文件
├── frontend/                   # Vue.js 前端
│   ├── public/                # 靜態資源
│   ├── src/
│   │   ├── assets/           # 資源文件
│   │   ├── components/       # Vue 組件
│   │   ├── composables/      # 組合式函數
│   │   ├── pages/            # 頁面組件
│   │   ├── utils/            # 工具函數
│   │   ├── App.vue           # 根組件
│   │   ├── main.js           # 入口文件
│   │   ├── router.js         # 路由配置
│   │   └── style.css         # 全局樣式
│   └── vite.config.js         # Vite 配置
├── Deploy.md                   # 部署指南（Apache）
├── README.md                   # 本文件
└── quick-update.sh            # 快速更新腳本
```

## 🚀 快速開始

### 系統需求

- **PHP**: 8.2 或更高版本
- **Node.js**: 18.0 或更高版本
- **MySQL**: 8.0 或更高版本
- **Composer**: 2.x
- **npm**: 8.0 或更高版本

### 後端設置

1. **進入後端目錄**
   ```bash
   cd backend
   ```

2. **安裝 PHP 依賴**
   ```bash
   composer install
   ```

3. **配置環境變數**
   ```bash
   cp .env.example .env
   ```
   
   編輯 `.env` 文件，設定以下參數：
   ```env
   APP_NAME="LINE Reservation System"
   APP_URL=http://localhost:8000
   
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=line_reservation
   DB_USERNAME=your_username
   DB_PASSWORD=your_password
   
   FRONTEND_URL=http://localhost:5173
   ```
   
   **注意**: LINE Bot 設定（Channel Access Token 和 Channel Secret）是存儲在資料庫的 `settings` 表中，透過後台管理介面進行設定，不需要在 `.env` 文件中配置。

4. **生成應用程式金鑰**
   ```bash
   php artisan key:generate
   ```

5. **執行資料庫遷移**
   ```bash
   php artisan migrate
   php artisan db:seed
   ```

6. **建立存儲連結**
   ```bash
   php artisan storage:link
   ```

7. **啟動開發伺服器**
   ```bash
   php artisan serve
   ```
   
   後端 API 將運行在 `http://localhost:8000`

### 前端設置

1. **進入前端目錄**
   ```bash
   cd frontend
   ```

2. **安裝 Node.js 依賴**
   ```bash
   npm install
   ```

3. **配置環境變數**
   ```bash
   cp .env.example .env
   ```
   
   編輯 `.env` 文件：
   ```env
   VITE_API_BASE_URL=http://localhost:8000/api
   VITE_APP_URL=http://localhost:5173
   ```

4. **啟動開發伺服器**
   ```bash
   npm run dev
   ```
   
   前端應用將運行在 `http://localhost:5173`

### 預設登入帳號

```
帳號: admin@example.com
密碼: password
```

⚠️ **請在生產環境中立即更改預設密碼！**

## 📚 相關文件

為了更好地理解和使用本系統，我們提供了以下詳細文件：

- **[ARCHITECTURE.md](./ARCHITECTURE.md)** - 系統架構說明
- **[SETUP.md](./SETUP.md)** - 環境設定與開發指南
- **[Deploy.md](./Deploy.md)** - Apache 部署指南
- **[API_DOCS.md](./API_DOCS.md)** - API 完整文件
- **[DATABASE.md](./DATABASE.md)** - 資料庫結構說明
- **[FRONTEND.md](./FRONTEND.md)** - 前端架構與組件說明
- **[TESTING.md](./TESTING.md)** - 測試指南
- **[MAINTENANCE.md](./MAINTENANCE.md)** - 維運與監控指南
- **[CONTRIBUTING.md](./CONTRIBUTING.md)** - 開發規範與貢獻指南

## 🔧 常見問題

### 無法連接資料庫
確認 MySQL 服務已啟動，並檢查 `.env` 文件中的資料庫連接設定。

### LINE Webhook 無法接收訊息
1. 確認 Webhook URL 設定正確（需使用 HTTPS）
2. 檢查 LINE Channel Secret 和 Access Token 是否正確
3. 查看 `storage/logs/laravel.log` 了解詳細錯誤

### 前端無法連接後端 API
檢查 `frontend/.env` 中的 `VITE_API_BASE_URL` 是否指向正確的後端地址。

更多問題請參考 [SETUP.md](./SETUP.md) 或提交 Issue。

## PR須知

在提交 Pull Request 之前，請先閱讀 [CONTRIBUTING.md](./CONTRIBUTING.md) 了解開發規範和流程。

## 📝 更新日誌

### v1.0.0 (2025-10-23)
- ✨ 初始版本發布
- 🤖 LINE Bot 整合
- 👥 客戶管理功能
- 📅 預約管理功能
- ✅ 報到與付款功能
- 📊 儀表板統計
- 🔐 完整的認證與授權系統

## 📄 授權

© 2025 傅盛祥 (Spencer Kuku)

本專案保留所有權利，禁止未經授權的複製、修改、散布或商業使用。

**未經許可不得：**
- 使用於商業用途
- 轉售或再發佈
- 修改後重新分發

如需授權或商業使用，請聯絡專案所有者。

## 📧 聯絡方式

- **專案擁有者**: 傅盛祥
- **GitHub**: [@spencerkuku](https://github.com/spencerkuku)
- **專案倉庫**: [line-reservation](https://github.com/spencerkuku/line-reservation)

---

**感謝使用 LINE 預約系統！** 🎉
