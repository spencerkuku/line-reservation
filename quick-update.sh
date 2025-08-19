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

# 安全權限設置函數
set_secure_permissions() {
    echo "🔧 設置安全權限..."
    
    # 設置基本擁有者權限
    sudo chown -R www-data:www-data "$PROJECT_DIR"
    
    # 設置目錄權限 (755 - 僅擁有者可寫入)
    find "$PROJECT_DIR" -type d -exec sudo chmod 755 {} \;
    
    # 設置一般檔案權限 (644 - 僅擁有者可寫入，無執行權限)
    find "$PROJECT_DIR" -type f -exec sudo chmod 644 {} \;
    
    # 設置 Laravel 必要的寫入權限目錄 (限制為 755，不使用 775)
    sudo chmod -R 755 "$PROJECT_DIR/backend/storage" "$PROJECT_DIR/backend/bootstrap/cache"
    
    # 嚴格保護敏感配置檔案 (600 - 僅擁有者可讀寫)
    sudo chmod 600 "$PROJECT_DIR/frontend/.env" "$PROJECT_DIR/backend/.env" 2>/dev/null || true
    sudo chmod 600 "$PROJECT_DIR/frontend/.env.example" "$PROJECT_DIR/backend/.env.example" 2>/dev/null || true
    
    # 確保 artisan 有執行權限 (Laravel CLI 需要)
    sudo chmod 755 "$PROJECT_DIR/backend/artisan"
    
    # 移除常見檔案類型的不必要執行權限
    find "$PROJECT_DIR" -type f \( -name "*.php" -o -name "*.js" -o -name "*.vue" -o -name "*.css" -o -name "*.html" -o -name "*.json" -o -name "*.md" -o -name "*.txt" -o -name "*.log" \) -exec sudo chmod 644 {} \;
    
    echo "✅ 安全權限設置完成"
}

