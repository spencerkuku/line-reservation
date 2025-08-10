#!/bin/bash

echo "🚀 開始部署 LINE Reservation System (Apache) - 優化版本..."

# 檢查是否為 root 用戶
if [[ $EUID -eq 0 ]]; then
   echo "❌ 請不要使用 root 用戶執行此腳本"
   exit 1
fi

# 設定變數
PROJECT_DIR="/var/www/line-reservation"

# 自動偵測伺服器 IP 或使用預設域名
SERVER_IP=$(hostname -I | awk '{print $1}')
DOMAIN=${1:-$SERVER_IP}  # 使用命令列參數或自動偵測的 IP

echo "📁 專案目錄: $PROJECT_DIR"
echo "🌐 域名/IP: $DOMAIN"
echo "🔍 偵測到的伺服器 IP: $SERVER_IP"

# 更新系統
echo "📦 更新系統套件..."
sudo apt update && sudo apt upgrade -y

# 安裝 Node.js 20 (最新穩定版)
echo "🔧 安裝 Node.js 20..."
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -

# 安裝必要套件
echo "🔧 安裝必要套件..."
sudo apt install -y apache2 php8.3 php8.3-fpm php8.3-mysql php8.3-xml \
    php8.3-curl php8.3-mbstring php8.3-zip php8.3-gd php8.3-bcmath \
    mysql-server nodejs

# 檢查 Composer 是否已安裝
if ! command -v composer &> /dev/null; then
    echo "📥 安裝 Composer..."
    php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
    php composer-setup.php
    sudo mv composer.phar /usr/local/bin/composer
    rm composer-setup.php
fi

# 啟用 Apache 模組
echo "⚙️ 設定 Apache 模組..."
sudo a2enmod rewrite ssl headers proxy proxy_fcgi setenvif expires

# 自動偵測 PHP-FPM Socket
echo "🔍 偵測 PHP-FPM 配置..."

# 檢測實際安裝的 PHP-FPM 版本
INSTALLED_PHP_VERSIONS=$(systemctl list-units --type=service | grep -o 'php[0-9]\+\.[0-9]\+-fpm' | sed 's/-fpm//' | sed 's/php//')
CLI_PHP_VERSION=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')

echo "📋 CLI PHP 版本: $CLI_PHP_VERSION"
echo "� 已安裝的 PHP-FPM 版本: $INSTALLED_PHP_VERSIONS"

# 優先使用已安裝的 PHP-FPM 服務
PHP_FPM_VERSION=""
for version in $INSTALLED_PHP_VERSIONS; do
    if systemctl is-active --quiet php${version}-fpm; then
        PHP_FPM_VERSION=$version
        echo "✅ 找到運行中的 PHP-FPM: php${version}-fpm"
        break
    fi
done

# 如果沒有運行中的，使用第一個安裝的版本
if [ -z "$PHP_FPM_VERSION" ] && [ -n "$INSTALLED_PHP_VERSIONS" ]; then
    PHP_FPM_VERSION=$(echo $INSTALLED_PHP_VERSIONS | head -n1 | awk '{print $1}')
    echo "⚠️  使用第一個可用的 PHP-FPM 版本: $PHP_FPM_VERSION"
fi

# 如果仍然沒有，使用 CLI 版本
if [ -z "$PHP_FPM_VERSION" ]; then
    PHP_FPM_VERSION=$CLI_PHP_VERSION
    echo "⚠️  使用 CLI PHP 版本: $PHP_FPM_VERSION"
fi

# 檢查 socket 文件
POSSIBLE_SOCKETS=(
    "/run/php/php${PHP_FPM_VERSION}-fpm.sock"
    "/var/run/php/php${PHP_FPM_VERSION}-fpm.sock"
    "/var/run/php-fpm/php-fpm.sock"
)

PHP_FPM_HANDLER=""
for sock in "${POSSIBLE_SOCKETS[@]}"; do
    echo "🔍 檢查 Socket: $sock"
    if [ -S "$sock" ]; then
        echo "✅ 找到 PHP-FPM socket: $sock"
        PHP_FPM_HANDLER="proxy:unix:${sock}|fcgi://localhost"
        SOCK_FILE="$sock"
        break
    fi
done

# 如果仍然找不到 socket，使用 TCP 連接
if [ -z "$PHP_FPM_HANDLER" ]; then
    echo "⚠️  找不到 PHP-FPM socket，改用 TCP: 127.0.0.1:9000"
    PHP_FPM_HANDLER="proxy:fcgi://127.0.0.1:9000"
fi

