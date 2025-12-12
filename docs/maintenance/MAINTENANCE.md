# 多租戶 B2B 系統維運指南

## 目錄

- [日誌管理](#日誌管理)
- [監控與告警](#監控與告警)
- [定期維護任務](#定期維護任務)
- [備份策略](#備份策略)
- [錯誤處理](#錯誤處理)
- [效能優化](#效能優化)
- [安全維護](#安全維護)

## 日誌管理

### Laravel 日誌位置

```bash
# 應用程式日誌
backend/storage/logs/laravel.log

# 日誌結構
[時間] 環境.級別: 訊息 {"context":"data"}
```

### 日誌級別

| 級別 | 說明 | 使用時機 |
|------|------|----------|
| `DEBUG` | 除錯資訊 | 開發環境詳細追蹤 |
| `INFO` | 一般資訊 | 正常操作記錄 |
| `WARNING` | 警告訊息 | 潛在問題 |
| `ERROR` | 錯誤訊息 | 需要注意的錯誤 |
| `CRITICAL` | 嚴重錯誤 | 系統嚴重問題 |

### 查看日誌

```bash
# 即時查看日誌（推薦）
php artisan pail

# 傳統方式查看最新日誌
tail -f backend/storage/logs/laravel.log

# 查看最後 100 行
tail -n 100 backend/storage/logs/laravel.log

# 搜尋特定錯誤
grep "ERROR" backend/storage/logs/laravel.log

# 搜尋今天的錯誤
grep "$(date +%Y-%m-%d)" backend/storage/logs/laravel.log | grep "ERROR"
```

### Web 伺服器日誌

```bash
# Apache 訪問日誌
tail -f /var/log/apache2/line-reservation_access.log

# Apache 錯誤日誌
tail -f /var/log/apache2/line-reservation_error.log

# MySQL 錯誤日誌
tail -f /var/log/mysql/error.log
```

### 日誌輪替

**設定 logrotate**:
```bash
sudo nano /etc/logrotate.d/line-reservation
```

```conf
/var/www/line-reservation/backend/storage/logs/*.log {
    daily
    missingok
    rotate 14
    compress
    delaycompress
    notifempty
    create 0644 www-data www-data
    sharedscripts
}
```

### 清理舊日誌

```bash
# 手動清理 30 天前的日誌
find backend/storage/logs -name "*.log" -mtime +30 -delete

# 或使用 Laravel 命令（如有自訂）
php artisan log:clear --days=30
```

## 監控與告警

### 系統監控指標

#### 1. 應用程式健康檢查

```bash
# 建立健康檢查端點
# routes/web.php
Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'timestamp' => now(),
        'services' => [
            'database' => DB::connection()->getPdo() ? 'ok' : 'error',
            'cache' => Cache::has('health_check') ? 'ok' : 'error',
        ]
    ]);
});

# 測試健康檢查
curl http://localhost:8000/health
```

#### 2. 資料庫監控

```sql
-- 查看當前連線數
SHOW PROCESSLIST;

-- 查看慢查詢
SELECT * FROM mysql.slow_log ORDER BY start_time DESC LIMIT 10;

-- 查看資料庫大小
SELECT 
    table_schema AS 'Database',
    ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'Size (MB)'
FROM information_schema.tables
WHERE table_schema = 'line_reservation'
GROUP BY table_schema;

-- 查看資料表大小
SELECT 
    table_name AS 'Table',
    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'Size (MB)'
FROM information_schema.TABLES
WHERE table_schema = 'line_reservation'
ORDER BY (data_length + index_length) DESC;
```

#### 3. 系統資源監控

```bash
# CPU 使用率
top -bn1 | grep "Cpu(s)"

# 記憶體使用率
free -h

# 硬碟使用率
df -h

# 網路連線數
netstat -an | grep :80 | wc -l

# 查看 Apache 狀態
systemctl status apache2

# 查看 MySQL 狀態
systemctl status mysql

# 查看 PHP-FPM 狀態
systemctl status php8.3-fpm
```

### 告警設定

#### 簡易郵件告警腳本

```bash
#!/bin/bash
# /usr/local/bin/line-reservation-monitor.sh

# 檢查服務狀態
if ! systemctl is-active --quiet apache2; then
    echo "Apache is down!" | mail -s "Alert: Apache Down" admin@example.com
fi

if ! systemctl is-active --quiet mysql; then
    echo "MySQL is down!" | mail -s "Alert: MySQL Down" admin@example.com
fi

# 檢查硬碟空間
DISK_USAGE=$(df / | tail -1 | awk '{print $5}' | sed 's/%//')
if [ $DISK_USAGE -gt 80 ]; then
    echo "Disk usage is ${DISK_USAGE}%" | mail -s "Alert: High Disk Usage" admin@example.com
fi

# 檢查應用程式錯誤
ERROR_COUNT=$(grep -c "ERROR" /var/www/line-reservation/backend/storage/logs/laravel.log)
if [ $ERROR_COUNT -gt 100 ]; then
    echo "Application has ${ERROR_COUNT} errors" | mail -s "Alert: High Error Rate" admin@example.com
fi
```

```bash
# 設定為可執行
chmod +x /usr/local/bin/line-reservation-monitor.sh

# 加入 crontab（每 5 分鐘執行一次）
crontab -e
*/5 * * * * /usr/local/bin/line-reservation-monitor.sh
```

## 定期維護任務

### 每日任務

```bash
#!/bin/bash
# daily-maintenance.sh

# 1. 清理快取
cd /var/www/line-reservation/backend
php artisan cache:clear
php artisan view:clear

# 2. 優化資料庫
mysql -u root -p -e "OPTIMIZE TABLE line_reservation.reservations;"

# 3. 檢查日誌大小
LOG_SIZE=$(du -sh backend/storage/logs | cut -f1)
echo "$(date): Log size: $LOG_SIZE" >> /var/log/line-reservation-maintenance.log
```

### 每週任務

```bash
#!/bin/bash
# weekly-maintenance.sh

# 1. 清理舊日誌
find backend/storage/logs -name "*.log" -mtime +7 -delete

# 2. 清理軟刪除資料（30天前）
php artisan tinker --execute="
Reservation::onlyTrashed()->where('deleted_at', '<', now()->subDays(30))->forceDelete();
Customer::onlyTrashed()->where('deleted_at', '<', now()->subDays(30))->forceDelete();
"

# 3. 資料庫備份
mysqldump -u backup_user -p line_reservation > /backup/weekly_$(date +%Y%m%d).sql
```

### 每月任務

```bash
#!/bin/bash
# monthly-maintenance.sh

# 1. 更新系統套件
sudo apt update
sudo apt upgrade -y

# 2. 清理 LINE 訊息日誌（90天前）
mysql -u root -p line_reservation -e "
DELETE FROM line_message_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
"

# 3. 清理活動日誌（180天前）
mysql -u root -p line_reservation -e "
DELETE FROM admin_activity_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 180 DAY);
"

# 4. 檢查 SSL 憑證到期日
sudo certbot certificates

# 5. 完整系統備份
tar -czf /backup/monthly_$(date +%Y%m%d).tar.gz /var/www/line-reservation
```

### 設定 Cron Jobs

```bash
# 編輯 crontab
crontab -e

# 加入以下任務
# 每天凌晨 2:00 執行每日維護
0 2 * * * /usr/local/bin/daily-maintenance.sh

# 每週日凌晨 3:00 執行每週維護
0 3 * * 0 /usr/local/bin/weekly-maintenance.sh

# 每月 1 號凌晨 4:00 執行每月維護
0 4 1 * * /usr/local/bin/monthly-maintenance.sh

# Laravel 排程器（每分鐘）
* * * * * cd /var/www/line-reservation/backend && php artisan schedule:run >> /dev/null 2>&1
```

##  備份策略

### 3-2-1 備份原則

- **3** 份備份副本
- **2** 種不同的儲存媒體
- **1** 份異地備份

### 資料庫備份

#### 全量備份

```bash
#!/bin/bash
# database-backup.sh

DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/backup/database"
DB_NAME="line_reservation"
DB_USER="backup_user"
DB_PASS="backup_password"

# 建立備份目錄
mkdir -p $BACKUP_DIR

# 執行備份
mysqldump -u $DB_USER -p$DB_PASS \
    --single-transaction \
    --quick \
    --lock-tables=false \
    $DB_NAME | gzip > $BACKUP_DIR/backup_$DATE.sql.gz

# 保留最近 30 天的備份
find $BACKUP_DIR -name "backup_*.sql.gz" -mtime +30 -delete

echo "Backup completed: backup_$DATE.sql.gz"
```

#### 增量備份（使用二進制日誌）

```bash
# 啟用二進制日誌
# /etc/mysql/mysql.conf.d/mysqld.cnf
[mysqld]
log_bin = /var/log/mysql/mysql-bin.log
expire_logs_days = 7
max_binlog_size = 100M

# 刷新二進制日誌
mysql -u root -p -e "FLUSH LOGS;"

# 備份二進制日誌
mysqlbinlog /var/log/mysql/mysql-bin.000001 > /backup/binlog_$(date +%Y%m%d).sql
```

### 應用程式備份

```bash
#!/bin/bash
# application-backup.sh

DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/backup/application"
APP_DIR="/var/www/line-reservation"

mkdir -p $BACKUP_DIR

# 備份應用程式（排除 node_modules 和 vendor）
tar -czf $BACKUP_DIR/app_$DATE.tar.gz \
    --exclude='node_modules' \
    --exclude='vendor' \
    --exclude='storage/logs/*.log' \
    $APP_DIR

# 保留最近 14 天的備份
find $BACKUP_DIR -name "app_*.tar.gz" -mtime +14 -delete

echo "Application backup completed: app_$DATE.tar.gz"
```

### 還原備份

```bash
# 還原資料庫
gunzip < /backup/database/backup_20251023_020000.sql.gz | mysql -u root -p line_reservation

# 還原應用程式
cd /var/www
sudo rm -rf line-reservation.bak
sudo mv line-reservation line-reservation.bak
sudo tar -xzf /backup/application/app_20251023_020000.tar.gz
sudo chown -R www-data:www-data line-reservation
```

## ❌ 錯誤處理

### 常見錯誤及解決方案

#### 1. 500 內部伺服器錯誤

```bash
# 檢查 Laravel 日誌
tail -f backend/storage/logs/laravel.log

# 檢查 Apache 錯誤日誌
tail -f /var/log/apache2/line-reservation_error.log

# 檢查權限
sudo chown -R www-data:www-data /var/www/line-reservation
sudo chmod -R 755 /var/www/line-reservation
sudo chmod -R 775 /var/www/line-reservation/backend/storage
```

#### 2. 資料庫連接失敗

```bash
# 測試資料庫連接
mysql -u line_user -p line_reservation -e "SELECT 1"

# 檢查 MySQL 狀態
systemctl status mysql

# 重啟 MySQL
sudo systemctl restart mysql

# 檢查 .env 配置
cat backend/.env | grep DB_
```

#### 3. LINE Webhook 失敗

```bash
# 檢查 Webhook URL 可訪問性
curl -X POST https://your-domain.com/api/line/webhook

# 檢查 LINE 簽名驗證
# 暫時停用簽名驗證進行測試（不建議在生產環境）
# routes/api.php
Route::post('/line/webhook', [LineWebhookController::class, 'handle']);
// ->middleware('verify.line.signature');

# 查看 LINE 相關日誌
grep "LINE" backend/storage/logs/laravel.log
```

#### 4. 前端 CORS 錯誤

```bash
# 檢查 CORS 配置
cat backend/config/cors.php

# 確認 FRONTEND_URL 設定正確
cat backend/.env | grep FRONTEND_URL

# 清除配置快取
php artisan config:clear
```

## 效能優化

### Laravel 優化

```bash
# 配置快取
php artisan config:cache

# 路由快取
php artisan route:cache

# 視圖快取
php artisan view:cache

# 優化 Composer autoload
composer dump-autoload --optimize

# 開啟 OPcache
# /etc/php/8.3/fpm/php.ini
opcache.enable=1
opcache.memory_consumption=128
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=10000
opcache.validate_timestamps=0  # 生產環境設為 0
```

### 資料庫優化

```sql
-- 分析表格
ANALYZE TABLE reservations, customers, services;

-- 優化表格
OPTIMIZE TABLE reservations, customers, services;

-- 查看慢查詢
SHOW VARIABLES LIKE 'slow_query_log';
SET GLOBAL slow_query_log = 'ON';
SET GLOBAL long_query_time = 2;

-- 檢查索引使用情況
EXPLAIN SELECT * FROM reservations 
WHERE reservation_date = '2025-10-25' AND status = 'confirmed';
```

### 前端優化

```bash
# 建立生產版本（自動優化）
cd frontend
npm run build

# 結果包含:
# - 代碼壓縮
# - Tree shaking
# - CSS 壓縮
# - 圖片優化
```

## 安全維護

### 安全檢查清單

```bash
# 1. 更新系統套件
sudo apt update && sudo apt upgrade -y

# 2. 更新 Composer 依賴
cd backend
composer update

# 3. 更新 npm 依賴
cd frontend
npm update

# 4. 檢查安全漏洞
npm audit
composer audit

# 5. 修復安全漏洞
npm audit fix
composer update --with-dependencies

# 6. 檢查檔案權限
find /var/www/line-reservation -type f -perm 0777
find /var/www/line-reservation -type d -perm 0777

# 7. 檢查 SSL 憑證
sudo certbot certificates

# 8. 更新防火牆規則
sudo ufw status

# 9. 檢查失敗的登入嘗試
grep "Failed" backend/storage/logs/laravel.log | wc -l

# 10. 檢查異常 IP
tail -1000 /var/log/apache2/line-reservation_access.log | \
awk '{print $1}' | sort | uniq -c | sort -rn | head -20
```

### SSL 憑證更新

```bash
# 測試憑證更新
sudo certbot renew --dry-run

# 手動更新憑證
sudo certbot renew

# 自動更新（cron job）
0 3 * * * certbot renew --quiet && systemctl reload apache2
```

---

**文件版本**: v1.0.0  
**最後更新**: 2025-10-23  
**維護者**: 傅盛祥 (Spencer Kuku)