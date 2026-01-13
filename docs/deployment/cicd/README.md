# LINE Reservation 部署系統 v2.0

模組化部署腳本系統，支援兩種部署模式，總計約 5,400 行程式碼。

---

## 目錄

- [快速開始](#-快速開始)
- [部署模式](#-部署模式)
- [檔案結構](#-檔案結構)
- [主要腳本](#-主要腳本)
- [模組說明](#-模組說明)
- [Cloudflare Pages 整合](#️-cloudflare-pages-整合核心新功能)
- [管理控制台](#-管理控制台)
- [命令參考](#-命令參考)
- [配置說明](#-配置說明)
- [備份與還原](#-備份與還原)
- [故障排除](#-故障排除)
- [相關文件](#-相關文件)

---

## 🚀 快速開始

### 首次部署

```bash
# 進入腳本目錄
cd /home/server/projects/line-reservation/docs/deployment/cicd

# 賦予執行權限
chmod +x deploy.sh manage.sh lib/*.sh

# 執行互動式部署
./deploy.sh
```

### 快速更新（日常維護）

```bash
./manage.sh update    # 快速更新 (git pull + rebuild)
```

---

## 📦 部署模式

### 1. 統一模式 (Unified)

前後端部署在同一伺服器，適合單一伺服器環境。

```bash
# 互動式
./deploy.sh

# 命令列模式
./deploy.sh --unified --domain=example.com --ssl
./deploy.sh --unified --domain=192.168.1.100 --no-ssl
```

**架構圖：**
```
┌──────────────────────────────────────┐
│           Apache Server              │
│  ┌────────────────┬───────────────┐  │
│  │  Vue.js 前端   │  Laravel API  │  │
│  │  /dist         │  /api/*       │  │
│  └────────────────┴───────────────┘  │
│              example.com             │
└──────────────────────────────────────┘
```

### 2. Headless API 模式（純後端）

後端 API 部署在伺服器，前端使用 Cloudflare Pages，適合前後端分離架構。

```bash
# 互動式
./deploy.sh

# 命令列模式
./deploy.sh --api-only --domain=api.example.com --cloudflare=app.pages.dev --ssl
```

**架構圖：**
```
┌────────────────────┐     ┌────────────────────┐
│  Cloudflare Pages  │     │   Apache Server    │
│  ┌──────────────┐  │     │  ┌──────────────┐  │
│  │  Vue.js 前端 │◄─┼─API─┼─►│ Laravel API  │  │
│  └──────────────┘  │     │  └──────────────┘  │
│  app.pages.dev     │     │  api.example.com   │
└────────────────────┘     └────────────────────┘
        CORS 自動配置 ◄──────────────┘
```

---

## 📁 檔案結構

```
cicd/
├── config.sh           # 全域配置 (147 行)
├── deploy.sh           # 主部署腳本 (416 行)
├── manage.sh           # 管理控制台 (414 行)
├── README.md           # 本文件
│
├── lib/                # 模組庫
│   ├── core.sh         # 核心函數 (310 行)
│   ├── db.sh           # 資料庫管理 (358 行)
│   ├── git.sh          # Git 操作 (304 行)
│   ├── apache.sh       # Apache 配置 (525 行)
│   ├── ssl.sh          # SSL 憑證 (301 行)
│   ├── backend.sh      # Laravel 管理 (513 行)
│   ├── frontend.sh     # Vue.js 建置 (390 行)
│   ├── backup.sh       # 備份還原 (553 行)
│   ├── cloudflare.sh   # ☁️ Cloudflare (572 行)
│   ├── system.sh       # 系統服務 (547 行)
│   └── menu.sh         # 選單 UI (501 行)
│
└── (舊版腳本，可忽略)
    ├── deploy-apache-optimized.sh
    └── quick-update.sh
```

---

## 📋 主要腳本

### deploy.sh - 主部署腳本

用於首次安裝和完整部署。

```bash
./deploy.sh [選項]

選項:
  --unified              統一部署模式 (前後端同伺服器)
  --api-only             純 API 模式 (Cloudflare Pages 前端)
  --domain=<域名>        設定後端域名
  --cloudflare=<域名>    設定 Cloudflare Pages 域名 (API 模式必需)
  --ssl                  啟用 SSL 憑證
  --no-ssl               不使用 SSL
  --help                 顯示幫助訊息
```

**部署流程（統一模式）：**
1. 安裝系統套件
2. 設置 MySQL 資料庫
3. Git Clone/Pull 專案
4. 安裝 Composer 依賴
5. 配置 Laravel 環境
6. 執行資料庫遷移
7. 安裝 npm 依賴
8. 配置前端環境
9. 建置前端
10. 配置 Apache VirtualHost
11. 設置 SSL 憑證（可選）
12. 重啟服務

### manage.sh - 管理控制台

用於日常維護和管理。

```bash
./manage.sh [命令]

命令:
  (無參數)         啟動互動式選單
  update           快速更新 (pull + rebuild)
  cache            清除所有快取
  restart          重啟 Web 服務
  status           顯示系統狀態
  backup           建立備份
  help             顯示幫助
```

**互動式選單：**
```
╔══════════════════════════════════════════════════════════╗
║     🛠️  LINE Reservation 生產環境管理控制台              ║
╠══════════════════════════════════════════════════════════╣
║  📁 【環境配置管理】                                     ║
║   1) 域名/IP 配置更新                                    ║
║   2) SSL 證書管理                                        ║
║   3) ☁️ Cloudflare Pages 配置                            ║
║                                                          ║
║  🏗️ 【建置與部署管理】                                   ║
║   4) 前端重新建置                                        ║
║   5) 後端快取清理                                        ║
║   6) 完整系統重建                                        ║
║                                                          ║
║  🔄 【版本控制與更新】                                   ║
║   7) 代碼更新 (Git Pull + 重建)                          ║
║   8) Git 倉庫管理                                        ║
║                                                          ║
║  ⚙️ 【系統服務管理】                                     ║
║   9) 重啟 Web 服務                                       ║
║  10) 系統狀態監控                                        ║
║  11) 日誌查看                                            ║
║                                                          ║
║  💾 【備份與恢復】                                       ║
║  12) 備份管理                                            ║
║                                                          ║
║   0) 安全退出                                            ║
╚══════════════════════════════════════════════════════════╝
```

---

## 🔧 模組說明

### config.sh - 全域配置

管理所有配置變數和持久化設定。

| 變數 | 說明 | 預設值 |
|------|------|--------|
| `PROJECT_DIR` | 專案路徑 | `/var/www/line-reservation` |
| `DEPLOYMENT_MODE` | 部署模式 | `unified` |
| `CLOUDFLARE_FRONTEND_DOMAIN` | Cloudflare 域名 | (空) |
| `BACKEND_DOMAIN` | 後端域名 | (空) |
| `USE_SSL` | 是否啟用 SSL | `false` |
| `DB_NAME` | 資料庫名稱 | `line_reservation` |
| `DB_USER` | 資料庫用戶 | `line_user` |
| `GIT_REPO_URL` | Git 倉庫 URL | GitHub URL |
| `GIT_BRANCH` | Git 分支 | `main` |
| `BACKUP_RETENTION_DAYS` | 備份保留天數 | `30` |

### lib/core.sh - 核心函數

提供基礎工具函數。

```bash
# 日誌函數
log_info "訊息"
log_success "成功訊息"
log_warning "警告訊息"
log_error "錯誤訊息"
log_step "步驟說明..."
log_header "標題"

# 環境變數操作
safe_read_env "KEY" "file.env"
update_env_var "KEY" "value" "file.env"

# 工具函數
confirm_action "確定要執行嗎?"
read_with_default "請輸入" "預設值"
is_ip_address "192.168.1.1"
get_server_ip
detect_php_fpm_handler
```

### lib/db.sh - 資料庫管理

MySQL 資料庫操作。

```bash
setup_database              # 設置資料庫和用戶
generate_db_password        # 生成隨機密碼
save_db_credentials         # 保存憑證
backup_database             # 備份資料庫
restore_database "file.sql" # 還原資料庫
setup_backup_cron           # 設置自動備份
```

### lib/git.sh - Git 操作

版本控制管理。

```bash
git_clone "url" "path"
git_pull "path"
git_fetch "path"
git_checkout_branch "path" "branch"
git_restore "path" "file"
git_reset_hard "path"
interactive_git_restore "path"
```

### lib/apache.sh - Apache 配置

VirtualHost 和代理配置。

```bash
generate_unified_config "domain" "project_dir" "php_handler"
generate_unified_ssl_config "domain" "project_dir" "php_handler"
generate_api_only_config "domain" "project_dir" "php_handler" "cf_domain"
switch_to_unified_mode "domain" "project_dir" "php_handler"
switch_to_api_mode "domain" "project_dir" "php_handler" "cf_domain"
```

### lib/ssl.sh - SSL 憑證

Let's Encrypt 憑證管理。

```bash
setup_ssl_certificate "domain"
check_ssl_prerequisites
renew_ssl_certificate
toggle_ssl "domain" "current_state" "project_dir"
check_ssl_expiry "domain"
setup_ssl_auto_renew
```

### lib/backend.sh - Laravel 管理

後端應用程式管理。

```bash
composer_install "project_dir" "is_production"
setup_laravel_env "project_dir" "domain" "protocol" "db_name" "db_user" "db_pass"
configure_laravel_for_unified "project_dir" "domain" "protocol"
configure_laravel_for_api_only "project_dir" "api_domain" "cf_domain"
generate_app_key "project_dir"
run_migrations "project_dir" "force"
rebuild_cache "project_dir"
set_backend_permissions "project_dir"
```

### lib/frontend.sh - Vue.js 管理

前端建置管理。

```bash
npm_install "project_dir"
build_frontend "project_dir"
configure_frontend_for_unified "project_dir" "domain" "protocol"
configure_frontend_for_cloudflare "project_dir" "api_domain" "protocol"
generate_cloudflare_env_template "project_dir" "api_domain" "protocol"
skip_frontend_build
```

### lib/backup.sh - 備份還原

完整的備份和還原功能。

```bash
backup_env "project_dir"
backup_project "project_dir"
backup_database
backup_full "project_dir"
restore_env "project_dir" "backup_file"
restore_project "project_dir" "backup_file"
restore_full "project_dir" "backup_dir"
show_backup_menu
select_backup_to_restore "type"
```

### lib/cloudflare.sh - Cloudflare 整合

☁️ **核心新功能** - Cloudflare Pages 整合。

```bash
configure_cloudflare_integration "project_dir"
prompt_cloudflare_domain
configure_laravel_cors "project_dir" "cf_domain"
configure_laravel_sanctum "project_dir" "cf_domain" "api_domain"
configure_laravel_session "project_dir"
configure_laravel_frontend_url "project_dir" "cf_domain"
generate_cloudflare_env_file "project_dir" "api_domain" "protocol"
show_cloudflare_env_instructions
verify_cloudflare_config "project_dir"
test_cors_headers "api_url"
switch_to_headless_mode "api_domain" "cf_domain" "project_dir"
switch_back_to_unified_mode "domain" "project_dir"
update_cloudflare_domain "new_domain" "project_dir"
show_cloudflare_menu
```

### lib/system.sh - 系統管理

套件和服務管理。

```bash
install_nodejs
install_php
install_apache
install_mysql
install_certbot
install_all_packages "use_ssl"
install_packages_for_api_only "use_ssl"
restart_web_services
check_all_services
show_log_menu
show_system_menu
```

### lib/menu.sh - 選單 UI

互動式選單介面。

```bash
show_deploy_menu
prompt_deployment_mode
prompt_domain_config "mode"
prompt_ssl_config "domain"
prompt_cloudflare_config
show_main_menu
show_cloudflare_submenu
show_git_submenu
show_ssl_submenu
show_current_status
handle_main_menu_choice "choice"
main_menu_loop
```

---

## ☁️ Cloudflare Pages 整合（核心新功能）

### 功能說明

Headless API 模式會自動配置所有必要的跨域設定：

| 配置項 | 說明 | 自動設定值 |
|--------|------|-----------|
| `CORS_ALLOWED_ORIGINS` | CORS 允許來源 | `https://app.pages.dev` |
| `SANCTUM_STATEFUL_DOMAINS` | Sanctum SPA 認證 | `app.pages.dev,api.example.com` |
| `FRONTEND_URL` | 前端網址 | `https://app.pages.dev` |
| `SESSION_SAME_SITE` | Cookie 跨域 | `none` |
| `SESSION_SECURE_COOKIE` | HTTPS Cookie | `true` |

### 使用方式

#### 方式一：部署時配置

```bash
./deploy.sh --api-only --domain=api.example.com --cloudflare=app.pages.dev --ssl
```

#### 方式二：互動式配置

```bash
./manage.sh
# 選擇 3) ☁️ Cloudflare Pages 配置
# 選擇 1) 🌟 配置 Cloudflare Pages 整合
```

#### 方式三：從統一模式切換

```bash
./manage.sh
# 選擇 3) Cloudflare Pages 配置
# 輸入域名後自動重新配置
```

### Cloudflare Pages 前端設定

部署後需要在 Cloudflare Pages 設定環境變數：

```bash
# 在 Cloudflare Pages Dashboard 設定
VITE_API_BASE_URL=https://api.example.com/api
VITE_APP_URL=https://app.pages.dev
VITE_BACKEND_URL=https://api.example.com
```

腳本會自動生成 `.env.cloudflare.example` 供參考。

### 驗證配置

```bash
./manage.sh
# 選擇 3) Cloudflare Pages 配置
# 選擇 3) ✅ 驗證配置
# 選擇 6) 🧪 測試 CORS
```

---

## 📊 命令參考

### 日常操作

| 命令 | 說明 |
|------|------|
| `./manage.sh` | 啟動互動式管理選單 |
| `./manage.sh update` | 快速更新 (git pull + composer + npm + build) |
| `./manage.sh cache` | 清除 Laravel 和 Vue 快取 |
| `./manage.sh restart` | 重啟 Apache + PHP-FPM |
| `./manage.sh status` | 顯示所有服務狀態 |
| `./manage.sh backup` | 進入備份管理選單 |

### 部署操作

| 命令 | 說明 |
|------|------|
| `./deploy.sh` | 互動式部署 |
| `./deploy.sh --unified --domain=example.com --ssl` | 統一模式 + SSL |
| `./deploy.sh --unified --domain=192.168.1.100` | 統一模式 (IP，無 SSL) |
| `./deploy.sh --api-only --domain=api.example.com --cloudflare=app.pages.dev --ssl` | Headless 模式 |
| `./deploy.sh --help` | 顯示幫助 |

---

## 📝 配置說明

### 持久化配置

部署配置會儲存在 `~/.line-reservation-config`：

```bash
# 範例內容
DEPLOYMENT_MODE=api_only
CLOUDFLARE_FRONTEND_DOMAIN=myapp.pages.dev
CLOUDFLARE_FRONTEND_PROTOCOL=https
BACKEND_DOMAIN=api.example.com
BACKEND_PROTOCOL=https
USE_SSL=true
GIT_REPO_URL=https://github.com/spencerkuku/line-reservation.git
GIT_BRANCH=main
```

### 資料庫憑證

資料庫密碼儲存在 `~/.line-reservation-credentials`：

```bash
Database: line_reservation
Username: line_user
Password: (自動生成的密碼)
```

---

## 💾 備份與還原

### 備份類型

| 類型 | 說明 | 保存位置 |
|------|------|----------|
| 環境備份 | `.env` 文件 | `~/line-reservation-backups/` |
| 專案備份 | 完整專案目錄 | `~/line-reservation-backups/project-backups/` |
| 資料庫備份 | MySQL dump | `~/line-reservation-backups/database/` |
| 完整備份 | 以上全部 | `~/line-reservation-backups/` |

### 備份命令

```bash
./manage.sh backup   # 進入備份選單

# 或在互動選單中
./manage.sh
# 選擇 12) 備份管理
```

### 自動備份

腳本會設置每日自動資料庫備份的 cron job，備份保留 30 天。

---

## 🐛 故障排除

### 常見問題

#### 1. 權限問題

```bash
# 重設專案權限
sudo chown -R www-data:www-data /var/www/line-reservation
sudo chmod -R 755 /var/www/line-reservation
sudo chmod -R 775 /var/www/line-reservation/backend/storage
sudo chmod -R 775 /var/www/line-reservation/backend/bootstrap/cache
```

#### 2. Apache 無法啟動

```bash
# 檢查配置語法
sudo apache2ctl configtest

# 查看錯誤日誌
sudo tail -f /var/log/apache2/error.log
```

#### 3. PHP-FPM 問題

```bash
# 檢查 PHP-FPM 狀態
sudo systemctl status php8.3-fpm

# 重啟 PHP-FPM
sudo systemctl restart php8.3-fpm
```

#### 4. CORS 錯誤（Headless 模式）

```bash
./manage.sh
# 選擇 3) Cloudflare Pages 配置
# 選擇 3) ✅ 驗證配置
# 選擇 6) 🧪 測試 CORS
```

#### 5. 資料庫連接失敗

```bash
# 檢查 MySQL 狀態
sudo systemctl status mysql

# 測試連接
mysql -u line_user -p line_reservation -e "SELECT 1"
```

### 日誌位置

| 日誌 | 位置 |
|------|------|
| Laravel | `/var/www/line-reservation/backend/storage/logs/laravel.log` |
| Apache Access | `/var/log/apache2/line-reservation_access.log` |
| Apache Error | `/var/log/apache2/line-reservation_error.log` |
| MySQL | `/var/log/mysql/error.log` |

---

## ⚡ 特色功能

- ✅ **模組化設計**: 11 個獨立模組，低耦合、易維護
- ✅ **兩種部署模式**: 統一部署 / Headless API
- ✅ **互動式選單**: 友善的命令列介面
- ✅ **CLI 支援**: 完整的命令列參數
- ✅ **自動 CORS 配置**: Cloudflare Pages 跨域自動處理
- ✅ **PHP 版本自動偵測**: 支援 PHP 8.3/8.2/8.1
- ✅ **備份還原**: 環境、專案、資料庫完整備份
- ✅ **SSL 自動化**: Let's Encrypt 憑證自動申請與更新
- ✅ **持久化配置**: 配置自動保存和載入

---

## 📚 相關文件

- [部署指南](../Deploy.md) - 完整手動部署說明
- [設置指南](../SETUP.md) - 開發環境設置
- [API 文件](../../api/API_DOCS.md) - API 規格說明
- [架構文件](../../architecture/ARCHITECTURE.md) - 系統架構

---

## 📌 版本資訊

- **版本**: 2.0
- **最後更新**: 2026-01-13
- **總代碼行數**: ~5,400 行
- **維護者**: LINE Reservation Team
