#!/bin/bash

# 資料庫備份還原後的修復腳本
# 用於解決500錯誤和migration問題

echo "開始修復資料庫備份還原後的問題..."

cd "$(dirname "$0")/backend"

# 1. 清理所有緩存
echo "清理Laravel緩存..."
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# 2. 重新生成autoload
echo "重新生成autoload..."
composer dump-autoload

# 3. 檢查migration狀態
echo "檢查migration狀態..."
php artisan migrate:status

# 4. 重新快取配置（包含新的security logging channel）
echo "重新快取配置..."
php artisan config:cache

# 5. 確保storage目錄權限正確
echo "設定storage目錄權限..."
chmod -R 775 storage
chmod -R 775 bootstrap/cache

# 6. 創建必要的日誌目錄
echo "創建日誌目錄..."
mkdir -p storage/logs
touch storage/logs/laravel.log
touch storage/logs/security.log
touch storage/logs/linebot.log
touch storage/logs/reservations.log
touch storage/logs/api.log

# 7. 設定日誌文件權限
chmod 664 storage/logs/*.log

# 8. 測試Laravel是否正常運行
echo "測試Laravel..."
php artisan --version

echo "修復完成！"
echo ""
echo "如果仍有問題，請檢查："
echo "1. 資料庫連接設定"
echo "2. 環境變數配置 (.env)"
echo "3. web server配置"
echo "4. PHP錯誤日誌"