echo "🔧 使用 PHP-FPM 處理器: $PHP_FPM_HANDLER"
echo "📋 使用 PHP-FPM 版本: $PHP_FPM_VERSION"

# 設定資料庫（如果不存在）
echo "🗄️ 設定資料庫..."

# 啟動 MySQL 服務
sudo systemctl start mysql
sudo systemctl enable mysql

DB_PASS=$(openssl rand -hex 16)
echo "🔑 生成資料庫密碼: $DB_PASS"

# 嘗試多種方式連接 MySQL
MYSQL_CMD=""
echo "🔍 檢測 MySQL 連接方式..."

# 方法1: 嘗試使用 debian-sys-maint
if sudo mysql --defaults-file=/etc/mysql/debian.cnf -e "SELECT 1;" > /dev/null 2>&1; then
    echo "✅ 使用 debian-sys-maint 連接"
    MYSQL_CMD="sudo mysql --defaults-file=/etc/mysql/debian.cnf"
# 方法2: 嘗試直接 sudo mysql
elif sudo mysql -e "SELECT 1;" > /dev/null 2>&1; then
    echo "✅ 使用 sudo mysql 連接"
    MYSQL_CMD="sudo mysql"
# 方法3: 嘗試無密碼連接
elif mysql -e "SELECT 1;" > /dev/null 2>&1; then
    echo "✅ 使用無密碼連接"
    MYSQL_CMD="mysql"
else
    echo "❌ 無法連接到 MySQL，請檢查以下項目："
    echo "   1. MySQL 服務是否正在運行: sudo systemctl status mysql"
    echo "   2. MySQL root 密碼設定: sudo mysql_secure_installation"
    echo "   3. 或手動設定 MySQL："
    echo "      sudo mysql_secure_installation"
    echo "      然後編輯腳本設定 MySQL root 密碼"
    exit 1
fi

$MYSQL_CMD -e "CREATE DATABASE IF NOT EXISTS \`line_reservation\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>/dev/null || true
$MYSQL_CMD -e "CREATE USER IF NOT EXISTS 'line_user'@'localhost' IDENTIFIED BY '${DB_PASS}';" 2>/dev/null || true
$MYSQL_CMD -e "GRANT ALL PRIVILEGES ON \`line_reservation\`.* TO 'line_user'@'localhost';" 2>/dev/null || true
$MYSQL_CMD -e "FLUSH PRIVILEGES;" 2>/dev/null || true

# 確保專案目錄存在後再寫入憑證檔案
CRED_FILE="/tmp/db_credentials_$(date +%s).txt"
echo "📄 資料庫資訊已暫存到 $CRED_FILE"
echo "Database: line_reservation" > $CRED_FILE
echo "Username: line_user" >> $CRED_FILE
echo "Password: $DB_PASS" >> $CRED_FILE

# 下載/更新專案
if [ -d "$PROJECT_DIR" ]; then
    echo "📥 更新現有專案..."
    cd $PROJECT_DIR
    
    # 添加 Git 安全目錄例外
    sudo git config --global --add safe.directory $PROJECT_DIR
    
    # 嘗試更新，如果失敗則跳過（可能是權限或認證問題）
    if ! sudo git pull origin main 2>/dev/null; then
        echo "⚠️  Git 更新失敗，使用現有程式碼繼續部署"
        echo "    如需更新程式碼，請手動執行："
        echo "    cd $PROJECT_DIR && git pull origin main"
    fi
else
    echo "📥 下載專案..."
    cd /var/www
    
    # 嘗試下載，如果失敗則提示手動下載
    if ! sudo git clone https://github.com/spencerkuku/line-reservation.git 2>/dev/null; then
        echo "❌ Git 下載失敗，請先手動下載專案："
        echo "    cd /var/www"
        echo "    sudo git clone https://github.com/spencerkuku/line-reservation.git"
        echo "    或下載 ZIP 檔案並解壓到 $PROJECT_DIR"
        exit 1
    fi
    
    # 添加 Git 安全目錄例外
    sudo git config --global --add safe.directory $PROJECT_DIR
fi

# 切換到專案目錄
cd $PROJECT_DIR

# 移動資料庫憑證檔案到專案目錄
if [ -f "$CRED_FILE" ]; then
    sudo mv "$CRED_FILE" "$PROJECT_DIR/db_credentials.txt"
    sudo chown $USER:$USER "$PROJECT_DIR/db_credentials.txt"
    echo "📄 資料庫憑證已移動到 $PROJECT_DIR/db_credentials.txt"
fi

