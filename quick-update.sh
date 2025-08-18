#!/bin/bash

echo "🚀 LINE Reservation 快速更新工具..."

set -e
trap 'echo "❌ 更新失敗於第 $LINENO 行"; exit 1' ERR

# 檢查非 root 執行
if [[ $EUID -eq 0 ]]; then
    echo "❌ 請不要用 root 用戶執行，改用一般用戶！"
    exit 1
fi

PROJECT_DIR="/var/www/line-reservation"
USER_HOME=$(eval echo "~$USER")

# 檢查專案目錄是否存在
if [ ! -d "$PROJECT_DIR" ]; then
    echo "❌ 專案目錄不存在: $PROJECT_DIR"
    echo "請先執行完整部署腳本"
    exit 1
fi

# 確保目錄權限
echo "🔧 檢查並修正目錄權限..."
sudo chown -R $USER:$USER "$PROJECT_DIR"

cd "$PROJECT_DIR"

# 選單系統
show_menu() {
    echo ""
    echo "=================================="
    echo "🛠️  LINE Reservation 快速更新工具"
    echo "=================================="
    echo "1) 更新域名/IP 設定"
    echo "2) 切換 SSL 開關"
    echo "3) 重建前端 (npm run build)"
    echo "4) 清除後端快取"
    echo "5) 完整重建 (前端+後端快取)"
    echo "6) 更新代碼並重建"
    echo "7) 重啟 Apache 服務"
    echo "8) 檢查服務狀態"
    echo "9) 查看日誌"
    echo "10) 恢復 Git 並更新代碼"
    echo "0) 退出"
    echo "=================================="
}

# 讀取當前設定
read_current_settings() {
    CURRENT_DOMAIN=""
    CURRENT_SSL=""
    CURRENT_PROTOCOL=""
    
    if [ -f "backend/.env" ]; then
        CURRENT_DOMAIN=$(grep "^APP_URL=" backend/.env | cut -d'=' -f2 | sed 's|https\?://||')
        if grep -q "^APP_URL=https://" backend/.env; then
            CURRENT_SSL="true"
            CURRENT_PROTOCOL="https"
        else
            CURRENT_SSL="false"
            CURRENT_PROTOCOL="http"
        fi
    fi
    
    echo "📋 當前設定:"
    echo "  域名/IP: $CURRENT_DOMAIN"
    echo "  SSL: $CURRENT_SSL"
    echo "  協議: $CURRENT_PROTOCOL"
}

# 更新環境變數
update_env_var() {
    local key="$1"
    local val="$2"
    local file="$3"
    if grep -q "^${key}=" "$file"; then
        sed -i "s|^${key}=.*|${key}=${val}|" "$file"
    else
        echo "${key}=${val}" >> "$file"
    fi
}

# 更新域名/IP
update_domain() {
    echo "🌐 更新域名/IP 設定..."
    read_current_settings
    
    echo ""
    echo "當前域名/IP: $CURRENT_DOMAIN"
    read -p "請輸入新的域名或IP (按 Enter 保持不變): " NEW_DOMAIN
    
    if [ -z "$NEW_DOMAIN" ]; then
        echo "✅ 保持原設定: $CURRENT_DOMAIN"
        return
    fi
    
    echo "🔄 更新後端設定..."
    cd backend
    update_env_var "APP_URL" "${CURRENT_PROTOCOL}://$NEW_DOMAIN" ".env"
    update_env_var "FRONTEND_URL" "${CURRENT_PROTOCOL}://$NEW_DOMAIN" ".env"
    update_env_var "SANCTUM_STATEFUL_DOMAINS" "$NEW_DOMAIN" ".env"
    update_env_var "SESSION_DOMAIN" "$NEW_DOMAIN" ".env"
    
    echo "🔄 更新前端設定..."
    cd ../frontend
    update_env_var "VITE_API_BASE_URL" "${CURRENT_PROTOCOL}://$NEW_DOMAIN/api" ".env"
    update_env_var "VITE_APP_BASE_URL" "${CURRENT_PROTOCOL}://$NEW_DOMAIN" ".env"
    update_env_var "VITE_APP_URL" "${CURRENT_PROTOCOL}://$NEW_DOMAIN" ".env"
    update_env_var "VITE_BACKEND_URL" "${CURRENT_PROTOCOL}://$NEW_DOMAIN/api" ".env"
    
    cd ..
    echo "✅ 域名/IP 已更新為: $NEW_DOMAIN"
}

