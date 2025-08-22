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

# 安全權限設置函數
set_secure_permissions() {
    echo "🔧 設置生產環境安全權限..."
    
    # 設置基本擁有者權限
    sudo chown -R www-data:www-data "$PROJECT_DIR"
    
    # 設置目錄權限 (755 - 僅擁有者可寫入)
    find "$PROJECT_DIR" -type d -exec sudo chmod 755 {} \;
    
    # 設置一般檔案權限 (644 - 僅擁有者可寫入，無執行權限)
    find "$PROJECT_DIR" -type f -exec sudo chmod 644 {} \;
    
    # 設置 Laravel 必要的寫入權限目錄 (限制為 755，不使用 775)
    sudo chmod -R 755 "$PROJECT_DIR/backend/storage" "$PROJECT_DIR/backend/bootstrap/cache"
    sudo chown -R www-data:www-data "$PROJECT_DIR/backend/storage" "$PROJECT_DIR/backend/bootstrap/cache"
    sudo chmod -R g+s "$PROJECT_DIR/backend/storage" "$PROJECT_DIR/backend/bootstrap/cache"
    
    # 嚴格保護敏感配置檔案 (600 - 僅擁有者可讀寫)
    sudo chmod 600 "$PROJECT_DIR/frontend/.env" "$PROJECT_DIR/backend/.env"
    sudo chmod 600 "$PROJECT_DIR/frontend/.env.example" "$PROJECT_DIR/backend/.env.example"
    sudo chmod 600 "$PROJECT_DIR/db_credentials.txt" 2>/dev/null || true
    
    # 確保 artisan 有執行權限 (Laravel CLI 需要)
    sudo chmod 755 "$PROJECT_DIR/backend/artisan"
    
    # 移除常見檔案類型的不必要執行權限
    find "$PROJECT_DIR" -type f \( -name "*.php" -o -name "*.js" -o -name "*.vue" -o -name "*.css" -o -name "*.html" -o -name "*.json" -o -name "*.md" -o -name "*.txt" -o -name "*.log" \) -exec sudo chmod 644 {} \;
    
    # 保護配置檔案
    find "$PROJECT_DIR" -type f \( -name "composer.json" -o -name "composer.lock" -o -name "package.json" -o -name "package-lock.json" \) -exec sudo chmod 644 {} \;
    
    echo "✅ 生產環境安全權限設置完成"
}

# 自動偵測 IP
SERVER_IP=$(hostname -I | awk '{print $1}')

# 讓用戶選擇使用 IP 或 domain
echo "🌐 請選擇訪問方式："
echo "1) 使用自動偵測的 IP 地址 ($SERVER_IP)"
echo "2) 使用自定義 IP/域名"
echo ""
read -p "請輸入選擇 (1 或 2): " CHOICE

if [ "$CHOICE" = "2" ]; then
    read -p "請輸入您的自定義 IP 地址或域名: " DOMAIN
    if [ -z "$DOMAIN" ]; then
        echo "❌ IP/域名不能為空！"
        exit 1
    fi
    echo "🌐 將使用自定義 IP/域名: $DOMAIN"
elif [ "$CHOICE" = "1" ]; then
    DOMAIN=$SERVER_IP
    echo "🌐 將使用自動偵測的 IP 地址: $DOMAIN"
else
    echo "❌ 無效的選擇！"
    exit 1
fi

# 詢問是否使用 SSL
echo ""
echo "🔒 是否要設定 SSL 憑證？"
echo "注意：SSL 需要有效的域名，不適用於 IP 地址"
read -p "是否使用 SSL? (y/N): " SSL_CHOICE