# 設定後端
echo "🔨 設定後端..."
cd backend

# 確保權限正確
sudo chown -R $USER:$USER .

# 安裝 PHP 依賴
composer install --optimize-autoloader --no-dev

# 設定環境檔案
if [ ! -f .env ]; then
    cp .env.example .env
    echo "📄 創建新的 .env 檔案"
else
    echo "📄 使用現有的 .env 檔案"
fi

# 更新 .env 為當前環境設定
echo "⚙️ 更新後端環境變數..."

# 基本應用設定
sed -i 's/^APP_ENV=.*/APP_ENV=production/' .env
sed -i 's/^APP_DEBUG=.*/APP_DEBUG=false/' .env
sed -i "s|^APP_URL=.*|APP_URL=http://$DOMAIN|" .env

# 前端 URL 設定
grep -q "^FRONTEND_URL=" .env || echo "FRONTEND_URL=http://$DOMAIN" >> .env
sed -i "s|^FRONTEND_URL=.*|FRONTEND_URL=http://$DOMAIN|" .env

# 資料庫設定
sed -i 's/^DB_CONNECTION=.*/DB_CONNECTION=mysql/' .env
sed -i 's/^DB_HOST=.*/DB_HOST=127.0.0.1/' .env
sed -i 's/^DB_PORT=.*/DB_PORT=3306/' .env
sed -i 's/^DB_DATABASE=.*/DB_DATABASE=line_reservation/' .env
sed -i 's/^DB_USERNAME=.*/DB_USERNAME=line_user/' .env
sed -i "s/^DB_PASSWORD=.*/DB_PASSWORD=$DB_PASS/" .env

# Laravel Sanctum CORS 設定
grep -q "^SANCTUM_STATEFUL_DOMAINS=" .env || echo "SANCTUM_STATEFUL_DOMAINS=$DOMAIN" >> .env
sed -i "s|^SANCTUM_STATEFUL_DOMAINS=.*|SANCTUM_STATEFUL_DOMAINS=$DOMAIN|" .env

# Session 和安全設定
sed -i 's/^SESSION_DRIVER=.*/SESSION_DRIVER=file/' .env
sed -i 's/^SESSION_LIFETIME=.*/SESSION_LIFETIME=120/' .env

# Cache 設定
sed -i 's/^CACHE_DRIVER=.*/CACHE_DRIVER=file/' .env
sed -i 's/^QUEUE_CONNECTION=.*/QUEUE_CONNECTION=sync/' .env

# 日誌設定
sed -i 's/^LOG_CHANNEL=.*/LOG_CHANNEL=stack/' .env
sed -i 's/^LOG_LEVEL=.*/LOG_LEVEL=info/' .env

echo "✅ 後端環境變數已更新："
echo "   APP_URL=http://$DOMAIN"
echo "   FRONTEND_URL=http://$DOMAIN"
echo "   SANCTUM_STATEFUL_DOMAINS=$DOMAIN"
echo "   DB_DATABASE=line_reservation"
echo "   DB_USERNAME=line_user"

# 驗證 .env 設定
echo ""
echo "📋 驗證 .env 主要設定："
echo "APP_ENV=$(grep '^APP_ENV=' .env | cut -d'=' -f2)"
echo "APP_DEBUG=$(grep '^APP_DEBUG=' .env | cut -d'=' -f2)"
echo "APP_URL=$(grep '^APP_URL=' .env | cut -d'=' -f2-)"
echo "DB_DATABASE=$(grep '^DB_DATABASE=' .env | cut -d'=' -f2)"
echo "SANCTUM_STATEFUL_DOMAINS=$(grep '^SANCTUM_STATEFUL_DOMAINS=' .env | cut -d'=' -f2-)"

echo ""
echo "⚠️  請編輯 $PROJECT_DIR/backend/.env 檔案，設定正確的 LINE Bot 金鑰："
echo "   LINE_CHANNEL_ACCESS_TOKEN=your_line_channel_access_token"
echo "   LINE_CHANNEL_SECRET=your_line_channel_secret"

echo "⚠️  請編輯 $PROJECT_DIR/backend/.env 檔案，設定正確的 LINE Bot 金鑰"

# 生成應用程式金鑰
php artisan key:generate --force

# 執行資料庫遷移（忽略種子錯誤）
php artisan migrate --force

# 執行資料庫種子（如果失敗則跳過）
if ! php artisan db:seed --force 2>/dev/null; then
    echo "⚠️  資料庫種子可能已存在，跳過種子步驟"
fi