# 切換 SSL
toggle_ssl() {
    echo "🔒 切換 SSL 設定..."
    read_current_settings
    
    echo ""
    echo "當前 SSL 狀態: $CURRENT_SSL"
    
    if [ "$CURRENT_SSL" = "true" ]; then
        read -p "是否要停用 SSL? (y/N): " DISABLE_SSL
        if [[ "$DISABLE_SSL" =~ ^[Yy]$ ]]; then
            NEW_PROTOCOL="http"
            echo "🔄 停用 SSL..."
        else
            echo "✅ 保持 SSL 啟用"
            return
        fi
    else
        # 檢查是否為 IP 地址
        if [[ $CURRENT_DOMAIN =~ ^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
            echo "⚠️ 警告：IP 地址無法使用 SSL 憑證"
            return
        fi
        
        read -p "是否要啟用 SSL? (y/N): " ENABLE_SSL
        if [[ "$ENABLE_SSL" =~ ^[Yy]$ ]]; then
            NEW_PROTOCOL="https"
            echo "🔄 啟用 SSL..."
        else
            echo "✅ 保持 SSL 停用"
            return
        fi
    fi
    
    # 更新設定檔
    echo "🔄 更新後端設定..."
    cd backend
    update_env_var "APP_URL" "${NEW_PROTOCOL}://$CURRENT_DOMAIN" ".env"
    update_env_var "FRONTEND_URL" "${NEW_PROTOCOL}://$CURRENT_DOMAIN" ".env"
    
    echo "🔄 更新前端設定..."
    cd ../frontend
    update_env_var "VITE_API_BASE_URL" "${NEW_PROTOCOL}://$CURRENT_DOMAIN/api" ".env"
    update_env_var "VITE_APP_BASE_URL" "${NEW_PROTOCOL}://$CURRENT_DOMAIN" ".env"
    update_env_var "VITE_APP_URL" "${NEW_PROTOCOL}://$CURRENT_DOMAIN" ".env"
    update_env_var "VITE_BACKEND_URL" "${NEW_PROTOCOL}://$CURRENT_DOMAIN/api" ".env"
    
    cd ..
    echo "✅ SSL 設定已更新為: $NEW_PROTOCOL"
    
    if [ "$NEW_PROTOCOL" = "https" ]; then
        echo "⚠️ 注意：您需要手動設定 SSL 憑證和更新 Apache 配置"
        echo "建議執行: sudo certbot --apache -d $CURRENT_DOMAIN"
    fi
}

# 重建前端
rebuild_frontend() {
    echo "🏗️ 重建前端..."
    cd frontend
    
    # 確保權限正確
    sudo chown -R $USER:$USER .
    
    echo "🧹 清理舊檔案..."
    if [ -d "dist" ]; then
        rm -rf dist
    fi
    if [ -d "node_modules" ]; then
        echo "清理 node_modules..."
        rm -rf node_modules package-lock.json
    fi
    
    echo "📦 安裝依賴..."
    npm install
    
    echo "🏗️ 建立生產版本..."
    npm run build
    
    echo "🔧 設定權限..."
    sudo chown -R www-data:www-data dist
    
    cd ..
    echo "✅ 前端重建完成"
}

# 清除後端快取
clear_backend_cache() {
    echo "🧹 清除後端快取..."
    cd backend
    
    # 確保權限正確
    sudo chown -R $USER:$USER .
    
    echo "清除配置快取..."
    php artisan config:clear
    php artisan config:cache
    
    echo "清除路由快取..."
    php artisan route:clear
    php artisan route:cache
    
    echo "清除視圖快取..."
    php artisan view:clear
    
    echo "清除應用快取..."
    php artisan cache:clear
    
    echo "🔧 設定權限..."
    sudo chown -R www-data:www-data storage bootstrap/cache
    sudo chmod -R 775 storage bootstrap/cache
    
    cd ..
    echo "✅ 後端快取清除完成"
}

# 完整重建
full_rebuild() {
    echo "🔄 執行完整重建..."
    clear_backend_cache
    rebuild_frontend
    
    echo "🔧 最終權限設定..."
    sudo chown -R www-data:www-data "$PROJECT_DIR"
    find "$PROJECT_DIR" -type d -exec sudo chmod 755 {} \;
    find "$PROJECT_DIR" -type f -exec sudo chmod 644 {} \;
    sudo chmod -R 775 "$PROJECT_DIR/backend/storage" "$PROJECT_DIR/backend/bootstrap/cache"
    
    echo "✅ 完整重建完成"
}

# 恢復 Git 並更新代碼
restore_git_and_update() {
    echo "� 恢復 Git 並更新代碼..."
    
    # 確保權限
    sudo chown -R $USER:$USER "$PROJECT_DIR"
    
    # 檢查是否已有 Git
    if [ -d ".git" ]; then
        echo "✅ Git 目錄已存在"
        read -p "是否要重新初始化 Git? (y/N): " REINIT_GIT
        if [[ "$REINIT_GIT" =~ ^[Yy]$ ]]; then
            echo "�️ 移除現有 Git..."
            rm -rf .git
        else
            echo "�📥 使用現有 Git 拉取更新..."
            git pull origin main || echo "⚠️ Git 更新可能失敗，請檢查"
            cleanup_git_and_rebuild
            return
        fi
    fi
    
    echo "🔧 設定 Git..."
    git config --global --add safe.directory "$PROJECT_DIR"
    
    echo "� 初始化 Git 倉庫..."
    git init
    
    echo "🔗 添加遠端倉庫..."
    read -p "請輸入 Git 倉庫 URL (預設: https://github.com/spencerkuku/line-reservation.git): " REPO_URL
    if [ -z "$REPO_URL" ]; then
        REPO_URL="https://github.com/spencerkuku/line-reservation.git"
    fi
    
    git remote add origin "$REPO_URL"
    
    echo "📥 拉取最新代碼..."
    git fetch origin
    
    echo "🌿 選擇分支..."
    read -p "請輸入要使用的分支名稱 (預設: main): " BRANCH_NAME
    if [ -z "$BRANCH_NAME" ]; then
        BRANCH_NAME="main"
    fi
    
    echo "🔄 切換到分支: $BRANCH_NAME"
    git checkout -b "$BRANCH_NAME" "origin/$BRANCH_NAME" || git checkout "$BRANCH_NAME" || {
        echo "❌ 無法切換到分支 $BRANCH_NAME"
        echo "📋 可用的遠端分支:"
        git branch -r
        return 1
    }
    
    echo "�🔄 執行完整重建..."
    full_rebuild
    
    echo "🗑️ 清理 Git 資料..."
    cleanup_git_files
    
    echo "✅ Git 恢復和代碼更新完成"
}

# 清理 Git 檔案
cleanup_git_files() {
    echo "🗑️ 清理 Git 相關檔案..."
    
    if [ -d ".git" ]; then
        rm -rf .git
        echo "✅ .git 目錄已刪除"
    fi
    
    if [ -f ".gitignore" ]; then
        rm -f .gitignore
        echo "✅ .gitignore 檔案已刪除"
    fi
    
    if [ -f ".gitattributes" ]; then
        rm -f .gitattributes
        echo "✅ .gitattributes 檔案已刪除"
    fi
    
    # 清理可能的 Git 相關檔案
    find . -name ".git*" -type f -delete 2>/dev/null || true
    
    echo "✅ Git 相關檔案清理完成"
}

# 更新代碼並重建 (原版，不使用 Git)
update_and_rebuild() {
    echo "📥 更新代碼並重建..."
    
    if [ ! -d ".git" ]; then
        echo "❌ 沒有 Git 倉庫，請選擇選項 10 來恢復 Git"
        return
    fi
    
    # 確保權限
    sudo chown -R $USER:$USER "$PROJECT_DIR"
    
    echo "📥 拉取最新代碼..."
    git pull origin main || echo "⚠️ Git 更新可能失敗，請檢查"
    
    echo "🔄 執行完整重建和清理..."
    cleanup_git_and_rebuild
    
    echo "✅ 代碼更新並重建完成"
}
cleanup_git_and_rebuild() {
    echo "🔄 執行完整重建..."
    clear_backend_cache
    rebuild_frontend
    
    echo "🔧 最終權限設定..."
    sudo chown -R www-data:www-data "$PROJECT_DIR"
    find "$PROJECT_DIR" -type d -exec sudo chmod 755 {} \;
    find "$PROJECT_DIR" -type f -exec sudo chmod 644 {} \;
    sudo chmod -R 775 "$PROJECT_DIR/backend/storage" "$PROJECT_DIR/backend/bootstrap/cache"
    
    echo "🗑️ 清理 Git 資料..."
    cleanup_git_files
    
    echo "✅ 完整重建和清理完成"
}

# 重啟服務
restart_services() {
    echo "🔄 重啟 Apache 服務..."
    sudo systemctl reload apache2
    
    echo "🔄 重啟 PHP-FPM..."
    sudo systemctl restart php8.3-fpm || sudo systemctl restart php-fpm || echo "⚠️ PHP-FPM 重啟可能失敗"
    
    echo "✅ 服務重啟完成"
}

# 檢查狀態
check_status() {
    echo "📊 檢查服務狀態..."
    
    echo ""
    echo "🌐 Apache 狀態:"
    sudo systemctl status apache2 --no-pager -l
    
    echo ""
    echo "🐘 PHP-FPM 狀態:"
    sudo systemctl status php8.3-fpm --no-pager -l || sudo systemctl status php-fpm --no-pager -l || echo "無法獲取 PHP-FPM 狀態"
    
    echo ""
    echo "🗄️ MySQL 狀態:"
    sudo systemctl status mysql --no-pager -l
    
    echo ""
    echo "💾 磁碟使用量:"
    df -h /var/www
    
    echo ""
    echo "📁 專案檔案權限:"
    ls -la "$PROJECT_DIR" | head -10
}

# 查看日誌
view_logs() {
    echo "📋 查看日誌..."
    echo ""
    echo "選擇要查看的日誌:"
    echo "1) Apache 錯誤日誌"
    echo "2) Apache 訪問日誌"
    echo "3) Laravel 日誌"
    echo "4) 系統日誌"
    echo ""
    read -p "請選擇 (1-4): " LOG_CHOICE
    
    case $LOG_CHOICE in
        1)
            echo "📋 Apache 錯誤日誌 (最後50行):"
            sudo tail -50 /var/log/apache2/line-reservation_error.log || sudo tail -50 /var/log/apache2/error.log
            ;;
        2)
            echo "📋 Apache 訪問日誌 (最後30行):"
            sudo tail -30 /var/log/apache2/line-reservation_access.log || sudo tail -30 /var/log/apache2/access.log
            ;;
        3)
            echo "📋 Laravel 日誌 (最後30行):"
            sudo tail -30 "$PROJECT_DIR/backend/storage/logs/laravel.log" 2>/dev/null || echo "Laravel 日誌檔案不存在"
            ;;
        4)
            echo "📋 系統日誌 (最後20行):"
            sudo journalctl -u apache2 -n 20 --no-pager
            ;;
        *)
            echo "❌ 無效選擇"
            ;;
    esac
}

# 主循環
main() {
    while true; do
        show_menu
        read_current_settings
        echo ""
        read -p "請選擇操作 (0-10): " CHOICE
        
        case $CHOICE in
            1)
                update_domain
                ;;
            2)
                toggle_ssl
                ;;
            3)
                rebuild_frontend
                ;;
            4)
                clear_backend_cache
                ;;
            5)
                full_rebuild
                ;;
            6)
                update_and_rebuild
                ;;
            7)
                restart_services
                ;;
            8)
                check_status
                ;;
            9)
                view_logs
                ;;
            10)
                restore_git_and_update
                ;;
            0)
                echo "👋 再見！"
                exit 0
                ;;
            *)
                echo "❌ 無效選擇，請重試"
                ;;
        esac
        
        echo ""
        read -p "按 Enter 繼續..."
    done
}

# 執行主程序
main
