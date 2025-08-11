#!/bin/bash

echo "🚀 開始部署 LINE Reservation System (Apache) - 完整優化版..."

set -e
trap 'echo "❌ 部署失敗於第 $LINENO 行"; exit 1' ERR

# 檢查非 root 執行
if [[ $EUID -eq 0 ]]; then
    echo "❌ 請不要用 root 用戶執行，改用一般用戶！"
    exit 1
fi

PROJECT_DIR="/var/www/line-reservation"
USER_HOME=$(eval echo "~$USER")

# 自動偵測 IP
SERVER_IP=$(hostname -I | awk '{print $1}')

# 讓用戶選擇使用 IP 或 domain
echo "🌐 請選擇訪問方式："
echo "1) 使用 IP 地址 ($SERVER_IP) - 不使用 SSL"
echo "2) 使用自定義域名 - 使用 SSL"
echo ""
read -p "請輸入選擇 (1 或 2): " CHOICE

USE_SSL=false
if [ "$CHOICE" = "2" ]; then
    read -p "請輸入您的域名: " DOMAIN
    if [ -z "$DOMAIN" ]; then
        echo "❌ 域名不能為空！"
        exit 1
    fi
    USE_SSL=true
    PROTOCOL="https"
    echo "🔒 將使用 SSL 配置域名: $DOMAIN"
elif [ "$CHOICE" = "1" ]; then
    DOMAIN=$SERVER_IP
    USE_SSL=false
    PROTOCOL="http"
    echo "🌐 將使用 IP 地址: $DOMAIN"
else
    echo "❌ 無效的選擇！"
    exit 1
fi

echo "📁 專案目錄: $PROJECT_DIR"
echo "🌐 域名/IP: $DOMAIN"
echo "🔒 使用 SSL: $USE_SSL"
echo "🔍 偵測到伺服器 IP: $SERVER_IP"

echo "📦 更新系統套件..."
sudo apt update && sudo apt upgrade -y

echo "🔧 安裝 Node.js 20..."
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs

echo "🔧 安裝必要套件..."
if [ "$USE_SSL" = true ]; then
    sudo apt install -y apache2 php8.3 php8.3-fpm php8.3-mysql php8.3-xml php8.3-curl php8.3-mbstring php8.3-zip php8.3-gd php8.3-bcmath mysql-server git curl unzip certbot python3-certbot-apache
else
    sudo apt install -y apache2 php8.3 php8.3-fpm php8.3-mysql php8.3-xml php8.3-curl php8.3-mbstring php8.3-zip php8.3-gd php8.3-bcmath mysql-server git curl unzip
fi

echo "📥 安裝 Composer (如未安裝)..."
if ! command -v composer &>/dev/null; then
    php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
    php composer-setup.php
    sudo mv composer.phar /usr/local/bin/composer
    rm composer-setup.php
fi

echo "⚙️ 啟用 Apache 模組..."
sudo a2enmod rewrite ssl headers proxy proxy_fcgi setenvif expires

echo "🔍 偵測 PHP-FPM 版本..."
INSTALLED_PHP_VERSIONS=$(systemctl list-units --type=service | grep -oP 'php\d+\.\d+-fpm' | sed 's/-fpm//' | sed 's/php//')
CLI_PHP_VERSION=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')

PHP_FPM_VERSION=""
for v in $INSTALLED_PHP_VERSIONS; do
    if systemctl is-active --quiet php${v}-fpm; then
        PHP_FPM_VERSION=$v
        break
    fi
done
if [ -z "$PHP_FPM_VERSION" ]; then
    PHP_FPM_VERSION=$CLI_PHP_VERSION
fi

POSSIBLE_SOCKETS=(
    "/run/php/php${PHP_FPM_VERSION}-fpm.sock"
    "/var/run/php/php${PHP_FPM_VERSION}-fpm.sock"
    "/var/run/php-fpm/php-fpm.sock"
)

PHP_FPM_HANDLER=""
for sock in "${POSSIBLE_SOCKETS[@]}"; do
    if [ -S "$sock" ]; then
        PHP_FPM_HANDLER="proxy:unix:${sock}|fcgi://localhost"
        break
    fi
done
if [ -z "$PHP_FPM_HANDLER" ]; then
    PHP_FPM_HANDLER="proxy:fcgi://127.0.0.1:9000"
fi

echo "🔧 使用 PHP-FPM 處理器: $PHP_FPM_HANDLER"
echo "📋 使用 PHP-FPM 版本: $PHP_FPM_VERSION"

