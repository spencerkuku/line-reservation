# LINE Reservation System 部署指南

本文檔提供兩種部署方式：**一般架設**和 **Docker 容器化部署**

## 📋 系統需求

### 基本需求
- **作業系統**: Ubuntu 20.04+ / CentOS 8+ / Debian 11+
- **PHP**: 8.1+
- **Node.js**: 18+
- **MySQL**: 8.0+
- **Web Server**: Nginx 或 Apache
- **記憶體**: 最少 2GB，建議 4GB+
- **硬碟**: 最少 10GB 可用空間

### LINE Bot 需求
- LINE Developer Account
- LINE Bot Channel
- SSL 憑證 (HTTPS)

---

## 🚀 方式一：一般架設步驟

### 1. 環境準備

#### 更新系統套件
```bash
# Ubuntu/Debian
sudo apt update && sudo apt upgrade -y

```

#### 安裝 PHP 8.1+
```bash
# Ubuntu/Debian
sudo apt install -y php8.1 php8.1-fpm php8.1-mysql php8.1-xml php8.1-curl \
    php8.1-mbstring php8.1-zip php8.1-gd php8.1-bcmath php8.1-redis

```

#### 安裝 MySQL 8.0
```bash
# Ubuntu/Debian
sudo apt install -y mysql-server

```

#### 安裝 Node.js 18+
```bash
# 使用 NodeSource 安裝
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt install -y nodejs

# 或使用 NVM
curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.39.0/install.sh | bash
source ~/.bashrc
nvm install 18
nvm use 18
```

#### 安裝 Composer
```bash
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php
sudo mv composer.phar /usr/local/bin/composer
```

#### 安裝 Nginx
```bash
# Ubuntu/Debian
sudo apt install -y nginx

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
sudo git clone https://github.com/your-repo/line-reservation.git
sudo chown -R www-data:www-data line-reservation
cd line-reservation
```

#### 設定後端
```bash
cd backend

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
```

#### 設定前端
```bash
cd ../frontend

# 安裝 Node.js 依賴
npm install

# 建立生產版本
npm run build

# 設定權限
sudo chown -R www-data:www-data /var/www/line-reservation/frontend/dist
```

### 4. Web Server 設定

#### Nginx 設定
```bash
sudo nano /etc/nginx/sites-available/line-reservation
```

**Nginx 設定檔：**
```nginx
server {
    listen 80;
    server_name your-domain.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name your-domain.com;
    root /var/www/line-reservation/frontend/dist;
    index index.html;

    # SSL 設定
    ssl_certificate /path/to/your/certificate.crt;
    ssl_certificate_key /path/to/your/private.key;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-RSA-AES256-GCM-SHA512:DHE-RSA-AES256-GCM-SHA512;

    # 前端路由
    location / {
        try_files $uri $uri/ /index.html;
    }

    # API 路由
    location /api {
        alias /var/www/line-reservation/backend/public;
        try_files $uri $uri/ @php;
    }

    location @php {
        rewrite ^/api(.*)$ /api/index.php$1 last;
    }

    location ~ ^/api/.*\.php$ {
        root /var/www/line-reservation/backend/public;
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # LINE Webhook
    location /webhook {
        alias /var/www/line-reservation/backend/public;
        try_files $uri @webhook;
    }

    location @webhook {
        rewrite ^/webhook(.*)$ /webhook/index.php$1 last;
    }

    # 安全設定
    location ~ /\. {
        deny all;
    }

    # 快取設定
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
```

#### 啟用網站
```bash
sudo ln -s /etc/nginx/sites-available/line-reservation /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

### 5. SSL 憑證設定

#### 使用 Let's Encrypt (免費)
```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d your-domain.com
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

## 🐳 方式二：Docker 容器化部署

### 1. 安裝 Docker 和 Docker Compose

#### Ubuntu/Debian
```bash
# 安裝 Docker
curl -fsSL https://get.docker.com -o get-docker.sh
sudo sh get-docker.sh
sudo usermod -aG docker $USER

# 安裝 Docker Compose
sudo curl -L "https://github.com/docker/compose/releases/latest/download/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
sudo chmod +x /usr/local/bin/docker-compose
```

### 2. 創建 Docker 設定檔

#### `docker-compose.yml`
```yaml
version: '3.8'

services:
  # MySQL 資料庫
  mysql:
    image: mysql:8.0
    container_name: line-reservation-mysql
    restart: unless-stopped
    environment:
      MYSQL_ROOT_PASSWORD: root_password
      MYSQL_DATABASE: line_reservation
      MYSQL_USER: line_user
      MYSQL_PASSWORD: line_password
    volumes:
      - mysql_data:/var/lib/mysql
      - ./docker/mysql/my.cnf:/etc/mysql/conf.d/my.cnf
    ports:
      - "3306:3306"
    networks:
      - line-reservation-network

  # Redis (快取)
  redis:
    image: redis:7-alpine
    container_name: line-reservation-redis
    restart: unless-stopped
    ports:
      - "6379:6379"
    networks:
      - line-reservation-network

  # PHP-FPM (後端 API)
  php:
    build:
      context: .
      dockerfile: docker/php/Dockerfile
    container_name: line-reservation-php
    restart: unless-stopped
    volumes:
      - ./backend:/var/www/html
      - ./docker/php/php.ini:/usr/local/etc/php/php.ini
    depends_on:
      - mysql
      - redis
    networks:
      - line-reservation-network
    environment:
      - APP_ENV=production
      - DB_HOST=mysql
      - REDIS_HOST=redis

  # Nginx (Web Server)
  nginx:
    build:
      context: .
      dockerfile: docker/nginx/Dockerfile
    container_name: line-reservation-nginx
    restart: unless-stopped
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./frontend/dist:/var/www/html/frontend
      - ./backend/public:/var/www/html/backend
      - ./docker/nginx/nginx.conf:/etc/nginx/nginx.conf
      - ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf
      - ./ssl:/etc/nginx/ssl
    depends_on:
      - php
    networks:
      - line-reservation-network

  # Node.js (前端建構)
  node:
    image: node:18-alpine
    container_name: line-reservation-node
    working_dir: /app
    volumes:
      - ./frontend:/app
    command: sh -c "npm install && npm run build"
    networks:
      - line-reservation-network

networks:
  line-reservation-network:
    driver: bridge

volumes:
  mysql_data:
    driver: local
```

