# LINE Reservation System 部署指南 (Apache)

本文檔提供使用 **Apache Web Server** 的部署方式，適用於 Laravel + Vue.js 的全端應用程式。

## 📋 系統需求

### 基本需求
- **作業系統**: Ubuntu 20.04+ / CentOS 8+ / Debian 11+
- **PHP**: 8.3+
- **Node.js**: 18+
- **MySQL**: 8.0+
- **Web Server**: Apache 2.4+
- **記憶體**: 最少 2GB，建議 4GB+
- **硬碟**: 最少 10GB 可用空間

### LINE Bot 需求
- LINE Developer Account
- LINE Bot Channel
- SSL 憑證 (HTTPS)

---

## 🚀 快速部署步驟

### 1. 環境準備

#### 更新系統套件
```bash
# Ubuntu/Debian
sudo apt update && sudo apt upgrade -y

```

#### 安裝 PHP 8.3+
```bash
# Ubuntu/Debian
sudo apt install -y php8.3 php8.3-fpm php8.3-mysql php8.3-xml php8.3-curl \
    php8.3-mbstring php8.3-zip php8.3-gd php8.3-bcmath

```

#### 安裝 MySQL 8.0
```bash
# Ubuntu/Debian
sudo apt install -y mysql-server

```

#### 安裝 Node.js 20+
```bash
# 使用 NodeSource 安裝 Node.js 20
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs

# 或使用 NVM 安裝 Node.js 20
curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.39.0/install.sh | bash
source ~/.bashrc
nvm install 20
nvm use 20
```

#### 安裝 Composer
```bash
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php
sudo mv composer.phar /usr/local/bin/composer
```

#### 安裝 Apache
```bash
# Ubuntu/Debian
sudo apt install -y apache2

# 啟用必要模組
sudo a2enmod rewrite
sudo a2enmod ssl
sudo a2enmod headers
sudo a2enmod proxy
sudo a2enmod proxy_fcgi
sudo a2enmod setenvif
```

### 2. 資料庫設定

```bash
# 登入 MySQL
sudo mysql

# 創建資料庫和用戶
CREATE DATABASE line_reservation CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'line_user'@'localhost' IDENTIFIED BY 'your_strong_password';
GRANT ALL PRIVILEGES ON line_reservation.* TO 'line_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### 3. 部署應用程式

#### 下載專案
```bash
cd /var/www
sudo git clone https://github.com/spencerkuku/line-reservation.git
sudo chown -R www-data:www-data line-reservation
cd line-reservation
```

#### 設定後端
```bash
cd backend

# 使用當前使用的權限
sudo chown -R $USER:$USER .

# 安裝 PHP 依賴
composer install --optimize-autoloader --no-dev

# 複製環境設定檔
cp .env.example .env

# 編輯環境設定
sudo nano .env
```

**`.env` 設定範例：**
```env
APP_NAME="LINE Reservation System"
APP_ENV=production
APP_KEY=base64:your_generated_app_key
APP_DEBUG=false
APP_URL=https://your-domain.com

LOG_CHANNEL=stack
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=line_reservation
DB_USERNAME=line_user
DB_PASSWORD=your_strong_password

BROADCAST_DRIVER=log
CACHE_DRIVER=file
FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync
SESSION_DRIVER=file
SESSION_LIFETIME=120

MEMCACHED_HOST=127.0.0.1

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="hello@example.com"
MAIL_FROM_NAME="${APP_NAME}"

# LINE Bot 設定
LINE_CHANNEL_ACCESS_TOKEN=your_line_channel_access_token
LINE_CHANNEL_SECRET=your_line_channel_secret
```

#### 生成應用程式金鑰並遷移資料庫
```bash
php artisan key:generate
php artisan migrate
php artisan db:seed

# 設定權限
sudo chown -R www-data:www-data /var/www/line-reservation
sudo chmod -R 755 /var/www/line-reservation
sudo chmod -R 775 /var/www/line-reservation/backend/storage
sudo chmod -R 775 /var/www/line-reservation/backend/bootstrap/cache