echo "🗄️ 啟動並啟用 MySQL 服務..."
sudo systemctl start mysql
sudo systemctl enable mysql

DB_PASS=$(openssl rand -hex 16)
echo "🔑 生成 MySQL 資料庫密碼：$DB_PASS"

MYSQL_CMD=""
if sudo mysql --defaults-file=/etc/mysql/debian.cnf -e "SELECT 1;" &>/dev/null; then
    MYSQL_CMD="sudo mysql --defaults-file=/etc/mysql/debian.cnf"
elif sudo mysql -e "SELECT 1;" &>/dev/null; then
    MYSQL_CMD="sudo mysql"
elif mysql -e "SELECT 1;" &>/dev/null; then
    MYSQL_CMD="mysql"
else
    echo "❌ 無法連接 MySQL，請檢查設定後再重試。"
    exit 1
fi

# 建立資料庫及使用者，指定 mysql_native_password
$MYSQL_CMD -e "CREATE DATABASE IF NOT EXISTS \`line_reservation\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
$MYSQL_CMD -e "CREATE USER IF NOT EXISTS 'line_user'@'localhost' IDENTIFIED WITH mysql_native_password BY '${DB_PASS}';"
$MYSQL_CMD -e "ALTER USER 'line_user'@'localhost' IDENTIFIED WITH mysql_native_password BY '${DB_PASS}';"
$MYSQL_CMD -e "GRANT ALL PRIVILEGES ON \`line_reservation\`.* TO 'line_user'@'localhost';"
$MYSQL_CMD -e "FLUSH PRIVILEGES;"

CRED_FILE="$USER_HOME/.line-reservation-credentials"
echo -e "Database: line_reservation\nUsername: line_user\nPassword: $DB_PASS" > "$CRED_FILE"
chmod 600 "$CRED_FILE"
echo "📄 資料庫憑證已保存於 $CRED_FILE"

# 下載或更新專案
if [ -d "$PROJECT_DIR" ]; then
    echo "📥 更新專案代碼..."
    sudo chown -R $USER:$USER "$PROJECT_DIR"   # 確保目錄權限
    cd "$PROJECT_DIR"
    git config --global --add safe.directory "$PROJECT_DIR"
    if ! git pull origin main; then
        echo "⚠️ Git 更新失敗，請手動檢查"
    fi
else
    echo "📥 下載專案代碼..."
    cd /var/www
    sudo git clone https://github.com/spencerkuku/line-reservation.git
    sudo chown -R $USER:$USER line-reservation
    git config --global --add safe.directory "$PROJECT_DIR"
fi

cd "$PROJECT_DIR"

# 備份資料庫憑證
cp "$CRED_FILE" "$PROJECT_DIR/db_credentials.txt"
chmod 600 "$PROJECT_DIR/db_credentials.txt"
echo "📄 資料庫憑證備份已建立於 $PROJECT_DIR/db_credentials.txt"

# 後端安裝
cd backend

echo "📦 安裝 PHP 依賴..."
sudo -u $USER composer install --optimize-autoloader --no-dev

# 環境檔案
if [ ! -f .env ]; then
    cp .env.example .env
fi

update_env_var() {
    local key="$1"
    local val="$2"
    local file=".env"
    if grep -q "^${key}=" "$file"; then
        sed -i "s|^${key}=.*|${key}=${val}|" "$file"
    else
        echo "${key}=${val}" >> "$file"
    fi
}

update_env_var "APP_ENV" "production"
update_env_var "APP_DEBUG" "false"
update_env_var "APP_URL" "${PROTOCOL}://$DOMAIN"
update_env_var "FRONTEND_URL" "${PROTOCOL}://$DOMAIN"
update_env_var "DB_CONNECTION" "mysql"
update_env_var "DB_HOST" "127.0.0.1"
update_env_var "DB_PORT" "3306"
update_env_var "DB_DATABASE" "line_reservation"
update_env_var "DB_USERNAME" "line_user"
update_env_var "DB_PASSWORD" "$DB_PASS"
update_env_var "SANCTUM_STATEFUL_DOMAINS" "$DOMAIN"
update_env_var "SESSION_DRIVER" "file"
update_env_var "SESSION_LIFETIME" "120"
update_env_var "SESSION_DOMAIN" "$DOMAIN"
update_env_var "SESSION_SAME_SITE" "lax"
update_env_var "CACHE_DRIVER" "file"
update_env_var "QUEUE_CONNECTION" "sync"
update_env_var "LOG_CHANNEL" "stack"
update_env_var "LOG_LEVEL" "info"