#### `docker/php/Dockerfile`
```dockerfile
FROM php:8.1-fpm

# 安裝系統依賴
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libzip-dev \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip

# 安裝 Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 設定工作目錄
WORKDIR /var/www/html

# 複製應用程式檔案
COPY backend/ .

# 安裝 PHP 依賴
RUN composer install --optimize-autoloader --no-dev

# 設定權限
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 775 storage bootstrap/cache

# 暴露端口
EXPOSE 9000

CMD ["php-fpm"]
```

#### `docker/nginx/Dockerfile`
```dockerfile
FROM nginx:alpine

# 複製設定檔
COPY docker/nginx/nginx.conf /etc/nginx/nginx.conf
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf

# 創建 SSL 目錄
RUN mkdir -p /etc/nginx/ssl

# 暴露端口
EXPOSE 80 443

CMD ["nginx", "-g", "daemon off;"]
```

#### `docker/nginx/default.conf`
```nginx
server {
    listen 80;
    server_name localhost;
    root /var/www/html/frontend;
    index index.html;

    # 前端路由
    location / {
        try_files $uri $uri/ /index.html;
    }

    # API 路由
    location /api {
        root /var/www/html/backend;
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        root /var/www/html/backend;
        fastcgi_pass php:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # LINE Webhook
    location /webhook {
        root /var/www/html/backend;
        try_files $uri /index.php?$query_string;
    }
}
```

#### `docker/mysql/my.cnf`
```ini
[mysqld]
character-set-server=utf8mb4
collation-server=utf8mb4_unicode_ci
default-time-zone='+08:00'
```

#### `docker/php/php.ini`
```ini
memory_limit = 256M
upload_max_filesize = 50M
post_max_size = 50M
max_execution_time = 300
date.timezone = Asia/Taipei
```

### 3. 環境設定檔

#### `.env.docker`
```env
APP_NAME="LINE Reservation System"
APP_ENV=production
APP_KEY=base64:your_generated_app_key
APP_DEBUG=false
APP_URL=https://your-domain.com

DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=line_reservation
DB_USERNAME=line_user
DB_PASSWORD=line_password

CACHE_DRIVER=redis
SESSION_DRIVER=redis
REDIS_HOST=redis
REDIS_PORT=6379

LINE_CHANNEL_ACCESS_TOKEN=your_line_channel_access_token
LINE_CHANNEL_SECRET=your_line_channel_secret
```

### 4. 部署腳本

#### `deploy.sh`
```bash
#!/bin/bash

echo "🚀 開始部署 LINE Reservation System..."

# 停止現有容器
echo "📦 停止現有容器..."
docker-compose down

# 拉取最新程式碼
echo "📥 拉取最新程式碼..."
git pull origin main

# 複製環境設定檔
echo "⚙️ 設定環境變數..."
cp .env.docker backend/.env

# 建構並啟動容器
echo "🔨 建構並啟動容器..."
docker-compose up -d --build

# 等待資料庫啟動
echo "⏳ 等待資料庫啟動..."
sleep 30

# 執行資料庫遷移
echo "🗄️ 執行資料庫遷移..."
docker-compose exec php php artisan key:generate
docker-compose exec php php artisan migrate --force
docker-compose exec php php artisan db:seed --force

# 清除快取
echo "🧹 清除快取..."
docker-compose exec php php artisan cache:clear
docker-compose exec php php artisan config:clear
docker-compose exec php php artisan route:clear

# 建構前端
echo "🎨 建構前端..."
docker-compose run --rm node

echo "✅ 部署完成！"
echo "🌐 請訪問: http://your-domain.com"
```

### 5. 執行部署

```bash
# 給予執行權限
chmod +x deploy.sh

# 執行部署
./deploy.sh
```

### 6. Docker 管理指令

```bash
# 查看容器狀態
docker-compose ps

# 查看日誌
docker-compose logs -f

# 進入容器
docker-compose exec php bash
docker-compose exec mysql mysql -u root -p

# 重啟服務
docker-compose restart php
docker-compose restart nginx

# 備份資料庫
docker-compose exec mysql mysqldump -u root -p line_reservation > backup.sql

# 還原資料庫
docker-compose exec -T mysql mysql -u root -p line_reservation < backup.sql
```

---

## 🔒 安全性設定

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

# Nginx 日誌
tail -f /var/log/nginx/access.log
tail -f /var/log/nginx/error.log

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
sudo systemctl status php8.1-fpm
sudo systemctl restart php8.1-fpm
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