# 建立 Storage 連結 (如果專案有檔案上傳功能，如果已存在則跳過)
if ! php artisan storage:link 2>/dev/null; then
    echo "⚠️  Storage 連結已存在，跳過此步驟"
fi

# 設定前端
echo "🎨 設定前端..."
cd ../frontend

# 確保前端目錄權限正確
sudo chown -R $USER:$USER .

# 設定前端環境檔案
if [ ! -f .env ]; then
    cp .env.example .env
fi

# 更新前端環境變數為當前伺服器配置
echo "⚙️ 更新前端環境變數..."
sed -i "s|localhost:8000|$DOMAIN|g" .env
sed -i "s|localhost:5173|$DOMAIN|g" .env
sed -i "s|http://localhost|http://$DOMAIN|g" .env
sed -i "s|^VITE_API_BASE_URL=.*|VITE_API_BASE_URL=http://$DOMAIN/api|" .env
sed -i "s|^VITE_APP_BASE_URL=.*|VITE_APP_BASE_URL=http://$DOMAIN|" .env
sed -i "s|^VITE_APP_URL=.*|VITE_APP_URL=http://$DOMAIN|" .env
sed -i "s|^VITE_BACKEND_URL=.*|VITE_BACKEND_URL=http://$DOMAIN/api|" .env

echo "✅ 前端環境變數已更新："
echo "   VITE_API_BASE_URL=http://$DOMAIN/api"
echo "   VITE_APP_BASE_URL=http://$DOMAIN"

# 清理舊的 node_modules 如果有權限問題
if [ -d "node_modules" ]; then
    echo "🧹 清理舊的 node_modules..."
    sudo rm -rf node_modules package-lock.json
fi

# 安裝 Node.js 依賴
echo "📦 安裝前端依賴..."
npm install

# 建立生產版本
echo "🏗️  建立前端生產版本..."

# 清理舊的構建文件以確保使用新的環境變數
if [ -d "dist" ]; then
    echo "🧹 清理舊的構建文件..."
    rm -rf dist
fi

npm run build