echo "🔄 Laravel 清除並重建配置快取..."
sudo -u $USER php artisan config:clear
sudo -u $USER php artisan config:cache

echo "🔑 生成 Laravel 應用金鑰..."
sudo -u $USER php artisan key:generate --force

echo "🗄️ 執行資料庫遷移..."
sudo -u $USER php artisan migrate --force

echo "🗄️ 執行資料庫種子..."
if ! sudo -u $USER php artisan db:seed --force 2>/dev/null; then
    echo "⚠️ 種子可能已存在，跳過"
fi

echo "🔗 建立 Storage 連結..."
if ! sudo -u $USER php artisan storage:link 2>/dev/null; then
    echo "⚠️ Storage 連結已存在，跳過"
fi

# 前端安裝
cd ../frontend

if [ ! -f .env ]; then
    cp .env.example .env
fi

update_frontend_env_var() {
    local key="$1"
    local val="$2"
    local file=".env"
    if grep -q "^${key}=" "$file"; then
        sed -i "s|^${key}=.*|${key}=${val}|" "$file"
    else
        echo "${key}=${val}" >> "$file"
    fi
}

update_frontend_env_var "VITE_API_BASE_URL" "${PROTOCOL}://$DOMAIN/api"
update_frontend_env_var "VITE_APP_BASE_URL" "${PROTOCOL}://$DOMAIN"
update_frontend_env_var "VITE_APP_URL" "${PROTOCOL}://$DOMAIN"
update_frontend_env_var "VITE_BACKEND_URL" "${PROTOCOL}://$DOMAIN/api"

if [ -d node_modules ]; then
    echo "🧹 清理舊的 node_modules..."
    rm -rf node_modules package-lock.json
fi

echo "📦 安裝前端依賴..."
sudo -u $USER npm install

echo "🏗️ 建立前端生產版本..."
if [ -d dist ]; then
    rm -rf dist
fi
sudo -u $USER npm run build

echo "🔧 調整權限..."
sudo chown -R www-data:www-data "$PROJECT_DIR"
find "$PROJECT_DIR" -type d -exec sudo chmod 755 {} \;
find "$PROJECT_DIR" -type f -exec sudo chmod 644 {} \;

sudo chmod -R 775 "$PROJECT_DIR/backend/storage" "$PROJECT_DIR/backend/bootstrap/cache"
sudo chmod -R g+s "$PROJECT_DIR/backend/storage" "$PROJECT_DIR/backend/bootstrap/cache"
sudo chmod -R 600 "$PROJECT_DIR/frontend/.env" "$PROJECT_DIR/backend/.env" "$PROJECT_DIR/frontend/.env.example" "$PROJECT_DIR/backend/.env.example"

echo "🖥️ 設定 Apache 虛擬主機..."

APACHE_CONF="/etc/apache2/sites-available/line-reservation.conf"

if [ "$USE_SSL" = true ]; then
    # SSL 配置 (HTTPS)
    sudo tee "$APACHE_CONF" > /dev/null <<EOF
<VirtualHost *:80>
    ServerName $DOMAIN
    DocumentRoot $PROJECT_DIR/frontend/dist
    
    # 重定向所有 HTTP 流量到 HTTPS
    RewriteEngine On
    RewriteCond %{HTTPS} off
    RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [R=301,L]
</VirtualHost>

<VirtualHost *:443>
    ServerName $DOMAIN
    DocumentRoot $PROJECT_DIR/frontend/dist

    # SSL 配置
    SSLEngine on
    SSLCertificateFile /etc/letsencrypt/live/$DOMAIN/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/$DOMAIN/privkey.pem
    
    # 安全標頭
    Header always set Strict-Transport-Security "max-age=63072000; includeSubDomains; preload"
    Header always set X-Frame-Options DENY
    Header always set X-Content-Type-Options nosniff
    Header always set Referrer-Policy "strict-origin-when-cross-origin"

    # 前端靜態檔案服務
    <Directory $PROJECT_DIR/frontend/dist>
        AllowOverride All
        Require all granted
        Options FollowSymLinks
        
        # Vue Router 歷史模式支援
        RewriteEngine On
        RewriteBase /
        RewriteRule ^index\.html$ - [L]
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteCond %{REQUEST_URI} !^/api/
        RewriteCond %{REQUEST_URI} !^/storage/
        RewriteRule . /index.html [L]
    </Directory>

    # API 路由代理到後端
    RewriteEngine On
    RewriteRule ^/api/(.*)$ $PROJECT_DIR/backend/public/index.php [QSA,L]

    <Directory $PROJECT_DIR/backend/public>
        AllowOverride All
        Require all granted
        Options FollowSymLinks

        <FilesMatch "\.php$">
            SetHandler "$PHP_FPM_HANDLER"
        </FilesMatch>
    </Directory>

    # 後端存儲檔案
    Alias /storage $PROJECT_DIR/backend/storage/app/public
    <Directory $PROJECT_DIR/backend/storage/app/public>
        AllowOverride None
        Require all granted
        Options FollowSymLinks
    </Directory>

    ErrorLog \${APACHE_LOG_DIR}/line-reservation_error.log
    CustomLog \${APACHE_LOG_DIR}/line-reservation_access.log combined