# 建立符號連結給前端 API 存取
sudo ln -s /var/www/line-reservation/backend/public /var/www/line-reservation/frontend/dist/api
```

#### 設定前端
```bash
cd ../frontend

# 設定環境變數檔案
cp .env.example .env
nano .env

# 內容範例：
# VITE_API_BASE_URL=https://your-domain.com/api
# VITE_APP_URL=https://your-domain.com

# 安裝 Node.js 依賴
npm install

# 建立生產版本
npm run build

# 設定權限
sudo chown -R www-data:www-data /var/www/line-reservation/frontend/dist
```

## 🚀 針對您環境的快速部署

### 1. 一鍵安裝腳本

創建並執行以下部署腳本：

```bash
#!/bin/bash

echo "🚀 開始部署 LINE Reservation System (Apache)..."

# 更新系統
sudo apt update && sudo apt upgrade -y

# 安裝必要套件
sudo apt install -y apache2 php8.3 php8.3-fpm php8.3-mysql php8.3-xml \
    php8.3-curl php8.3-mbstring php8.3-zip php8.3-gd php8.3-bcmath \
    mysql-server nodejs npm composer

# 啟用 Apache 模組
sudo a2enmod rewrite ssl headers proxy proxy_fcgi setenvif

# 設定資料庫
sudo mysql -e "CREATE DATABASE IF NOT EXISTS line_reservation CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
sudo mysql -e "CREATE USER IF NOT EXISTS 'spencer'@'localhost' IDENTIFIED BY 'Spencerku123@';"
sudo mysql -e "GRANT ALL PRIVILEGES ON line_reservation.* TO 'spencer'@'localhost';"
sudo mysql -e "FLUSH PRIVILEGES;"

# 切換到專案目錄
cd /var/www/line-reservation

# 設定後端
cd backend
composer install --optimize-autoloader
cp .env.example .env

# 更新 .env 為生產環境設定
sed -i 's/APP_ENV=local/APP_ENV=production/' .env
sed -i 's/APP_DEBUG=true/APP_DEBUG=false/' .env
sed -i 's|APP_URL=http://localhost:8000|APP_URL=https://your-domain.com|' .env

php artisan key:generate
php artisan migrate --force
php artisan db:seed --force

# 設定前端
cd ../frontend
npm install
npm run build

# 設定權限
sudo chown -R www-data:www-data /var/www/line-reservation
sudo chmod -R 755 /var/www/line-reservation
sudo chmod -R 775 /var/www/line-reservation/backend/storage
sudo chmod -R 775 /var/www/line-reservation/backend/bootstrap/cache

echo "✅ 部署完成！請設定 Apache 虛擬主機。"
```

### 2. Apache 虛擬主機設定

創建配置檔案：

```bash
sudo nano /etc/apache2/sites-available/line-reservation.conf
```

**針對您當前環境的 Apache 配置：**

根據您的 `.env` 設定和程式碼結構，建議使用以下設定：

```apache
<VirtualHost *:80>
    ServerName localhost
    DocumentRoot /var/www/line-reservation/frontend/dist
    
    # 前端 SPA 路由處理
    <Directory "/var/www/line-reservation/frontend/dist">
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
        
        # Vue.js Router History Mode 支援 - 保護 API 路由
        RewriteEngine On
        RewriteRule ^api/ - [L]
        RewriteRule ^webhook/ - [L]
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteRule . /index.html [L]
    </Directory>

    # Laravel API 路由處理
    Alias /api /var/www/line-reservation/backend/public
    <Directory "/var/www/line-reservation/backend/public">
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
        
        # 自動偵測 PHP-FPM 配置
        <FilesMatch "\.php$">
            SetHandler "proxy:unix:/var/run/php/php8.3-fpm.sock|fcgi://localhost"
        </FilesMatch>
    </Directory>

    # LINE Webhook 路由處理
    Alias /webhook /var/www/line-reservation/backend/public
    <Directory "/var/www/line-reservation/backend/public">
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    # CORS 標頭設定（配合您的 .env 設定）
    Header always set Access-Control-Allow-Origin "http://localhost:5173"
    Header always set Access-Control-Allow-Methods "GET, POST, PUT, DELETE, OPTIONS"
    Header always set Access-Control-Allow-Headers "Content-Type, Authorization, X-Requested-With, X-XSRF-TOKEN"
    Header always set Access-Control-Allow-Credentials "true"

    # 錯誤和存取日誌
    ErrorLog ${APACHE_LOG_DIR}/line-reservation_error.log
    CustomLog ${APACHE_LOG_DIR}/line-reservation_access.log combined