USE_SSL=false
if [[ "$SSL_CHOICE" =~ ^[Yy]$ ]]; then
    # 檢查是否為 IP 地址
    if [[ $DOMAIN =~ ^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
        echo "⚠️ 警告：IP 地址無法使用 SSL 憑證，將使用 HTTP"
        USE_SSL=false
        PROTOCOL="http"
    else
        USE_SSL=true
        PROTOCOL="https"
        echo "🔒 將設定 SSL 憑證"
    fi
else
    USE_SSL=false
    PROTOCOL="http"
    echo "🌐 將使用 HTTP（不使用 SSL）"
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
    cd "$PROJECT_DIR" || { echo "❌ 無法進入專案目錄"; exit 1; }
    git config --global --add safe.directory "$PROJECT_DIR"
    if ! git pull origin single-user-version 2>/dev/null; then
        echo "⚠️ Git 更新失敗，將嘗試重新下載..."
        cd /var/www
        sudo rm -rf line-reservation
        if ! sudo git clone -b single-user-version https://github.com/spencerkuku/line-reservation.git; then
            echo "❌ Git 下載失敗，請檢查網路連線"
            exit 1
        fi
        sudo chown -R $USER:$USER line-reservation
    fi
else
    echo "📥 下載專案代碼..."
    cd /var/www || { echo "❌ 無法進入 /var/www 目錄"; exit 1; }
    if ! sudo git clone -b single-user-version https://github.com/spencerkuku/line-reservation.git; then
        echo "❌ Git 下載失敗，請檢查網路連線和倉庫權限"
        exit 1
    fi
    sudo chown -R $USER:$USER line-reservation
fi

git config --global --add safe.directory "$PROJECT_DIR"
cd "$PROJECT_DIR" || { echo "❌ 無法進入專案目錄"; exit 1; }

# 備份資料庫憑證
cp "$CRED_FILE" "$PROJECT_DIR/db_credentials.txt"
chmod 600 "$PROJECT_DIR/db_credentials.txt"
echo "📄 資料庫憑證備份已建立於 $PROJECT_DIR/db_credentials.txt"

# 後端安裝
cd backend || { echo "❌ 無法進入後端目錄"; exit 1; }

echo "📦 安裝 PHP 依賴..."
if ! sudo -u $USER composer install --optimize-autoloader --no-dev; then
    echo "❌ Composer 安裝失敗"
    exit 1
fi

# 環境檔案
if [ ! -f .env ]; then
    cp .env.example .env
fi

update_env_var() {
    local key="$1"
    local val="$2"
    local file=".env"
    
    if [ ! -f "$file" ]; then
        echo "❌ 環境檔案不存在: $file"
        return 1
    fi
    
    if grep -q "^${key}=" "$file" 2>/dev/null; then
        if sed -i "s|^${key}=.*|${key}=${val}|" "$file"; then
            echo "✅ 已更新 $key"
        else
            echo "❌ 更新失敗: $key"
            return 1
        fi
    else
        if echo "${key}=${val}" >> "$file"; then
            echo "✅ 已添加 $key"
        else
            echo "❌ 添加失敗: $key"
            return 1
        fi
    fi
}

update_env_var "APP_ENV" "production"
update_env_var "APP_DEBUG" "false"
update_env_var "APP_URL" "${PROTOCOL}://$DOMAIN"
update_env_var "FRONTEND_URL" "${PROTOCOL}://$DOMAIN"
update_env_var "CORS_ALLOWED_ORIGINS" "${PROTOCOL}://$DOMAIN"
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
update_env_var "SESSION_SECURE_COOKIE" "$USE_SSL"
update_env_var "CACHE_DRIVER" "file"
update_env_var "QUEUE_CONNECTION" "sync"
update_env_var "LOG_CHANNEL" "stack"
update_env_var "LOG_LEVEL" "info"
update_env_var "APP_TIMEZONE" "Asia/Taipei"

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
cd ../frontend || { echo "❌ 無法進入前端目錄"; exit 1; }

if [ ! -f .env ]; then
    cp .env.example .env
fi

update_frontend_env_var() {
    local key="$1"
    local val="$2"
    local file=".env"
    
    if [ ! -f "$file" ]; then
        echo "❌ 前端環境檔案不存在: $file"
        return 1
    fi
    
    if grep -q "^${key}=" "$file" 2>/dev/null; then
        if sed -i "s|^${key}=.*|${key}=${val}|" "$file"; then
            echo "✅ 已更新前端 $key"
        else
            echo "❌ 前端更新失敗: $key"
            return 1
        fi
    else
        if echo "${key}=${val}" >> "$file"; then
            echo "✅ 已添加前端 $key"
        else
            echo "❌ 前端添加失敗: $key"
            return 1
        fi
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
if ! sudo -u $USER npm install; then
    echo "❌ npm install 失敗"
    exit 1
fi

echo "🏗️ 建立前端生產版本..."
if [ -d dist ]; then
    rm -rf dist
fi
if ! sudo -u $USER npm run build; then
    echo "❌ npm run build 失敗"
    exit 1
fi

echo "🔧 調整權限..."
set_secure_permissions

echo "� 設定 Apache 安全配置..."
# 隱藏 Apache 版本信息
sudo tee -a /etc/apache2/apache2.conf > /dev/null <<EOF

# 安全設定 - 隱藏版本信息
ServerTokens Prod
ServerSignature Off

# 安全標頭設定
Header always set X-Content-Type-Options "nosniff"
Header always set X-Frame-Options "SAMEORIGIN"
Header always set Referrer-Policy "strict-origin-when-cross-origin"
Header always set X-XSS-Protection "1; mode=block"
EOF

echo "�🖥️ 設定 Apache 虛擬主機..."

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

# ==================== 資料庫備份系統設置 ====================
echo "💾 設置資料庫備份系統..."

# 建立備份相關目錄
BACKUP_BASE_DIR="$USER_HOME/line-reservation-backups"
DB_BACKUP_DIR="$BACKUP_BASE_DIR/database"
LOG_DIR="$BACKUP_BASE_DIR/logs"
SCRIPTS_DIR="$BACKUP_BASE_DIR/scripts"

echo "📁 建立備份目錄結構..."
mkdir -p "$DB_BACKUP_DIR" "$LOG_DIR" "$SCRIPTS_DIR"
# 設置嚴格的備份目錄權限 (僅擁有者可存取)
sudo chown -R $USER:$USER "$BACKUP_BASE_DIR"
sudo chmod -R 700 "$BACKUP_BASE_DIR"  # 700 而非 755，更安全

# 建立備份配置文件
BACKUP_CONFIG="$SCRIPTS_DIR/backup.conf"
cat > "$BACKUP_CONFIG" <<EOF
# 資料庫備份配置
DB_NAME="line_reservation"
DB_USER="line_user"
BACKUP_RETENTION_DAYS=30
BACKUP_DIR="$DB_BACKUP_DIR"
LOG_DIR="$LOG_DIR"
CREDENTIALS_FILE="$USER_HOME/.line-reservation-credentials"

# 備份檔案命名格式
BACKUP_PREFIX="line_reservation_backup"
TIMESTAMP_FORMAT="%Y%m%d_%H%M%S"

# 壓縮設置
USE_COMPRESSION=true
COMPRESSION_LEVEL=6

# 專案備份設置
PROJECT_BACKUP_DIR="$BACKUP_BASE_DIR/project-backups"
PROJECT_BACKUP_RETENTION_DAYS=14
EOF

sudo chown $USER:$USER "$BACKUP_CONFIG"
sudo chmod 600 "$BACKUP_CONFIG"  # 嚴格保護備份配置檔案

# 建立專案備份目錄
mkdir -p "$BACKUP_BASE_DIR/project-backups"
sudo chmod 700 "$BACKUP_BASE_DIR/project-backups"

# 建立主要備份腳本
BACKUP_SCRIPT="$SCRIPTS_DIR/database_backup.sh"
cat > "$BACKUP_SCRIPT" <<'BACKUP_SCRIPT_EOF'
#!/bin/bash

# LINE Reservation 資料庫備份腳本
# 作者: 自動部署腳本生成
# 版本: 2.0

# 設定嚴格模式
set -euo pipefail

# 載入配置
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CONFIG_FILE="$SCRIPT_DIR/backup.conf"

if [[ ! -f "$CONFIG_FILE" ]]; then
    echo "❌ 找不到配置文件: $CONFIG_FILE" >&2
    exit 1
fi

source "$CONFIG_FILE"

# 設定日誌函數
log() {
    local level="$1"
    shift
    local message="$*"
    local timestamp=$(date '+%Y-%m-%d %H:%M:%S')
    echo "[$timestamp] [$level] $message" | tee -a "$LOG_DIR/backup.log"
}

# 錯誤處理
error_exit() {
    log "ERROR" "$1"
    exit 1
}

# 檢查必要工具
check_requirements() {
    local missing_tools=()
    
    command -v mysqldump >/dev/null || missing_tools+=("mysqldump")
    [[ "$USE_COMPRESSION" == "true" ]] && command -v gzip >/dev/null || missing_tools+=("gzip")
    
    if [[ ${#missing_tools[@]} -gt 0 ]]; then
        error_exit "缺少必要工具: ${missing_tools[*]}"
    fi
}

# 讀取資料庫密碼
get_db_password() {
    if [[ ! -f "$CREDENTIALS_FILE" ]]; then
        error_exit "找不到資料庫憑證檔案: $CREDENTIALS_FILE"
    fi
    
    local db_pass
    db_pass=$(grep "^Password:" "$CREDENTIALS_FILE" | cut -d' ' -f2)
    
    if [[ -z "$db_pass" ]]; then
        error_exit "無法從憑證檔案讀取密碼"
    fi
    
    echo "$db_pass"
}

# 測試資料庫連線
test_db_connection() {
    local db_pass="$1"
    
    if ! mysql -u "$DB_USER" -p"$db_pass" -e "SELECT 1;" "$DB_NAME" >/dev/null 2>&1; then
        error_exit "無法連接到資料庫 $DB_NAME"
    fi
    
    log "INFO" "資料庫連線測試成功"
}

# 執行備份
perform_backup() {
    local db_pass="$1"
    local timestamp=$(date +"$TIMESTAMP_FORMAT")
    local backup_filename="${BACKUP_PREFIX}_${timestamp}.sql"
    local backup_filepath="$BACKUP_DIR/$backup_filename"
    
    log "INFO" "開始備份資料庫: $DB_NAME"
    log "INFO" "備份檔案: $backup_filepath"
    
    # 執行 mysqldump
    if ! mysqldump \
        --user="$DB_USER" \
        --password="$db_pass" \
        --host=localhost \
        --port=3306 \
        --single-transaction \
        --routines \
        --triggers \
        --events \
        --hex-blob \
        --add-drop-database \
        --no-tablespaces \
        --databases "$DB_NAME" > "$backup_filepath" 2>/dev/null; then
        error_exit "資料庫備份失敗"
    fi
    
    # 檢查備份檔案大小
    local file_size=$(stat -c%s "$backup_filepath")
    if [[ $file_size -lt 1024 ]]; then
        error_exit "備份檔案過小，可能備份失敗: ${file_size} bytes"
    fi
    
    log "INFO" "備份完成，檔案大小: $(du -h "$backup_filepath" | cut -f1)"
    
    # 壓縮備份檔案
    if [[ "$USE_COMPRESSION" == "true" ]]; then
        log "INFO" "正在壓縮備份檔案..."
        
        if gzip -"$COMPRESSION_LEVEL" "$backup_filepath"; then
            backup_filepath="${backup_filepath}.gz"
            local compressed_size=$(du -h "$backup_filepath" | cut -f1)
            log "INFO" "壓縮完成，壓縮後大小: $compressed_size"
        else
            log "WARN" "壓縮失敗，保留原始檔案"
        fi
    fi
    
    # 設定檔案權限
    chmod 600 "$backup_filepath"
    
    log "INFO" "備份成功完成: $backup_filepath"
    echo "$backup_filepath"
}

# 清理舊備份
cleanup_old_backups() {
    log "INFO" "開始清理 $BACKUP_RETENTION_DAYS 天前的舊備份..."
    
    local deleted_count=0
    while IFS= read -r -d '' file; do
        rm -f "$file"
        ((deleted_count++))
        log "INFO" "已刪除舊備份: $(basename "$file")"
    done < <(find "$BACKUP_DIR" -name "${BACKUP_PREFIX}_*.sql*" -mtime +$BACKUP_RETENTION_DAYS -print0)
    
    if [[ $deleted_count -eq 0 ]]; then
        log "INFO" "沒有需要清理的舊備份"
    else
        log "INFO" "共清理了 $deleted_count 個舊備份檔案"
    fi
}

# 生成備份報告
generate_report() {
    local backup_file="$1"
    local backup_count=$(find "$BACKUP_DIR" -name "${BACKUP_PREFIX}_*.sql*" | wc -l)
    local total_size=$(du -sh "$BACKUP_DIR" 2>/dev/null | cut -f1)
    
    log "INFO" "=== 備份報告 ==="
    log "INFO" "最新備份: $(basename "$backup_file")"
    log "INFO" "備份總數: $backup_count"
    log "INFO" "備份目錄總大小: $total_size"
    log "INFO" "=============="
}

# 主要流程
main() {
    local start_time=$(date)
    
    log "INFO" "開始執行資料庫備份..."
    log "INFO" "開始時間: $start_time"
    
    # 檢查執行環境
    check_requirements
    
    # 確保備份目錄存在
    mkdir -p "$BACKUP_DIR" "$LOG_DIR"
    
    # 獲取資料庫密碼
    local db_pass
    db_pass=$(get_db_password)
    
    # 測試資料庫連線
    test_db_connection "$db_pass"
    
    # 執行備份
    local backup_file
    backup_file=$(perform_backup "$db_pass")
    
    # 清理舊備份
    cleanup_old_backups
    
    # 生成報告
    generate_report "$backup_file"
    
    local end_time=$(date)
    log "INFO" "備份完成時間: $end_time"
    log "INFO" "資料庫備份流程全部完成"
}

# 執行主程序
main "$@"
BACKUP_SCRIPT_EOF

# 設定腳本權限 (僅擁有者可執行)
sudo chmod 700 "$BACKUP_SCRIPT"  # 700 而非 755，更安全
sudo chown $USER:$USER "$BACKUP_SCRIPT"

# 建立手動備份腳本
MANUAL_BACKUP_SCRIPT="$SCRIPTS_DIR/manual_backup.sh"
cat > "$MANUAL_BACKUP_SCRIPT" <<'MANUAL_SCRIPT_EOF'
#!/bin/bash

# 手動備份腳本
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BACKUP_SCRIPT="$SCRIPT_DIR/database_backup.sh"

echo "🚀 開始手動資料庫備份..."
echo "============================================"

if [[ -f "$BACKUP_SCRIPT" ]]; then
    "$BACKUP_SCRIPT"
    echo "============================================"
    echo "✅ 手動備份完成！"
    echo "📁 備份檔案位置: $(dirname "$BACKUP_SCRIPT")/../database/"
    echo "📄 日誌檔案: $(dirname "$BACKUP_SCRIPT")/../logs/backup.log"
else
    echo "❌ 找不到備份腳本: $BACKUP_SCRIPT"
    exit 1
fi
MANUAL_SCRIPT_EOF

sudo chmod 700 "$MANUAL_BACKUP_SCRIPT"  # 700 而非 755，更安全
sudo chown $USER:$USER "$MANUAL_BACKUP_SCRIPT"

# 建立備份狀態檢查腳本
STATUS_SCRIPT="$SCRIPTS_DIR/backup_status.sh"
cat > "$STATUS_SCRIPT" <<'STATUS_SCRIPT_EOF'
#!/bin/bash

# 備份狀態檢查腳本
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "$SCRIPT_DIR/backup.conf"

echo "📊 LINE Reservation 資料庫備份狀態"
echo "============================================"

# 檢查備份目錄
if [[ -d "$BACKUP_DIR" ]]; then
    echo "✅ 備份目錄: $BACKUP_DIR"
    echo "📁 備份檔案數量: $(find "$BACKUP_DIR" -name "*.sql*" | wc -l)"
    echo "💾 備份目錄大小: $(du -sh "$BACKUP_DIR" 2>/dev/null | cut -f1)"
    
    # 顯示最新的5個備份
    echo ""
    echo "📄 最新備份檔案 (最近5個):"
    find "$BACKUP_DIR" -name "line_reservation_backup_*.sql*" -type f -printf '%T@ %p\n' | \
        sort -nr | head -5 | while read timestamp file; do
        local date_str=$(date -d "@$timestamp" '+%Y-%m-%d %H:%M:%S')
        local size=$(du -h "$file" | cut -f1)
        echo "  ⏰ $date_str - $(basename "$file") ($size)"
    done
else
    echo "❌ 備份目錄不存在: $BACKUP_DIR"
fi

echo ""

# 檢查 crontab
echo "⏰ Cron 排程狀態:"
if crontab -l 2>/dev/null | grep -q "database_backup.sh"; then
    echo "✅ 自動備份已設定"
    crontab -l | grep "database_backup.sh"
else
    echo "❌ 未設定自動備份"
fi

echo ""

# 檢查日誌
if [[ -f "$LOG_DIR/backup.log" ]]; then
    echo "📋 最新備份日誌 (最後10行):"
    tail -10 "$LOG_DIR/backup.log"
else
    echo "❓ 尚無備份日誌檔案"
fi

echo "============================================"
STATUS_SCRIPT_EOF

sudo chmod 700 "$STATUS_SCRIPT"  # 700 而非 755，更安全
sudo chown $USER:$USER "$STATUS_SCRIPT"

# 建立專案備份腳本
PROJECT_BACKUP_SCRIPT="$SCRIPTS_DIR/project_backup.sh"
cat > "$PROJECT_BACKUP_SCRIPT" <<'PROJECT_BACKUP_SCRIPT_EOF'
#!/bin/bash

# LINE Reservation 專案備份腳本
# 用於備份整個專案或環境配置

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CONFIG_FILE="$SCRIPT_DIR/backup.conf"

if [[ ! -f "$CONFIG_FILE" ]]; then
    echo "❌ 找不到配置文件: $CONFIG_FILE" >&2
    exit 1
fi

source "$CONFIG_FILE"

PROJECT_DIR="/var/www/line-reservation"
PROJECT_BACKUP_DIR="${PROJECT_BACKUP_DIR:-$BACKUP_BASE_DIR/project-backups}"

log() {
    local level="$1"
    shift
    local message="$*"
    local timestamp=$(date '+%Y-%m-%d %H:%M:%S')
    echo "[$timestamp] [$level] $message" | tee -a "$LOG_DIR/project_backup.log"
}

backup_env() {
    local timestamp=$(date +"$TIMESTAMP_FORMAT")
    local backup_name="env_backup_${timestamp}"
    local backup_path="$PROJECT_BACKUP_DIR/$backup_name"
    
    log "INFO" "開始備份環境配置檔案..."
    
    mkdir -p "$backup_path"
    chmod 700 "$backup_path"
    
    # 備份環境檔案
    [ -f "$PROJECT_DIR/frontend/.env" ] && cp "$PROJECT_DIR/frontend/.env" "$backup_path/frontend.env"
    [ -f "$PROJECT_DIR/backend/.env" ] && cp "$PROJECT_DIR/backend/.env" "$backup_path/backend.env"
    [ -f "$PROJECT_DIR/db_credentials.txt" ] && cp "$PROJECT_DIR/db_credentials.txt" "$backup_path/db_credentials.txt"
    
    chmod 600 "$backup_path"/* 2>/dev/null || true
    
    log "INFO" "環境配置備份完成: $backup_path"
    echo "$backup_path"
}

backup_project() {
    local timestamp=$(date +"$TIMESTAMP_FORMAT")
    local backup_name="project_backup_${timestamp}.tar.gz"
    local backup_path="$PROJECT_BACKUP_DIR/$backup_name"
    
    log "INFO" "開始備份整個專案..."
    
    mkdir -p "$PROJECT_BACKUP_DIR"
    chmod 700 "$PROJECT_BACKUP_DIR"
    
    # 創建專案備份，排除不必要的檔案
    tar --exclude="$PROJECT_DIR/backend/vendor" \
        --exclude="$PROJECT_DIR/backend/storage/logs/*" \
        --exclude="$PROJECT_DIR/backend/storage/framework/cache/*" \
        --exclude="$PROJECT_DIR/backend/storage/framework/sessions/*" \
        --exclude="$PROJECT_DIR/backend/storage/framework/views/*" \
        --exclude="$PROJECT_DIR/frontend/node_modules" \
        --exclude="$PROJECT_DIR/frontend/dist" \
        --exclude="$PROJECT_DIR/.git" \
        -czf "$backup_path" \
        -C "$(dirname "$PROJECT_DIR")" \
        "$(basename "$PROJECT_DIR")" 2>/dev/null
    
    chmod 600 "$backup_path"
    
    local file_size=$(du -h "$backup_path" | cut -f1)
    log "INFO" "專案備份完成: $backup_path (大小: $file_size)"
    echo "$backup_path"
}

cleanup_old_backups() {
    local retention_days="${PROJECT_BACKUP_RETENTION_DAYS:-14}"
    
    log "INFO" "清理 $retention_days 天前的舊專案備份..."
    
    local deleted_count=0
    while IFS= read -r -d '' file; do
        rm -f "$file"
        ((deleted_count++))
        log "INFO" "已刪除舊備份: $(basename "$file")"
    done < <(find "$PROJECT_BACKUP_DIR" -name "*_backup_*" -mtime +$retention_days -print0 2>/dev/null)
    
    if [[ $deleted_count -eq 0 ]]; then
        log "INFO" "沒有需要清理的舊備份"
    else
        log "INFO" "共清理了 $deleted_count 個舊備份檔案"
    fi
}

case "${1:-help}" in
    env)
        backup_env
        ;;
    project)
        backup_project
        cleanup_old_backups
        ;;
    cleanup)
        cleanup_old_backups
        ;;
    *)
        echo "用法: $0 {env|project|cleanup}"
        echo "  env     - 備份環境配置檔案"
        echo "  project - 備份整個專案"
        echo "  cleanup - 清理舊備份"
        exit 1
        ;;
esac
PROJECT_BACKUP_SCRIPT_EOF

sudo chmod 700 "$PROJECT_BACKUP_SCRIPT"
sudo chown $USER:$USER "$PROJECT_BACKUP_SCRIPT"

# 設定自動備份排程
echo "⏰ 設定自動備份排程..."

# 清理現有的備份相關 crontab
sudo -u $USER crontab -l 2>/dev/null | grep -v "backup" | sudo -u $USER crontab - || true

# 設定新的 crontab（每日凌晨2:30執行）
(sudo -u $USER crontab -l 2>/dev/null; echo "30 2 * * * $BACKUP_SCRIPT >> $LOG_DIR/backup_cron.log 2>&1") | sudo -u $USER crontab -

echo "✅ 資料庫備份系統設置完成！"
echo ""
echo "📋 備份系統資訊:"
echo "  📁 資料庫備份目錄: $DB_BACKUP_DIR"
echo "  📁 專案備份目錄: $BACKUP_BASE_DIR/project-backups"
echo "  📄 日誌目錄: $LOG_DIR"
echo "  🔧 腳本目錄: $SCRIPTS_DIR"
echo "  ⏰ 自動備份時間: 每日凌晨 2:30"
echo "  🗂️ 資料庫備份保留: 30 天"
echo "  🗂️ 專案備份保留: 14 天"
echo ""
echo "📝 可用命令:"
echo "  資料庫備份: $MANUAL_BACKUP_SCRIPT"
echo "  專案環境備份: $PROJECT_BACKUP_SCRIPT env"
echo "  完整專案備份: $PROJECT_BACKUP_SCRIPT project"
echo "  檢查狀態: $STATUS_SCRIPT"
echo "  查看日誌: tail -f $LOG_DIR/backup.log"
echo ""

# 執行一次測試備份
echo "🧪 執行測試備份..."
if sudo -u $USER "$BACKUP_SCRIPT"; then
    echo "✅ 測試備份成功！"
else
    echo "⚠️ 測試備份失敗，請檢查配置"
fi

# 創建初始專案環境配置備份
echo "💾 創建初始環境配置備份..."
INITIAL_BACKUP_DIR="$BACKUP_BASE_DIR/project-backups/initial_env_backup_$(date +%Y%m%d_%H%M%S)"
mkdir -p "$INITIAL_BACKUP_DIR"
sudo chmod 700 "$INITIAL_BACKUP_DIR"

# 備份環境檔案
cp "$PROJECT_DIR/frontend/.env" "$INITIAL_BACKUP_DIR/frontend.env" 2>/dev/null || true
cp "$PROJECT_DIR/backend/.env" "$INITIAL_BACKUP_DIR/backend.env" 2>/dev/null || true
cp "$PROJECT_DIR/db_credentials.txt" "$INITIAL_BACKUP_DIR/db_credentials.txt" 2>/dev/null || true

# 設置備份檔案權限
sudo chmod 600 "$INITIAL_BACKUP_DIR"/* 2>/dev/null || true
sudo chown -R $USER:$USER "$INITIAL_BACKUP_DIR"

echo "✅ 初始環境配置備份已創建: $INITIAL_BACKUP_DIR"

echo "🔧 重啟 Apache..."
sudo systemctl reload apache2

echo "🔥 設定 UFW 防火牆..."
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw --force enable

echo "🗑️ 清理 Git 資料..."
cd "$PROJECT_DIR"

# 智能 Git 清理 - 檢查是否為多人開發環境
echo "🔍 檢查開發環境類型..."

is_multi_dev=false

# 檢查 .gitignore 是否有重要內容
if [ -f ".gitignore" ]; then
    gitignore_size=$(wc -l < .gitignore)
    if [ "$gitignore_size" -gt 10 ]; then
        echo "📋 發現詳細的 .gitignore 檔案 ($gitignore_size 行)"
        is_multi_dev=true
    fi
fi

# 檢查是否有多個遠端分支
if [ -d ".git" ]; then
    remote_branches=$(git branch -r 2>/dev/null | wc -l)
    if [ "$remote_branches" -gt 2 ]; then
        echo "🌿 發現多個遠端分支 ($remote_branches 個)"
        is_multi_dev=true
    fi
fi

if [ "$is_multi_dev" = true ]; then
    echo "⚠️ 檢測到可能的多人開發環境"
    echo "建議保留以下檔案以供未來開發使用："
    echo "  - .gitignore (忽略規則)"
    echo "  - .gitattributes (檔案屬性)"
    echo ""
    read -p "是否要保留 Git 配置檔案? (Y/n): " keep_git_config
    
    if [[ ! "$keep_git_config" =~ ^[Nn]$ ]]; then
        # 僅刪除 .git 目錄，保留配置檔案
        if [ -d ".git" ]; then
            sudo rm -rf .git
            echo "✅ .git 目錄已刪除（保留配置檔案）"
        fi
        
        echo "ℹ️ 已保留 .gitignore 和 .gitattributes 供未來使用"
    else
        # 完全清理
        echo "🧹 完全清理 Git 相關檔案..."
        if [ -d ".git" ]; then
            sudo rm -rf .git
            echo "✅ .git 目錄已刪除"
        fi
        
        if [ -f ".gitignore" ]; then
            sudo rm -f .gitignore
            echo "✅ .gitignore 檔案已刪除"
        fi
        
        if [ -f ".gitattributes" ]; then
            sudo rm -f .gitattributes
            echo "✅ .gitattributes 檔案已刪除"
        fi
        
        # 清理其他 Git 相關檔案
        find . -name ".git*" -type f -delete 2>/dev/null || true
    fi
else
    echo "🏠 檢測到單人生產環境，執行完全清理..."
    if [ -d ".git" ]; then
        sudo rm -rf .git
        echo "✅ .git 目錄已刪除"
    fi
    
    if [ -f ".gitignore" ]; then
        sudo rm -f .gitignore
        echo "✅ .gitignore 檔案已刪除"
    fi
    
    if [ -f ".gitattributes" ]; then
        sudo rm -f .gitattributes
        echo "✅ .gitattributes 檔案已刪除"
    fi
    
    # 清理其他 Git 相關檔案
    find . -name ".git*" -type f -delete 2>/dev/null || true
fi

echo "✅ Git 資料清理完成"

echo "✅ 部署完成！"
echo ""
echo "🌐 網站訪問:"
echo "   網站應該已可透過 ${PROTOCOL}://$DOMAIN 訪問"
if [ "$USE_SSL" = true ]; then
    echo "   🔒 SSL 已啟用，網站使用 HTTPS 加密連線"
    echo "   HTTP 流量將自動重定向到 HTTPS"
else
    echo "   🌐 使用 IP 訪問，未啟用 SSL"
fi
echo "   請確保 DNS 已指向此伺服器 IP 或直接使用 IP 訪問"
echo ""
echo "🔑 重要檔案位置:"
echo "   MySQL 資料庫憑證: $CRED_FILE"
echo "   初始環境備份: $INITIAL_BACKUP_DIR"
echo "   專案目錄: $PROJECT_DIR"
echo ""
echo "🛠️ 管理工具:"
echo "   快速更新工具: $(dirname "$PROJECT_DIR")/quick-update.sh"
echo "   資料庫備份: $MANUAL_BACKUP_SCRIPT"
echo "   專案備份: $PROJECT_BACKUP_SCRIPT env|project"
echo "   備份狀態檢查: $STATUS_SCRIPT"
echo ""
echo "⚠️ 安全建議:"
echo "   1. 定期檢查備份狀態"
echo "   2. 變更配置前先執行備份"
echo "   3. 保護好資料庫憑證檔案"
echo "   4. 定期更新系統套件"

exit 0