</VirtualHost>
EOF
else
    # 非 SSL 配置 (HTTP)
    sudo tee "$APACHE_CONF" > /dev/null <<EOF
<VirtualHost *:80>
    ServerName $DOMAIN
    DocumentRoot $PROJECT_DIR/frontend/dist

    # 前端靜態檔案服務
    <Directory $PROJECT_DIR/frontend/dist>
        AllowOverride All
        Require all granted
        Options FollowSymLinks
        
        # Vue Router 歷史模式支援
        RewriteEngine On
        RewriteBase /
        RewriteRule ^index\.html$ - [L]
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteCond %{REQUEST_URI} !^/api/
        RewriteCond %{REQUEST_URI} !^/storage/
        RewriteRule . /index.html [L]
    </Directory>

    # API 路由代理到後端
    RewriteEngine On
    RewriteRule ^/api/(.*)$ $PROJECT_DIR/backend/public/index.php [QSA,L]

    <Directory $PROJECT_DIR/backend/public>
        AllowOverride All
        Require all granted
        Options FollowSymLinks

        <FilesMatch "\.php$">
            SetHandler "$PHP_FPM_HANDLER"
        </FilesMatch>
    </Directory>

    # 後端存儲檔案
    Alias /storage $PROJECT_DIR/backend/storage/app/public
    <Directory $PROJECT_DIR/backend/storage/app/public>
        AllowOverride None
        Require all granted
        Options FollowSymLinks
    </Directory>

    ErrorLog \${APACHE_LOG_DIR}/line-reservation_error.log
    CustomLog \${APACHE_LOG_DIR}/line-reservation_access.log combined
</VirtualHost>
EOF
fi

echo "🔐 啟用站台..."
sudo a2ensite line-reservation.conf
sudo a2dissite 000-default.conf || true

# 如果使用 SSL，設置 Let's Encrypt 憑證
if [ "$USE_SSL" = true ]; then
    echo "🔒 設定 SSL 憑證..."
    
    # 首先重啟 Apache 以確保配置生效
    sudo systemctl reload apache2
    
    # 獲取 SSL 憑證
    echo "📜 正在獲取 SSL 憑證..."
    if sudo certbot --apache -d "$DOMAIN" --non-interactive --agree-tos --email "admin@$DOMAIN" --redirect; then
        echo "✅ SSL 憑證設置成功！"
    else
        echo "⚠️ SSL 憑證設置失敗，但網站仍可通過 HTTP 訪問"
        echo "您可以稍後手動執行: sudo certbot --apache -d $DOMAIN"
    fi
    
    # 設置自動更新憑證
    echo "🔄 設定憑證自動更新..."
    (crontab -l 2>/dev/null; echo "0 12 * * * /usr/bin/certbot renew --quiet") | crontab -
fi

echo "🔧 重啟 Apache..."
sudo systemctl reload apache2

echo "🔥 設定 UFW 防火牆..."
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw --force enable

echo "✅ 部署完成！"
echo "網站應該已可透過 ${PROTOCOL}://$DOMAIN 訪問。"
echo "MySQL 資料庫憑證已存於 $CRED_FILE"
if [ "$USE_SSL" = true ]; then
    echo "🔒 SSL 已啟用，網站使用 HTTPS 加密連線"
    echo "HTTP 流量將自動重定向到 HTTPS"
else
    echo "🌐 使用 IP 訪問，未啟用 SSL"
fi
echo "請確保 DNS 已指向此伺服器 IP 或直接使用 IP 訪問。"

exit 0