</VirtualHost>
```

**HTTPS 版本（生產環境）：**
```apache
# HTTP to HTTPS redirect
<VirtualHost *:80>
    ServerName your-domain.com
    DocumentRoot /var/www/line-reservation/frontend/dist
    Redirect permanent / https://your-domain.com/
</VirtualHost>

# HTTPS configuration
<VirtualHost *:443>
    ServerName your-domain.com
    DocumentRoot /var/www/line-reservation/frontend/dist
    
    # SSL 設定
    SSLEngine on
    SSLCertificateFile /path/to/your/certificate.crt
    SSLCertificateKeyFile /path/to/your/private.key
    SSLProtocol all -SSLv2 -SSLv3 -TLSv1 -TLSv1.1
    SSLCipherSuite ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384

    # 前端 SPA 路由處理
    <Directory "/var/www/line-reservation/frontend/dist">
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
        
        # Vue.js Router History Mode 支援
        RewriteEngine On
        RewriteRule ^api/ - [L]
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteRule . /index.html [L]
    </Directory>

    # Laravel API 路由處理
    Alias /api /var/www/line-reservation/backend/public
    <Directory "/var/www/line-reservation/backend/public">
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
        
        # PHP-FPM 處理
        <FilesMatch "\.php$">
            SetHandler "proxy:unix:/var/run/php/php8.3-fpm.sock|fcgi://localhost"
        </FilesMatch>
    </Directory>

    # LINE Webhook 路由
    <Location "/webhook">
        ProxyPass http://127.0.0.1:8000/api/line/webhook
        ProxyPassReverse http://127.0.0.1:8000/api/line/webhook
        ProxyPreserveHost On
    </Location>

    # 靜態資源快取設定
    <LocationMatch "\.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$">
        ExpiresActive On
        ExpiresDefault "access plus 1 year"
        Header append Cache-Control "public, immutable"
    </LocationMatch>

    # 安全標頭
    Header always set X-Content-Type-Options nosniff
    Header always set X-Frame-Options DENY
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"

    # 日誌設定
    ErrorLog ${APACHE_LOG_DIR}/line-reservation_error.log
    CustomLog ${APACHE_LOG_DIR}/line-reservation_access.log combined
</VirtualHost>
```

### 3. 啟用網站

```bash
# 停用預設網站
sudo a2dissite 000-default

# 啟用新網站
sudo a2ensite line-reservation

# 檢查設定檔語法
sudo apache2ctl configtest

# 重啟 Apache
sudo systemctl restart apache2
sudo systemctl enable apache2
```

### 4. 測試部署

```bash
# 測試 Apache 狀態
sudo systemctl status apache2

# 測試 PHP-FPM 狀態
sudo systemctl status php8.3-fpm

# 測試資料庫連接
mysql -u line_user -p line_reservation -e "SELECT 1"

# 測試 Laravel 路由
curl http://localhost/api/

# 測試前端
curl http://localhost/

# 檢查 Laravel Webhook 路由
cd /var/www/line-reservation/backend
php artisan route:list | grep webhook
```

### 5. 確認 Laravel Webhook 路由設定

檢查並確保 `routes/api.php` 包含 LINE Webhook 路由：

```php
// routes/api.php
Route::post('/line/webhook', [LineWebhookController::class, 'handle']);
```

如果沒有，請新增此路由。Webhook URL 將會是：
- `https://your-domain.com/webhook/api/line/webhook`
- 或者 `https://your-domain.com/api/line/webhook`

---

## 🔧 進階配置

### 5. SSL 憑證設定

