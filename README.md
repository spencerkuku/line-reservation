# Line 預約系統

這是一個基於 LINE Bot 的預約系統，包含前端管理介面和後端 API。

## 專案結構

```
line-reservation/
├── backend/        # Laravel API 後端
├── frontend/       # Vue.js 前端管理介面
└── planning/       # 專案規劃文件
```

## 功能特色

- LINE Bot 整合，支援用戶透過 LINE 進行預約
- 前端管理介面，可管理服務項目、時段和預約記錄
- 彈性的時段管理系統
- 客戶資料管理
- 預約狀態追蹤

## 技術棧

### 後端 (Backend)
- Laravel 11
- MySQL
- LINE Bot SDK
- RESTful API

### 前端 (Frontend)
- Vue.js 3
- Vite
- Tailwind CSS
- Vue Router

## 安裝說明

### 後端設置

1. 進入 backend 目錄
```bash
cd backend
```

2. 安裝依賴
```bash
composer install
```

3. 複製環境配置檔案
```bash
cp .env.example .env
```

4. 設置資料庫連線和 LINE Bot 配置

5. 執行遷移
```bash
php artisan migrate
```

6. 啟動開發伺服器
```bash
php artisan serve
```

### 前端設置

1. 進入 frontend 目錄
```bash
cd frontend
```

2. 安裝依賴
```bash
npm install
```

3. 啟動開發伺服器
```bash
npm run dev
```

## 部署說明

請參考各自目錄下的 README.md 文件獲取詳細的部署說明。

## 授權

此專案採用 MIT 授權條款。