# 創建備份函數
create_backup() {
    local backup_type="$1"
    local timestamp=$(date +"%Y%m%d_%H%M%S")
    local backup_dir="$USER_HOME/line-reservation-backups/project-backups"
    local backup_name="${backup_type}_backup_${timestamp}"
    local backup_path="$backup_dir/$backup_name"
    
    echo "💾 創建 $backup_type 備份..."
    
    # 確保備份目錄存在
    mkdir -p "$backup_dir"
    chmod 700 "$backup_dir"  # 僅擁有者可存取
    
    case $backup_type in
        "env")
            echo "📄 備份環境配置檔案..."
            mkdir -p "$backup_path"
            
            # 備份前端 .env
            if [ -f "$PROJECT_DIR/frontend/.env" ]; then
                cp "$PROJECT_DIR/frontend/.env" "$backup_path/frontend.env"
                echo "✅ 前端 .env 已備份"
            fi
            
            # 備份後端 .env
            if [ -f "$PROJECT_DIR/backend/.env" ]; then
                cp "$PROJECT_DIR/backend/.env" "$backup_path/backend.env"
                echo "✅ 後端 .env 已備份"
            fi
            
            # 備份資料庫憑證
            if [ -f "$PROJECT_DIR/db_credentials.txt" ]; then
                cp "$PROJECT_DIR/db_credentials.txt" "$backup_path/db_credentials.txt"
                echo "✅ 資料庫憑證已備份"
            fi
            
            chmod 600 "$backup_path"/* 2>/dev/null || true
            ;;
            
        "project")
            echo "📁 備份整個專案..."
            
            # 排除不必要的目錄和檔案
            tar --exclude="$PROJECT_DIR/backend/vendor" \
                --exclude="$PROJECT_DIR/backend/storage/logs/*" \
                --exclude="$PROJECT_DIR/backend/storage/framework/cache/*" \
                --exclude="$PROJECT_DIR/backend/storage/framework/sessions/*" \
                --exclude="$PROJECT_DIR/backend/storage/framework/views/*" \
                --exclude="$PROJECT_DIR/frontend/node_modules" \
                --exclude="$PROJECT_DIR/frontend/dist" \
                --exclude="$PROJECT_DIR/.git" \
                -czf "$backup_path.tar.gz" \
                -C "$(dirname "$PROJECT_DIR")" \
                "$(basename "$PROJECT_DIR")" 2>/dev/null
                
            echo "✅ 專案備份已保存到: $backup_path.tar.gz"
            ;;
    esac
    
    chmod 600 "$backup_path"* 2>/dev/null || true
    echo "📍 備份位置: $backup_path"
    echo "📅 備份時間: $(date)"
    
    # 清理超過 30 天的舊備份
    find "$backup_dir" -name "*_backup_*" -mtime +30 -delete 2>/dev/null || true
    
    return 0
}

# 恢復備份函數
restore_backup() {
    local backup_dir="$USER_HOME/line-reservation-backups/project-backups"
    
    if [ ! -d "$backup_dir" ]; then
        echo "❌ 找不到備份目錄: $backup_dir"
        return 1
    fi
    
    echo "📋 可用的備份:"
    local backups=($(find "$backup_dir" -name "*_backup_*" -type f -o -type d | sort -r))
    
    if [ ${#backups[@]} -eq 0 ]; then
        echo "❌ 找不到任何備份檔案"
        return 1
    fi
    
    local i=1
    for backup in "${backups[@]}"; do
        local backup_name=$(basename "$backup")
        local backup_date=$(echo "$backup_name" | grep -o '[0-9]\{8\}_[0-9]\{6\}' || echo "未知")
        echo "$i) $backup_name (建立於: $backup_date)"
        ((i++))
    done
    
    echo ""
    read -p "請選擇要恢復的備份 (1-${#backups[@]}): " backup_choice
    
    if [[ ! "$backup_choice" =~ ^[0-9]+$ ]] || [ "$backup_choice" -lt 1 ] || [ "$backup_choice" -gt ${#backups[@]} ]; then
        echo "❌ 無效的選擇"
        return 1
    fi
    
    local selected_backup="${backups[$((backup_choice-1))]}"
    echo "📥 恢復備份: $(basename "$selected_backup")"
    
    if [[ "$selected_backup" == *"env_backup"* ]]; then
        # 恢復環境檔案
        if [ -f "$selected_backup/frontend.env" ]; then
            cp "$selected_backup/frontend.env" "$PROJECT_DIR/frontend/.env"
            chmod 600 "$PROJECT_DIR/frontend/.env"
            echo "✅ 前端 .env 已恢復"
        fi
        
        if [ -f "$selected_backup/backend.env" ]; then
            cp "$selected_backup/backend.env" "$PROJECT_DIR/backend/.env"
            chmod 600 "$PROJECT_DIR/backend/.env"
            echo "✅ 後端 .env 已恢復"
        fi
        
        if [ -f "$selected_backup/db_credentials.txt" ]; then
            cp "$selected_backup/db_credentials.txt" "$PROJECT_DIR/db_credentials.txt"
            chmod 600 "$PROJECT_DIR/db_credentials.txt"
            echo "✅ 資料庫憑證已恢復"
        fi
    elif [[ "$selected_backup" == *".tar.gz" ]]; then
        # 恢復整個專案
        echo "⚠️ 警告：這將覆蓋整個專案目錄！"
        read -p "確定要繼續嗎? (y/N): " confirm
        if [[ "$confirm" =~ ^[Yy]$ ]]; then
            # 先備份當前狀態
            create_backup "project"
            
            # 解壓縮備份
            tar -xzf "$selected_backup" -C "$(dirname "$PROJECT_DIR")" 2>/dev/null
            echo "✅ 專案已從備份恢復"
            
            # 重新設置權限
            set_secure_permissions
        else
            echo "❌ 取消恢復操作"
            return 1
        fi
    fi
    
    echo "✅ 備份恢復完成"
}

# 智能 Git 清理函數
smart_git_cleanup() {
    echo "🗑️ 智能 Git 清理..."
    
    # 檢查是否為多人開發環境
    local is_multi_dev=false
    
    # 檢查 .gitignore 是否有重要內容
    if [ -f ".gitignore" ]; then
        local gitignore_size=$(wc -l < .gitignore)
        if [ "$gitignore_size" -gt 10 ]; then
            echo "📋 發現詳細的 .gitignore 檔案 ($gitignore_size 行)"
            is_multi_dev=true
        fi
    fi
    
    # 檢查是否有多個遠端分支
    if [ -d ".git" ]; then
        local remote_branches=$(git branch -r 2>/dev/null | wc -l)
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
                rm -rf .git
                echo "✅ .git 目錄已刪除（保留配置檔案）"
            fi
            
            echo "ℹ️ 已保留 .gitignore 和 .gitattributes 供未來使用"
            return 0
        fi
    fi
    
    # 完全清理 Git 相關檔案
    echo "🧹 完全清理 Git 相關檔案..."
    
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
    
    # 清理其他 Git 相關檔案
    find . -name ".git*" -type f -delete 2>/dev/null || true
    
    echo "✅ Git 完全清理完成"
}

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
    echo "11) 備份環境配置檔案"
    echo "12) 備份整個專案"
    echo "13) 恢復備份"
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
    echo "💾 自動備份環境配置..."
    create_backup "env"
    
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
    echo "� 自動備份環境配置..."
    create_backup "env"
    
    echo "�🔒 切換 SSL 設定..."
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
    # 設置基本擁有者權限
    sudo chown -R www-data:www-data dist
    
    # 移除不必要的執行權限，僅保留讀取權限
    find dist -type f -exec sudo chmod 644 {} \;
    find dist -type d -exec sudo chmod 755 {} \;
    
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
    # 設置基本擁有者權限
    sudo chown -R www-data:www-data storage bootstrap/cache
    
    # 設置寫入權限但限制為 755，不使用 775
    sudo chmod -R 755 storage bootstrap/cache
    
    cd ..
    echo "✅ 後端快取清除完成"
}

# 完整重建
full_rebuild() {
    echo "🔄 執行完整重建..."
    clear_backend_cache
    rebuild_frontend
    
    echo "🔧 最終權限設定..."
    set_secure_permissions
    
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
            sudo rm -rf .git
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
    smart_git_cleanup
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
    set_secure_permissions
    
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
        read -p "請選擇操作 (0-13): " CHOICE
        
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
            11)
                create_backup "env"
                ;;
            12)
                create_backup "project"
                ;;
            13)
                restore_backup
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