#### 使用 Let's Encrypt (免費)
```bash
sudo apt install -y certbot python3-certbot-apache
sudo certbot --apache -d your-domain.com

# 測試自動更新
sudo certbot renew --dry-run
```

### 6. 設定定時任務 (可選)
```bash
sudo crontab -e

# 添加以下行
* * * * * cd /var/www/line-reservation/backend && php artisan schedule:run >> /dev/null 2>&1
```

### 7. 防火牆設定
```bash
sudo ufw allow 22
sudo ufw allow 80
sudo ufw allow 443
sudo ufw enable
```

---

##  安全性設定

### 1. 防火牆設定
```bash
# UFW (Ubuntu)
sudo ufw allow 22
sudo ufw allow 80
sudo ufw allow 443
sudo ufw deny 3306  # 只允許本地連接
sudo ufw enable

# 或 iptables
sudo iptables -A INPUT -p tcp --dport 22 -j ACCEPT
sudo iptables -A INPUT -p tcp --dport 80 -j ACCEPT
sudo iptables -A INPUT -p tcp --dport 443 -j ACCEPT
sudo iptables -A INPUT -p tcp --dport 3306 -s 127.0.0.1 -j ACCEPT
sudo iptables -A INPUT -p tcp --dport 3306 -j DROP
```

### 2. SSL 憑證
- 建議使用 Let's Encrypt 免費憑證
- 定期更新憑證
- 使用 TLS 1.2+ 協議

### 3. 資料庫安全
```bash
# MySQL 安全設定
sudo mysql_secure_installation
```

---

## 📊 監控與維護

### 1. 日誌監控
```bash
# 應用程式日誌
tail -f /var/www/line-reservation/backend/storage/logs/laravel.log

# Apache 日誌
tail -f /var/log/apache2/line-reservation_access.log
tail -f /var/log/apache2/line-reservation_error.log

# MySQL 日誌
tail -f /var/log/mysql/error.log
```

### 2. 效能監控
```bash
# 系統資源
htop
iotop
netstat -tulpn

# 資料庫效能
mysqladmin processlist
mysqladmin status
```

### 3. 備份策略
```bash
# 資料庫備份腳本
#!/bin/bash
DATE=$(date +%Y%m%d_%H%M%S)
mysqldump -u line_user -p line_reservation > /backup/line_reservation_$DATE.sql
find /backup -name "*.sql" -mtime +7 -delete
```

---

## 🚨 故障排除

### 常見問題

#### 1. 502 Bad Gateway
```bash
# 檢查 PHP-FPM 狀態
sudo systemctl status php8.3-fpm
sudo systemctl restart php8.3-fpm

# 檢查 Apache 狀態
sudo systemctl status apache2
sudo systemctl restart apache2
```

#### 2. 資料庫連接失敗
```bash
# 檢查 MySQL 狀態
sudo systemctl status mysql
mysql -u line_user -p -e "SELECT 1"
```

#### 3. LINE Webhook 失效
- 檢查 SSL 憑證是否有效
- 確認 webhook URL 可從外部訪問
- 檢查 LINE Channel Secret 設定

#### 4. 權限問題
```bash
sudo chown -R www-data:www-data /var/www/line-reservation
sudo chmod -R 755 /var/www/line-reservation
sudo chmod -R 775 /var/www/line-reservation/backend/storage
```

---

## 📚 後續維護

### 定期維護任務
1. **每日**: 檢查系統日誌和錯誤
2. **每週**: 更新系統套件和安全補丁
3. **每月**: 資料庫備份和清理舊日誌
4. **每季**: SSL 憑證更新檢查

### 效能優化
1. **啟用 OPcache**: 加速 PHP 執行
2. **使用 Redis**: 快取資料庫查詢
3. **CDN**: 靜態資源分發
4. **資料庫索引**: 優化查詢效能

### 擴展建議
1. **負載平衡**: 多台伺服器分散負載
2. **資料庫分離**: 讀寫分離提升效能
3. **容器編排**: 使用 Kubernetes 管理大規模部署

---

**恭喜！您的 LINE Reservation System 已成功部署！** 🎉

如有任何問題，請參考故障排除章節或聯絡技術支援。