# 驗證前端環境變數是否正確應用
echo "📋 驗證前端構建結果..."
if [ -f "dist/index.html" ]; then
    echo "✅ 前端構建成功"
    # 檢查構建結果中是否包含正確的 API URL
    if grep -q "$DOMAIN" dist/assets/*.js 2>/dev/null; then
        echo "✅ 前端已使用正確的服務器地址: $DOMAIN"
    else
        echo "⚠️  前端可能仍使用舊的 URL，檢查構建文件中的 URL..."
        echo "   正在搜索 localhost 引用..."
        if grep -l "localhost" dist/assets/*.js 2>/dev/null; then
            echo "❌ 發現 localhost 引用，需要檢查 .env 設定"
        fi
    fi
else
    echo "❌ 前端構建失敗"
    exit 1
fi

# 建立生產版本
npm run build

# 優化權限管理
echo "🔐 設定檔案權限..."
sudo chown -R www-data:www-data $PROJECT_DIR
sudo chmod -R 755 $PROJECT_DIR
sudo chmod -R 775 $PROJECT_DIR/backend/storage $PROJECT_DIR/backend/bootstrap/cache
sudo chmod -R g+s $PROJECT_DIR/backend/storage $PROJECT_DIR/backend/bootstrap/cache

# 創建 Apache 虛擬主機設定
echo "🌐 創建 Apache 虛擬主機設定..."
sudo tee /etc/apache2/sites-available/line-reservation.conf > /dev/null <<EOF
<VirtualHost *:80>
    ServerName $DOMAIN
    DocumentRoot $PROJECT_DIR/frontend/dist
    
    # 前端 SPA 路由處理
    <Directory "$PROJECT_DIR/frontend/dist">
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
        
        # Vue.js Router History Mode 支援 - 保護後端路由
        RewriteEngine On
        RewriteRule ^(api|storage|public|webhook)/ - [L]
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteRule . /index.html [L]
    </Directory>

    # Laravel API 路由處理
    Alias /api $PROJECT_DIR/backend/public
    <Directory "$PROJECT_DIR/backend/public">
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
        
        # 自動偵測的 PHP-FPM 處理
        <FilesMatch "\.php$">
            SetHandler "$PHP_FPM_HANDLER"
        </FilesMatch>
    </Directory>

    # LINE Webhook 路由處理
    Alias /webhook $PROJECT_DIR/backend/public
    <Directory "$PROJECT_DIR/backend/public">
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    # CORS 標頭設定
    Header always set Access-Control-Allow-Origin "https://$DOMAIN"
    Header always set Access-Control-Allow-Methods "GET, POST, PUT, DELETE, OPTIONS"
    Header always set Access-Control-Allow-Headers "Content-Type, Authorization, X-Requested-With, X-XSRF-TOKEN"
    Header always set Access-Control-Allow-Credentials "true"

    # 靜態資源快取設定
    <LocationMatch "\.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$">
        ExpiresActive On
        ExpiresDefault "access plus 1 year"
        Header append Cache-Control "public, immutable"
    </LocationMatch>

    # 錯誤和存取日誌
    ErrorLog \${APACHE_LOG_DIR}/line-reservation_error.log
    CustomLog \${APACHE_LOG_DIR}/line-reservation_access.log combined
</VirtualHost>
EOF

# 啟用網站
echo "🔄 啟用網站..."
sudo a2dissite 000-default
sudo a2ensite line-reservation

# 檢查設定檔語法
echo "✅ 檢查 Apache 設定..."
if sudo apache2ctl configtest; then
    echo "✅ Apache 設定檔語法正確"
else
    echo "❌ Apache 設定檔有誤，請檢查"
    exit 1
fi

# 重啟服務
echo "🔄 重啟服務..."

# 確保 PHP-FPM 服務啟動
echo "🔄 啟動 PHP-FPM 服務: php${PHP_FPM_VERSION}-fpm"
sudo systemctl start php${PHP_FPM_VERSION}-fpm
sudo systemctl enable php${PHP_FPM_VERSION}-fpm

# 檢查 PHP-FPM 狀態
if sudo systemctl is-active --quiet php${PHP_FPM_VERSION}-fpm; then
    echo "✅ PHP-FPM 服務運行中"
else
    echo "❌ PHP-FPM 服務啟動失敗"
    sudo systemctl status php${PHP_FPM_VERSION}-fpm
fi

# 重啟 Apache
sudo systemctl restart apache2
sudo systemctl enable apache2

# 檢查 Apache 狀態
if sudo systemctl is-active --quiet apache2; then
    echo "✅ Apache 服務運行中"
else
    echo "❌ Apache 服務啟動失敗"
    sudo systemctl status apache2
fi

# SSL 自動化設定
echo "🔒 設定 SSL 憑證..."
sudo apt install certbot python3-certbot-apache -y

read -p "是否要自動設定 SSL 憑證？(y/N): " setup_ssl
if [[ $setup_ssl =~ ^[Yy]$ ]]; then
    sudo certbot --apache -d $DOMAIN --non-interactive --agree-tos --email admin@$DOMAIN
    
    # 設定自動續期
    echo "0 3 * * * root certbot renew --quiet" | sudo tee /etc/cron.d/certbot-renew
    echo "✅ SSL 憑證已設定，並啟用自動續期"
else
    echo "⚠️  您可以稍後手動設定 SSL："
    echo "   sudo certbot --apache -d $DOMAIN"
fi

# 測試服務狀態
echo "🧪 最終服務狀態檢查..."
if sudo systemctl is-active --quiet apache2; then
    echo "✅ Apache 運行中"
else
    echo "❌ Apache 未運行"
fi

if sudo systemctl is-active --quiet php${PHP_FPM_VERSION}-fpm; then
    echo "✅ PHP-FPM (${PHP_FPM_VERSION}) 運行中"
else
    echo "❌ PHP-FPM (${PHP_FPM_VERSION}) 未運行"
fi

if sudo systemctl is-active --quiet mysql; then
    echo "✅ MySQL 運行中"
else
    echo "❌ MySQL 未運行"
fi
else
    echo "❌ MySQL 未運行"
fi

echo ""
echo "🎉 部署完成！"
echo ""
echo "📝 後續步驟："
echo "1. 編輯 $PROJECT_DIR/backend/.env 設定："
echo "   - LINE Bot 金鑰"
echo "   - 資料庫密碼"
echo "2. 檢查 Laravel Webhook 路由："
echo "   - 確保 routes/api.php 有 /line/webhook 路由"
echo "3. 設定防火牆："
echo "   sudo ufw allow 80"
echo "   sudo ufw allow 443"
echo "   sudo ufw enable"
echo ""
echo "🌐 網站應該可以在以下地址訪問："
echo "   http://$DOMAIN"
echo "   https://$DOMAIN (如果設定了 SSL)"
echo ""
echo "📊 查看日誌："
echo "   sudo tail -f /var/log/apache2/line-reservation_error.log"
echo "   sudo tail -f $PROJECT_DIR/backend/storage/logs/laravel.log"
