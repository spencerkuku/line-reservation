# 環境設定與開發指南 (Setup & Development Guide)

## 📋 目錄

- [系統需求](#系統需求)
- [開發環境設置](#開發環境設置)
- [環境變數配置](#環境變數配置)
- [資料庫設置](#資料庫設置)
- [LINE Bot 設置](#line-bot-設置)
- [開發工作流程](#開發工作流程)
- [常見問題排除](#常見問題排除)

## 💻 系統需求

### 最低需求

| 項目 | 開發環境 | 生產環境 |
|------|----------|----------|
| **作業系統** | Windows 10+ / macOS 10.15+ / Ubuntu 20.04+ | Ubuntu 20.04+ / CentOS 8+ |
| **PHP** | 8.2+ | 8.2+ |
| **Node.js** | 18.0+ | 18.0+ |
| **MySQL** | 8.0+ | 8.0+ |
| **Composer** | 2.x | 2.x |
| **npm** | 8.0+ | 8.0+ |
| **記憶體** | 4GB+ | 4GB+ (建議 8GB+) |
| **硬碟空間** | 5GB+ | 10GB+ |

### 建議開發工具

- **IDE**: Visual Studio Code, PhpStorm
- **API 測試**: Postman, Insomnia
- **資料庫管理**: MySQL Workbench, phpMyAdmin, DBeaver
- **Git 客戶端**: Git CLI, GitHub Desktop, SourceTree
- **終端機**: Windows Terminal, iTerm2, GNOME Terminal

## 🚀 開發環境設置

### 1. 安裝基礎軟體

#### Windows

```powershell
# 安裝 Chocolatey (套件管理器)
Set-ExecutionPolicy Bypass -Scope Process -Force
iex ((New-Object System.Net.WebClient).DownloadString('https://chocolatey.org/install.ps1'))

# 安裝必要軟體
choco install php composer nodejs mysql git -y

# 驗證安裝
php -v
composer -V
node -v
npm -v
mysql --version
git --version
```

#### macOS

```bash
# 安裝 Homebrew (套件管理器)
/bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"

# 安裝必要軟體
brew install php@8.2 composer node mysql git

# 驗證安裝
php -v
composer -V
node -v
npm -v
mysql --version
git --version
```

#### Ubuntu/Debian

```bash
# 更新系統套件
sudo apt update && sudo apt upgrade -y

# 安裝 PHP 8.2 和擴展
sudo apt install -y software-properties-common
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
sudo apt install -y php8.2 php8.2-cli php8.2-fpm php8.2-mysql \
    php8.2-xml php8.2-curl php8.2-mbstring php8.2-zip \
    php8.2-gd php8.2-bcmath php8.2-intl

# 安裝 Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# 安裝 Node.js 20
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs

# 安裝 MySQL
sudo apt install -y mysql-server

# 安裝 Git
sudo apt install -y git

# 驗證安裝
php -v
composer -V
node -v
npm -v
mysql --version
git --version
```

### 2. 克隆專案

```bash
# 克隆專案倉庫
git clone https://github.com/spencerkuku/line-reservation.git

# 進入專案目錄
cd line-reservation

# 查看專案結構
ls -la
```

### 3. 後端設置

```bash
# 進入後端目錄
cd backend

# 安裝 PHP 依賴
composer install

# 複製環境變數檔案
cp .env.example .env

# 生成應用程式金鑰
php artisan key:generate

# 創建 SQLite 資料庫（可選，用於測試）
touch database/database.sqlite
```

#### 配置 `.env` 檔案

編輯 `backend/.env`：

```env
# 應用程式基本設定
APP_NAME="LINE Reservation System"
APP_ENV=local
APP_KEY=base64:your_generated_key_here
APP_DEBUG=true
APP_TIMEZONE=Asia/Taipei
APP_URL=http://localhost:8000
FRONTEND_URL=http://localhost:5173

# 日誌設定
LOG_CHANNEL=stack
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=debug

# 資料庫設定
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=line_reservation
DB_USERNAME=root
DB_PASSWORD=your_database_password

# Session 設定
BROADCAST_DRIVER=log
CACHE_DRIVER=file
FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync
SESSION_DRIVER=file
SESSION_LIFETIME=120

# Redis 設定（可選）
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Mail 設定（開發環境可用 log）
MAIL_MAILER=log
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="noreply@line-reservation.com"
MAIL_FROM_NAME="${APP_NAME}"

# LINE Bot 設定
LINE_CHANNEL_ACCESS_TOKEN=your_line_channel_access_token
LINE_CHANNEL_SECRET=your_line_channel_secret

# Sanctum 設定
SANCTUM_STATEFUL_DOMAINS=localhost:5173,127.0.0.1:5173,localhost:3000,127.0.0.1:3000
SESSION_DOMAIN=localhost
```

#### 執行資料庫遷移

```bash
# 執行遷移
php artisan migrate

# 填充測試資料（可選）
php artisan db:seed

# 或執行特定 Seeder
php artisan db:seed --class=UserSeeder
php artisan db:seed --class=ServiceSeeder
php artisan db:seed --class=AvailableTimeSeeder
```

#### 建立存儲連結

```bash
# 建立 public storage 符號連結
php artisan storage:link

# 設定權限（Linux/macOS）
chmod -R 775 storage bootstrap/cache
```

#### 啟動開發伺服器

```bash
# 方法 1: 使用 Artisan
php artisan serve
# 伺服器運行在 http://localhost:8000

# 方法 2: 指定 host 和 port
php artisan serve --host=0.0.0.0 --port=8000

# 方法 3: 使用 Valet (macOS)
valet link
valet secure  # 啟用 HTTPS
```

### 4. 前端設置

```bash
# 進入前端目錄（從專案根目錄）
cd frontend

# 安裝 Node.js 依賴
npm install

# 或使用 pnpm（更快）
npm install -g pnpm
pnpm install

# 複製環境變數檔案
cp .env.example .env
```

#### 配置前端 `.env` 檔案

編輯 `frontend/.env`：

```env
# API 基礎 URL
VITE_API_BASE_URL=http://localhost:8000/api

# 前端應用 URL
VITE_APP_URL=http://localhost:5173
VITE_APP_BASE_URL=http://localhost:5173

# 後端 URL（用於 CORS）
VITE_BACKEND_URL=http://localhost:8000

# 環境
VITE_APP_ENV=development

# Debug 模式
VITE_APP_DEBUG=true
```

#### 啟動前端開發伺服器

```bash
# 啟動開發伺服器
npm run dev

# 伺服器運行在 http://localhost:5173

# 或指定 port
npm run dev -- --port 3000

# 或指定 host（供外部存取）
npm run dev -- --host 0.0.0.0
```

## 🔧 環境變數配置

### 後端環境變數完整說明

| 變數名稱 | 說明 | 範例 | 必填 |
|---------|------|------|------|
| `APP_NAME` | 應用程式名稱 | `"LINE Reservation"` | ✅ |
| `APP_ENV` | 環境類型 | `local`, `production` | ✅ |
| `APP_KEY` | 應用程式加密金鑰 | `base64:...` | ✅ |
| `APP_DEBUG` | 除錯模式 | `true`, `false` | ✅ |
| `APP_URL` | 應用程式 URL | `http://localhost:8000` | ✅ |
| `FRONTEND_URL` | 前端 URL（CORS） | `http://localhost:5173` | ✅ |
| `DB_CONNECTION` | 資料庫類型 | `mysql`, `sqlite` | ✅ |
| `DB_HOST` | 資料庫主機 | `127.0.0.1` | ✅ |
| `DB_PORT` | 資料庫埠號 | `3306` | ✅ |
| `DB_DATABASE` | 資料庫名稱 | `line_reservation` | ✅ |
| `DB_USERNAME` | 資料庫用戶名 | `root` | ✅ |
| `DB_PASSWORD` | 資料庫密碼 | `password` | ✅ |
| `LINE_CHANNEL_ACCESS_TOKEN` | LINE Channel Access Token | `your_token` | ✅ |
| `LINE_CHANNEL_SECRET` | LINE Channel Secret | `your_secret` | ✅ |
| `MAIL_MAILER` | 郵件驅動 | `smtp`, `log` | ❌ |
| `SANCTUM_STATEFUL_DOMAINS` | Sanctum 允許的域名 | `localhost:5173` | ✅ |

### 前端環境變數完整說明

| 變數名稱 | 說明 | 範例 | 必填 |
|---------|------|------|------|
| `VITE_API_BASE_URL` | API 基礎 URL | `http://localhost:8000/api` | ✅ |
| `VITE_APP_URL` | 前端 URL | `http://localhost:5173` | ✅ |
| `VITE_BACKEND_URL` | 後端 URL | `http://localhost:8000` | ✅ |
| `VITE_APP_ENV` | 環境類型 | `development` | ❌ |
| `VITE_APP_DEBUG` | 除錯模式 | `true` | ❌ |

## 🗄️ 資料庫設置

### MySQL 資料庫創建

#### 方法 1: MySQL CLI

```bash
# 登入 MySQL
mysql -u root -p

# 創建資料庫
CREATE DATABASE line_reservation CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

# 創建用戶（可選）
CREATE USER 'line_user'@'localhost' IDENTIFIED BY 'your_password';
GRANT ALL PRIVILEGES ON line_reservation.* TO 'line_user'@'localhost';
FLUSH PRIVILEGES;

# 退出
EXIT;
```

#### 方法 2: phpMyAdmin

1. 打開 phpMyAdmin
2. 點擊「新增」創建資料庫
3. 資料庫名稱: `line_reservation`
4. 編碼: `utf8mb4_unicode_ci`
5. 點擊「創建」

### 資料庫遷移

```bash
# 查看待執行的遷移
php artisan migrate:status

# 執行所有遷移
php artisan migrate

# 重置並重新執行所有遷移（⚠️ 會刪除所有資料）
php artisan migrate:fresh

# 重置並填充資料
php artisan migrate:fresh --seed

# 回滾最後一批遷移
php artisan migrate:rollback

# 回滾所有遷移
php artisan migrate:reset
```

### 資料填充（Seeding）

```bash
# 執行所有 Seeders
php artisan db:seed

# 執行特定 Seeder
php artisan db:seed --class=UserSeeder

# 可用的 Seeders:
# - DatabaseSeeder (主 Seeder)
# - UserSeeder (創建管理員和測試用戶)
# - ServiceSeeder (創建服務項目)
# - AvailableTimeSeeder (創建可用時段)
# - CustomerSeeder (創建測試客戶)
```

### 預設測試帳號

執行 Seeder 後，以下帳號可用：

```
管理員帳號:
Email: admin@example.com
Password: password

一般用戶帳號:
Email: user@example.com
Password: password
```

⚠️ **生產環境務必更改這些預設密碼！**

## 🤖 LINE Bot 設置

### 1. 創建 LINE Channel

1. 前往 [LINE Developers Console](https://developers.line.biz/console/)
2. 登入或註冊帳號
3. 點擊「Create a new provider」創建提供者
4. 點擊「Create a channel」
5. 選擇「Messaging API」
6. 填寫必要資訊：
   - Channel name: `LINE Reservation Bot`
   - Channel description: 預約系統機器人
   - Category: 根據您的業務選擇
   - Subcategory: 根據您的業務選擇
7. 同意條款並創建

### 2. 設定 Channel

#### 基本設定

1. 在 Channel 基本設定頁面找到：
   - **Channel Secret**: 複製到 `.env` 的 `LINE_CHANNEL_SECRET`
   - **Channel ID**: 記錄備用

2. 在 Messaging API 頁面：
   - **Channel Access Token**: 點擊「Issue」生成，複製到 `.env` 的 `LINE_CHANNEL_ACCESS_TOKEN`

#### Webhook 設定

⚠️ **開發環境需使用 ngrok 或類似工具將本地伺服器暴露到公網**

```bash
# 安裝 ngrok (參考 https://ngrok.com/)

# 啟動 ngrok
ngrok http 8000

# 複製 ngrok 提供的 HTTPS URL，例如:
# https://abcd1234.ngrok.io
```

在 LINE Developers Console 設定：

1. 前往「Messaging API」標籤
2. 在「Webhook settings」找到「Webhook URL」
3. 輸入: `https://your-ngrok-url.ngrok.io/api/line/webhook`
4. 點擊「Verify」驗證
5. 啟用「Use webhook」
6. 關閉「Auto-reply messages」（自動回覆訊息）
7. 關閉「Greeting messages」（歡迎訊息）

### 3. 測試 LINE Bot

#### 加入好友

1. 在 LINE Developers Console 找到 QR Code
2. 用 LINE App 掃描加入好友

#### 測試訊息

發送測試訊息：
```
預約
服務
查詢
```

查看日誌：
```bash
# 後端日誌
tail -f backend/storage/logs/laravel.log

# 即時查看所有日誌
php artisan pail

# 查看特定類型日誌
php artisan pail --filter="level=error"
```

## 🔄 開發工作流程

### 日常開發流程

#### 1. 啟動開發環境

```bash
# 終端機 1: 後端
cd backend
php artisan serve

# 終端機 2: 前端
cd frontend
npm run dev

# 終端機 3: 資料庫（如果使用 Docker）
docker-compose up mysql

# 終端機 4: ngrok（如需測試 LINE Bot）
ngrok http 8000
```

#### 2. 代碼開發

```bash
# 創建新的 Migration
php artisan make:migration create_table_name

# 創建新的 Model
php artisan make:model ModelName -m

# 創建新的 Controller
php artisan make:controller Api/ControllerName

# 創建新的 Request
php artisan make:request RequestName

# 創建新的 Seeder
php artisan make:seeder SeederName
```

#### 3. 測試變更

```bash
# 後端測試
php artisan test

# 或使用 PHPUnit
./vendor/bin/phpunit

# 前端測試
npm run test

# 代碼格式檢查
composer run lint    # PHP
npm run lint         # JavaScript
```

#### 4. 提交代碼

```bash
# 查看變更
git status

# 添加變更
git add .

# 提交（遵循 Commit Message 規範）
git commit -m "feat: add customer export feature"

# 推送到遠端
git push origin feature/your-feature-name
```

### Git 分支策略

```
main                    # 生產環境分支
  ├── develop          # 開發分支
  │   ├── feature/xxx  # 功能分支
  │   ├── bugfix/xxx   # 錯誤修復分支
  │   └── hotfix/xxx   # 緊急修復分支
  └── release/v1.x.x   # 發布分支
```

### Commit Message 規範

```
格式: <type>(<scope>): <subject>

type:
- feat: 新功能
- fix: 錯誤修復
- docs: 文件更新
- style: 代碼格式調整
- refactor: 重構
- perf: 效能優化
- test: 測試相關
- chore: 建置或輔助工具變更

範例:
feat(customer): add customer export to CSV
fix(reservation): resolve timezone issue
docs(api): update API documentation
```

## 🐛 常見問題排除

### 後端問題

#### 問題: "Class not found" 錯誤

```bash
# 解決方案: 重新生成 autoload
composer dump-autoload
```

#### 問題: 資料庫連接失敗

```bash
# 檢查 MySQL 服務
sudo systemctl status mysql    # Linux
brew services list             # macOS

# 檢查連接
mysql -u root -p -e "SELECT 1"

# 檢查 .env 配置
cat backend/.env | grep DB_
```

#### 問題: Storage 權限錯誤

```bash
# Linux/macOS
cd backend
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# 或使用當前用戶
sudo chown -R $USER:www-data storage bootstrap/cache
```

#### 問題: Sanctum Token 無效

```bash
# 清除配置快取
php artisan config:clear
php artisan cache:clear

# 檢查 SANCTUM_STATEFUL_DOMAINS 設定
# 確保包含前端域名
```

### 前端問題

#### 問題: npm install 失敗

```bash
# 清除 npm 快取
npm cache clean --force

# 刪除 node_modules 和 package-lock.json
rm -rf node_modules package-lock.json

# 重新安裝
npm install
```

#### 問題: API 請求 CORS 錯誤

檢查後端 `config/cors.php`:

```php
'paths' => ['api/*', 'sanctum/csrf-cookie'],
'allowed_origins' => [env('FRONTEND_URL', 'http://localhost:5173')],
'supports_credentials' => true,
```

檢查前端 API 設定 `src/utils/api.js`:

```javascript
axios.defaults.withCredentials = true;
axios.defaults.baseURL = import.meta.env.VITE_API_BASE_URL;
```

#### 問題: Vite 開發伺服器無法啟動

```bash
# 檢查 port 是否被佔用
lsof -i :5173          # macOS/Linux
netstat -ano | find "5173"  # Windows

# 指定其他 port
npm run dev -- --port 3000
```

### LINE Bot 問題

#### 問題: Webhook 驗證失敗

```bash
# 確認 Webhook URL 可從外部訪問
curl -X POST https://your-domain.com/api/line/webhook

# 檢查 LINE_CHANNEL_SECRET 是否正確
php artisan tinker
config('linebot.channel_secret')
```

#### 問題: 無法接收訊息

1. 檢查 Webhook 是否啟用
2. 查看 LINE Developers Console 的 Webhook 日誌
3. 檢查後端日誌: `tail -f storage/logs/laravel.log`
4. 確認 ngrok 是否正常運行

#### 問題: 無法發送訊息

```bash
# 測試 LINE API 連接
php artisan tinker

use App\Services\LineBotService;
$bot = app(LineBotService::class);
$bot->pushMessage('用戶LINE ID', '測試訊息');
```

### 資料庫問題

#### 問題: Migration 失敗

```bash
# 查看錯誤詳情
php artisan migrate --verbose

# 回滾並重試
php artisan migrate:rollback
php artisan migrate

# 檢查資料庫表
mysql -u root -p line_reservation -e "SHOW TABLES;"
```

#### 問題: 外鍵約束錯誤

```bash
# 暫時停用外鍵檢查（僅開發環境）
php artisan tinker
DB::statement('SET FOREIGN_KEY_CHECKS=0;');
// 執行操作
DB::statement('SET FOREIGN_KEY_CHECKS=1;');
```

## 📚 開發資源

### 官方文件

- [Laravel 文件](https://laravel.com/docs)
- [Vue.js 文件](https://vuejs.org/)
- [Tailwind CSS 文件](https://tailwindcss.com/docs)
- [LINE Messaging API 文件](https://developers.line.biz/en/docs/messaging-api/)

### 實用工具

- [Laravel Debugbar](https://github.com/barryvdh/laravel-debugbar) - 除錯工具列
- [Laravel IDE Helper](https://github.com/barryvdh/laravel-ide-helper) - IDE 自動完成
- [Vue DevTools](https://devtools.vuejs.org/) - Vue 除錯工具
- [ngrok](https://ngrok.com/) - 本地服務暴露工具

### 推薦閱讀

- [RESTful API 設計指南](https://restfulapi.net/)
- [Git 版本控制](https://git-scm.com/book/zh-tw/v2)
- [MySQL 效能優化](https://dev.mysql.com/doc/refman/8.0/en/optimization.html)

## 🆘 獲取幫助

遇到問題？

1. 查看 [MAINTENANCE.md](./MAINTENANCE.md) 維運指南
2. 搜尋 [GitHub Issues](https://github.com/spencerkuku/line-reservation/issues)
3. 提交新的 Issue 並附上詳細錯誤訊息
4. 參考 [API_DOCS.md](./API_DOCS.md) API 文件

---

**文件版本**: v1.0.0  
**最後更新**: 2025-10-23  
**維護者**: 傅盛祥 (Spencer Kuku)
