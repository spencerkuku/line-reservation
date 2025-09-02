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

# 安全讀取 .env 檔案的輔助函數
safe_read_env() {
    local env_file="$1"
    local key="$2"
    local default_value="${3:-}"
    
    if [ ! -f "$env_file" ]; then
        echo "$default_value"
        return 1
    fi
    
    # 臨時調整權限以便讀取
    sudo chmod 644 "$env_file" 2>/dev/null || true
    
    # 讀取值
    local value=$(grep "^${key}=" "$env_file" 2>/dev/null | cut -d'=' -f2- | sed 's/^["'\'']*//;s/["'\'']*$//' || echo "$default_value")
    
    # 恢復安全權限
    sudo chmod 600 "$env_file" 2>/dev/null || true
    
    echo "$value"
}

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
    
    # 保護敏感配置檔案 (600 - 僅擁有者可讀寫)
    sudo chmod 600 "$PROJECT_DIR/frontend/.env" "$PROJECT_DIR/backend/.env" 2>/dev/null || true
    sudo chmod 600 "$PROJECT_DIR/frontend/.env.example" "$PROJECT_DIR/backend/.env.example" 2>/dev/null || true
    
    # 確保部署用戶可以讀取配置檔案（將 spencerku 加入 www-data 群組）
    sudo usermod -a -G www-data "$USER" 2>/dev/null || true
    
    # 強制應用群組權限變更（需要重新登入才會生效，所以用 newgrp 臨時生效）
    # 但為了腳本能立即運作，我們確保檔案的群組擁有者正確
    sudo chgrp www-data "$PROJECT_DIR/frontend/.env" "$PROJECT_DIR/backend/.env" 2>/dev/null || true
    
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
            echo "📄 【環境備份】備份系統環境配置檔案..."
            mkdir -p "$backup_path"
            
            # 臨時調整權限以便備份
            echo "🔧 臨時調整權限以進行備份..."
            
            # 備份前端 .env
            if [ -f "$PROJECT_DIR/frontend/.env" ]; then
                sudo chmod 644 "$PROJECT_DIR/frontend/.env" 2>/dev/null || true
                sudo cp "$PROJECT_DIR/frontend/.env" "$backup_path/frontend.env"
                sudo chmod 600 "$PROJECT_DIR/frontend/.env" 2>/dev/null || true
                echo "✅ 前端 .env 已備份"
            fi
            
            # 備份後端 .env
            if [ -f "$PROJECT_DIR/backend/.env" ]; then
                sudo chmod 644 "$PROJECT_DIR/backend/.env" 2>/dev/null || true
                sudo cp "$PROJECT_DIR/backend/.env" "$backup_path/backend.env"
                sudo chmod 600 "$PROJECT_DIR/backend/.env" 2>/dev/null || true
                echo "✅ 後端 .env 已備份"
            fi
            
            # 備份資料庫憑證
            if [ -f "$PROJECT_DIR/db_credentials.txt" ]; then
                sudo chmod 644 "$PROJECT_DIR/db_credentials.txt" 2>/dev/null || true
                sudo cp "$PROJECT_DIR/db_credentials.txt" "$backup_path/db_credentials.txt"
                sudo chmod 600 "$PROJECT_DIR/db_credentials.txt" 2>/dev/null || true
                echo "✅ 資料庫憑證已備份"
            fi
            
            chmod 600 "$backup_path"/* 2>/dev/null || true
            ;;
            
        "project")
            echo "📁 【專案備份】備份專案檔案（排除資料庫）..."
            
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
            
        "full")
            echo "🗄️ 【完整備份】執行系統完整備份（專案+資料庫）..."
            
            # 1. 先備份專案檔案
            echo "🗄️ 完整備份（專案 + 資料庫）..."
            
            # 1. 先備份專案檔案
            echo "📁 備份專案檔案..."
            tar --exclude="$PROJECT_DIR/backend/vendor" \
                --exclude="$PROJECT_DIR/backend/storage/logs/*" \
                --exclude="$PROJECT_DIR/backend/storage/framework/cache/*" \
                --exclude="$PROJECT_DIR/backend/storage/framework/sessions/*" \
                --exclude="$PROJECT_DIR/backend/storage/framework/views/*" \
                --exclude="$PROJECT_DIR/frontend/node_modules" \
                --exclude="$PROJECT_DIR/frontend/dist" \
                --exclude="$PROJECT_DIR/.git" \
                -czf "$backup_path-project.tar.gz" \
                -C "$(dirname "$PROJECT_DIR")" \
                "$(basename "$PROJECT_DIR")" 2>/dev/null
            
            # 2. 執行資料庫備份（使用部署腳本的資料庫備份功能）
            echo "🗄️ 執行資料庫備份..."
            local db_backup_script="$USER_HOME/line-reservation-backups/scripts/database_backup.sh"
            if [ -f "$db_backup_script" ]; then
                if "$db_backup_script"; then
                    echo "✅ 資料庫備份完成"
                else
                    echo "⚠️ 資料庫備份失敗"
                fi
            else
                echo "⚠️ 找不到資料庫備份腳本: $db_backup_script"
                echo "請先執行完整部署腳本以設置資料庫備份系統"
            fi
            
            echo "✅ 完整備份完成"
            echo "📁 專案檔案: $backup_path-project.tar.gz"
            ;;
            
        "db")
            echo "🗄️ 【資料庫備份】執行資料庫專項備份..."
            local db_backup_script="$USER_HOME/line-reservation-backups/scripts/database_backup.sh"
            if [ -f "$db_backup_script" ]; then
                if "$db_backup_script"; then
                    echo "✅ 資料庫備份完成"
                    # 回傳資料庫備份路徑
                    local db_backup_dir="$USER_HOME/line-reservation-backups/database"
                    echo "📍 資料庫備份位置: $db_backup_dir"
                else
                    echo "❌ 資料庫備份失敗"
                fi
            else
                echo "❌ 找不到資料庫備份腳本: $db_backup_script"
                echo "請先執行完整部署腳本以設置資料庫備份系統"
            fi
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
    local db_backup_dir="$USER_HOME/line-reservation-backups/database"
    
    echo "📋 【備份恢復控制台】選擇備份類型:"
    echo "1) 環境配置備份"
    echo "2) 專案檔案備份"
    echo "3) 資料庫備份"
    echo "4) 查看所有備份狀態"
    echo ""
    read -p "請選擇 (1-4): " backup_type_choice
    
    case $backup_type_choice in
        1)
            restore_env_backup
            ;;
        2)
            restore_project_backup
            ;;
        3)
            restore_database_backup
            ;;
        4)
            show_backup_status
            ;;
        *)
            echo "❌ 無效的選擇"
            return 1
            ;;
    esac
}

# 恢復環境配置備份
restore_env_backup() {
    local backup_dir="$USER_HOME/line-reservation-backups/project-backups"
    
    if [ ! -d "$backup_dir" ]; then
        echo "❌ 找不到備份目錄: $backup_dir"
        return 1
    fi
    
    echo "📋 可用的環境配置備份:"
    local env_backups=($(find "$backup_dir" -name "env_backup_*" -type d | sort -r))
    
    if [ ${#env_backups[@]} -eq 0 ]; then
        echo "❌ 找不到環境配置備份"
        return 1
    fi
    
    local i=1
    for backup in "${env_backups[@]}"; do
        local backup_name=$(basename "$backup")
        local backup_date=$(echo "$backup_name" | grep -o '[0-9]\{8\}_[0-9]\{6\}' || echo "未知")
        echo "$i) $backup_name (建立於: $backup_date)"
        ((i++))
    done
    
    echo ""
    read -p "請選擇要恢復的備份 (1-${#env_backups[@]}): " backup_choice
    
    if [[ ! "$backup_choice" =~ ^[0-9]+$ ]] || [ "$backup_choice" -lt 1 ] || [ "$backup_choice" -gt ${#env_backups[@]} ]; then
        echo "❌ 無效的選擇"
        return 1
    fi
    
    local selected_backup="${env_backups[$((backup_choice-1))]}"
    echo "📥 恢復環境備份: $(basename "$selected_backup")"
    
    # 恢復環境檔案
    if [ -f "$selected_backup/frontend.env" ]; then
        cp "$selected_backup/frontend.env" "$PROJECT_DIR/frontend/.env"
        sudo chown www-data:www-data "$PROJECT_DIR/frontend/.env"
        chmod 600 "$PROJECT_DIR/frontend/.env"
        echo "✅ 前端 .env 已恢復"
    fi
    
    if [ -f "$selected_backup/backend.env" ]; then
        cp "$selected_backup/backend.env" "$PROJECT_DIR/backend/.env"
        sudo chown www-data:www-data "$PROJECT_DIR/backend/.env"
        chmod 600 "$PROJECT_DIR/backend/.env"
        echo "✅ 後端 .env 已恢復"
    fi
    
    if [ -f "$selected_backup/db_credentials.txt" ]; then
        cp "$selected_backup/db_credentials.txt" "$PROJECT_DIR/db_credentials.txt"
        sudo chown www-data:www-data "$PROJECT_DIR/db_credentials.txt"
        chmod 600 "$PROJECT_DIR/db_credentials.txt"
        echo "✅ 資料庫憑證已恢復"
    fi
    
    echo "✅ 環境配置恢復完成"
}

# 恢復專案備份
restore_project_backup() {
    local backup_dir="$USER_HOME/line-reservation-backups/project-backups"
    
    if [ ! -d "$backup_dir" ]; then
        echo "❌ 找不到備份目錄: $backup_dir"
        return 1
    fi
    
    echo "📋 可用的專案備份:"
    local project_backups=($(find "$backup_dir" -name "project_backup_*.tar.gz" -o -name "*-project.tar.gz" | sort -r))
    
    if [ ${#project_backups[@]} -eq 0 ]; then
        echo "❌ 找不到專案備份檔案"
        return 1
    fi
    
    local i=1
    for backup in "${project_backups[@]}"; do
        local backup_name=$(basename "$backup")
        local backup_date=$(echo "$backup_name" | grep -o '[0-9]\{8\}_[0-9]\{6\}' || echo "未知")
        local backup_size=$(du -h "$backup" | cut -f1)
        echo "$i) $backup_name (建立於: $backup_date, 大小: $backup_size)"
        ((i++))
    done
    
    echo ""
    read -p "請選擇要恢復的備份 (1-${#project_backups[@]}): " backup_choice
    
    if [[ ! "$backup_choice" =~ ^[0-9]+$ ]] || [ "$backup_choice" -lt 1 ] || [ "$backup_choice" -gt ${#project_backups[@]} ]; then
        echo "❌ 無效的選擇"
        return 1
    fi
    
    local selected_backup="${project_backups[$((backup_choice-1))]}"
    echo "📥 恢復專案備份: $(basename "$selected_backup")"
    
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
    
    echo "✅ 專案恢復完成"
}

# 恢復資料庫備份
restore_database_backup() {
    local db_backup_dir="$USER_HOME/line-reservation-backups/database"
    
    if [ ! -d "$db_backup_dir" ]; then
        echo "❌ 找不到資料庫備份目錄: $db_backup_dir"
        echo "請先執行完整部署腳本以設置資料庫備份系統"
        return 1
    fi
    
    echo "📋 可用的資料庫備份:"
    local db_backups=($(find "$db_backup_dir" -name "line_reservation_backup_*.sql*" | sort -r))
    
    if [ ${#db_backups[@]} -eq 0 ]; then
        echo "❌ 找不到資料庫備份檔案"
        return 1
    fi
    
    local i=1
    for backup in "${db_backups[@]}"; do
        local backup_name=$(basename "$backup")
        local backup_date=$(echo "$backup_name" | grep -o '[0-9]\{8\}_[0-9]\{6\}' || echo "未知")
        local backup_size=$(du -h "$backup" | cut -f1)
        echo "$i) $backup_name (建立於: $backup_date, 大小: $backup_size)"
        ((i++))
    done
    
    echo ""
    read -p "請選擇要恢復的資料庫備份 (1-${#db_backups[@]}): " backup_choice
    
    if [[ ! "$backup_choice" =~ ^[0-9]+$ ]] || [ "$backup_choice" -lt 1 ] || [ "$backup_choice" -gt ${#db_backups[@]} ]; then
        echo "❌ 無效的選擇"
        return 1
    fi
    
    local selected_backup="${db_backups[$((backup_choice-1))]}"
    echo "📥 恢復資料庫備份: $(basename "$selected_backup")"
    
    echo "⚠️ 警告：這將覆蓋整個資料庫！"
    read -p "確定要繼續嗎? (y/N): " confirm
    if [[ ! "$confirm" =~ ^[Yy]$ ]]; then
        echo "❌ 取消恢復操作"
        return 1
    fi
    
    # 先執行當前資料庫備份
    echo "💾 備份當前資料庫..."
    create_backup "db"
    
    # 讀取資料庫配置
    if [ ! -f "$PROJECT_DIR/backend/.env" ]; then
        echo "❌ 找不到 .env 檔案"
        return 1
    fi
    
    # 使用安全函數讀取資料庫配置
    DB_HOST=$(safe_read_env "$PROJECT_DIR/backend/.env" "DB_HOST" "localhost")
    DB_PORT=$(safe_read_env "$PROJECT_DIR/backend/.env" "DB_PORT" "3306")
    DB_DATABASE=$(safe_read_env "$PROJECT_DIR/backend/.env" "DB_DATABASE" "")
    DB_USERNAME=$(safe_read_env "$PROJECT_DIR/backend/.env" "DB_USERNAME" "")
    DB_PASSWORD=$(safe_read_env "$PROJECT_DIR/backend/.env" "DB_PASSWORD" "")
    
    # 預設值
    DB_HOST=${DB_HOST:-localhost}
    DB_PORT=${DB_PORT:-3306}
    
    # 除錯信息（僅顯示非敏感信息）
    echo "🔍 資料庫配置檢查:"
    echo "  主機: $DB_HOST"
    echo "  端口: $DB_PORT"
    echo "  資料庫: $DB_DATABASE"
    echo "  用戶: $DB_USERNAME"
    echo "  密碼: $([ -n "$DB_PASSWORD" ] && echo "已設定" || echo "未設定")"
    
    if [ -z "$DB_DATABASE" ] || [ -z "$DB_USERNAME" ] || [ -z "$DB_PASSWORD" ]; then
        echo "❌ 無法讀取完整的資料庫配置"
        echo "請檢查 .env 檔案中的資料庫設定"
        return 1
    fi
    
    echo "🗄️ 正在恢復資料庫: $DB_DATABASE"
    
    # 檢查備份檔案是否壓縮，使用臨時檔案方式避免管道操作問題
    if [[ "$selected_backup" == *.gz ]]; then
        # 壓縮檔案：解壓縮到臨時檔案再恢復
        echo "📦 解壓縮備份檔案到臨時位置..."
        local temp_sql_file="/tmp/database_restore_$$.sql"
        
        if ! gunzip -c "$selected_backup" > "$temp_sql_file"; then
            echo "❌ 解壓縮失敗"
            rm -f "$temp_sql_file"
            return 1
        fi
        
        echo "🔄 從臨時檔案恢復資料庫..."
        MYSQL_PWD="$DB_PASSWORD" mysql \
            -h "$DB_HOST" \
            -P "$DB_PORT" \
            -u "$DB_USERNAME" \
            "$DB_DATABASE" < "$temp_sql_file"
        
        local restore_result=$?
        rm -f "$temp_sql_file"
        
        if [ $restore_result -ne 0 ]; then
            echo "❌ 資料庫恢復失敗"
            return 1
        fi
    else
        # 未壓縮檔案：直接恢復
        MYSQL_PWD="$DB_PASSWORD" mysql \
            -h "$DB_HOST" \
            -P "$DB_PORT" \
            -u "$DB_USERNAME" \
            "$DB_DATABASE" < "$selected_backup"
        
        if [ $? -ne 0 ]; then
            echo "❌ 資料庫恢復失敗"
            return 1
        fi
    fi
    
    if [ $? -eq 0 ]; then
        echo "✅ 資料庫恢復完成"
        
        # 清除 Laravel 快取
        echo "🧹 清除應用快取..."
        cd "$PROJECT_DIR/backend"
        php artisan config:clear
        php artisan cache:clear
        php artisan route:clear
        cd - > /dev/null
        
    else
        echo "❌ 資料庫恢復失敗"
        return 1
    fi
    
    echo "✅ 資料庫恢復完成"
}

# 顯示所有備份狀態
show_backup_status() {
    echo "📊 【備份系統監控】備份系統狀態總覽"
    echo "====================================="
    
    # 專案備份狀態
    local project_backup_dir="$USER_HOME/line-reservation-backups/project-backups"
    if [ -d "$project_backup_dir" ]; then
        echo ""
        echo "📁 專案備份狀態:"
        local env_count=$(find "$project_backup_dir" -name "env_backup_*" -type d | wc -l)
        local project_count=$(find "$project_backup_dir" -name "project_backup_*.tar.gz" -o -name "*-project.tar.gz" | wc -l)
        local project_size=$(du -sh "$project_backup_dir" 2>/dev/null | cut -f1)
        
        echo "  🔧 環境配置備份: $env_count 個"
        echo "  📦 專案檔案備份: $project_count 個"
        echo "  💾 專案備份總大小: $project_size"
        
        # 顯示最新的專案備份
        local latest_project=$(find "$project_backup_dir" -name "project_backup_*.tar.gz" -o -name "*-project.tar.gz" | sort -r | head -1)
        if [ -n "$latest_project" ]; then
            local latest_date=$(stat -c %y "$latest_project" | cut -d' ' -f1)
            echo "  📅 最新專案備份: $(basename "$latest_project") ($latest_date)"
        fi
    else
        echo "❌ 專案備份目錄不存在"
    fi
    
    # 資料庫備份狀態
    local db_backup_dir="$USER_HOME/line-reservation-backups/database"
    if [ -d "$db_backup_dir" ]; then
        echo ""
        echo "🗄️ 資料庫備份狀態:"
        local db_count=$(find "$db_backup_dir" -name "line_reservation_backup_*.sql*" | wc -l)
        local db_size=$(du -sh "$db_backup_dir" 2>/dev/null | cut -f1)
        
        echo "  📊 資料庫備份數量: $db_count 個"
        echo "  💾 資料庫備份總大小: $db_size"
        
        # 顯示最新的資料庫備份
        local latest_db=$(find "$db_backup_dir" -name "line_reservation_backup_*.sql*" | sort -r | head -1)
        if [ -n "$latest_db" ]; then
            local latest_date=$(stat -c %y "$latest_db" | cut -d' ' -f1)
            echo "  📅 最新資料庫備份: $(basename "$latest_db") ($latest_date)"
        fi
    else
        echo "❌ 資料庫備份目錄不存在"
        echo "   請先執行完整部署腳本以設置資料庫備份系統"
    fi
    
    # 備份腳本狀態
    local scripts_dir="$USER_HOME/line-reservation-backups/scripts"
    if [ -d "$scripts_dir" ]; then
        echo ""
        echo "🔧 備份腳本狀態:"
        local manual_script="$scripts_dir/manual_backup.sh"
        local auto_script="$scripts_dir/database_backup.sh"
        local status_script="$scripts_dir/backup_status.sh"
        
        [ -f "$manual_script" ] && echo "  ✅ 手動備份腳本: $(basename "$manual_script")" || echo "  ❌ 手動備份腳本: 不存在"
        [ -f "$auto_script" ] && echo "  ✅ 自動備份腳本: $(basename "$auto_script")" || echo "  ❌ 自動備份腳本: 不存在"
        [ -f "$status_script" ] && echo "  ✅ 狀態檢查腳本: $(basename "$status_script")" || echo "  ❌ 狀態檢查腳本: 不存在"
        
        # 檢查 crontab
        echo ""
        echo "⏰ 自動備份排程:"
        if crontab -l 2>/dev/null | grep -q "database_backup.sh"; then
            echo "  ✅ 自動備份已設定"
            crontab -l | grep "database_backup.sh" | sed 's/^/  /'
        else
            echo "  ❌ 未設定自動備份"
        fi
    else
        echo "❌ 備份腳本目錄不存在"
    fi
    
    echo ""
    echo "====================================="
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

# 確保當前用戶可以存取專案目錄（但不改變 www-data 的擁有權）
echo "🔧 檢查目錄存取權限..."
if [ ! -r "$PROJECT_DIR" ]; then
    echo "❌ 無法讀取專案目錄，請檢查權限"
    exit 1
fi

cd "$PROJECT_DIR" || { echo "❌ 無法進入專案目錄"; exit 1; }

# 選單系統
show_menu() {
    echo ""
    echo "============================================="
    echo "🛠️  LINE Reservation 生產環境管理控制台"
    echo "============================================="
    echo ""
    echo "📁 【環境配置管理】"
    echo "1) 域名/IP 配置更新"
    echo "2) SSL 證書開關切換"
    echo ""
    echo "🏗️ 【建置與部署管理】"
    echo "3) 前端重新建置 (Frontend Build)"
    echo "4) 後端快取清理 (Backend Cache)"
    echo "5) 完整系統重建 (Full Rebuild)"
    echo ""
    echo "🔄 【版本控制與更新】"
    echo "6) 代碼更新與建置"
    echo "7) Git 倉庫恢復與同步"
    echo ""
    echo "⚙️ 【系統服務管理】"
    echo "8) 重啟 Web 服務"
    echo "9) 系統狀態監控"
    echo "10) 日誌查看與分析"
    echo ""
    echo "💾 【備份與恢復管理】"
    echo "11) 環境配置備份"
    echo "12) 專案檔案備份"
    echo "13) 資料庫專項備份"
    echo "14) 完整系統備份"
    echo "15) 備份恢復控制台"
    echo ""
    echo "0) 安全退出系統"
    echo "============================================="
}

# 讀取當前設定
read_current_settings() {
    CURRENT_DOMAIN=""
    CURRENT_SSL=""
    CURRENT_PROTOCOL=""
    
    # 使用安全函數讀取配置
    if [ -f "$PROJECT_DIR/backend/.env" ]; then
        local app_url=$(safe_read_env "$PROJECT_DIR/backend/.env" "APP_URL" "")
        
        if [ -n "$app_url" ]; then
            CURRENT_DOMAIN=$(echo "$app_url" | sed 's|https\?://||')
            if echo "$app_url" | grep -q "^https://"; then
                CURRENT_SSL="true"
                CURRENT_PROTOCOL="https"
            else
                CURRENT_SSL="false"
                CURRENT_PROTOCOL="http"
            fi
        else
            CURRENT_DOMAIN="未設定"
            CURRENT_SSL="未知"
            CURRENT_PROTOCOL="http"
        fi
    else
        echo "⚠️ 警告：找不到後端 .env 檔案"
        CURRENT_DOMAIN="未設定"
        CURRENT_SSL="未知"
        CURRENT_PROTOCOL="http"
    fi
    
    echo "📋 當前設定:"
    echo "  域名/IP: $CURRENT_DOMAIN"
    echo "  SSL: $CURRENT_SSL"
    echo "  協議: $CURRENT_PROTOCOL"
}

# 更新 Apache 配置
update_apache_config() {
    local domain="$1"
    local use_ssl="$2"
    local project_dir="/var/www/line-reservation"
    local apache_conf="/etc/apache2/sites-available/line-reservation.conf"
    
    echo "🔧 更新 Apache 虛擬主機配置..."
    
    # 檢測 PHP-FPM socket
    local php_fpm_handler=""
    local possible_sockets=(
        "/run/php/php8.3-fpm.sock"
        "/var/run/php/php8.3-fpm.sock"
        "/run/php/php8.2-fpm.sock"
        "/var/run/php/php8.2-fpm.sock"
        "/run/php/php8.1-fpm.sock"
        "/var/run/php/php8.1-fpm.sock"
    )
    
    for sock in "${possible_sockets[@]}"; do
        if [ -S "$sock" ]; then
            php_fpm_handler="proxy:unix:${sock}|fcgi://localhost"
            break
        fi
    done
    
    if [ -z "$php_fpm_handler" ]; then
        php_fpm_handler="proxy:fcgi://127.0.0.1:9000"
    fi
    
    if [ "$use_ssl" = "true" ]; then
        # SSL 配置
        sudo tee "$apache_conf" > /dev/null <<EOF
<VirtualHost *:80>
    ServerName $domain
    # 所有 HTTP 流量跳轉到 HTTPS
    Redirect permanent / https://$domain/
</VirtualHost>

<IfModule mod_ssl.c>
<VirtualHost *:443>
    ServerName $domain
    # ServerAlias 可選，讓 www 也導向同一個證書
    ServerAlias www.$domain
    DocumentRoot $project_dir/frontend/dist

    <Directory $project_dir/frontend/dist>
        AllowOverride All
        Require all granted
        Options FollowSymLinks

        RewriteEngine On
        RewriteBase /
        RewriteRule ^index\.html$ - [L]
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteCond %{REQUEST_URI} !^/api/
        RewriteCond %{REQUEST_URI} !^/storage/
        RewriteRule . /index.html [L]
    </Directory>

    RewriteEngine On
    RewriteRule ^/api/(.*)$ $project_dir/backend/public/index.php [QSA,L]

    <Directory $project_dir/backend/public>
        AllowOverride All
        Require all granted
        Options FollowSymLinks
        <FilesMatch "\.php$">
            SetHandler "$php_fpm_handler"
        </FilesMatch>
    </Directory>

    Alias /storage $project_dir/backend/storage/app/public
    <Directory $project_dir/backend/storage/app/public>
        AllowOverride None
        Require all granted
        Options FollowSymLinks
    </Directory>

    ErrorLog \${APACHE_LOG_DIR}/line-reservation_error.log
    CustomLog \${APACHE_LOG_DIR}/line-reservation_access.log combined

    Include /etc/letsencrypt/options-ssl-apache.conf
    SSLCertificateFile /etc/letsencrypt/live/$domain/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/$domain/privkey.pem
</VirtualHost>
</IfModule>
EOF
    else
        # 非 SSL 配置
        sudo tee "$apache_conf" > /dev/null <<EOF
<VirtualHost *:80>
    ServerName $domain
    DocumentRoot $project_dir/frontend/dist

    <Directory $project_dir/frontend/dist>
        AllowOverride All
        Require all granted
        Options FollowSymLinks
        
        RewriteEngine On
        RewriteBase /
        RewriteRule ^index\.html$ - [L]
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteCond %{REQUEST_URI} !^/api/
        RewriteCond %{REQUEST_URI} !^/storage/
        RewriteRule . /index.html [L]
    </Directory>

    RewriteEngine On
    RewriteRule ^/api/(.*)$ $project_dir/backend/public/index.php [QSA,L]

    <Directory $project_dir/backend/public>
        AllowOverride All
        Require all granted
        Options FollowSymLinks
        <FilesMatch "\.php$">
            SetHandler "$php_fpm_handler"
        </FilesMatch>
    </Directory>

    Alias /storage $project_dir/backend/storage/app/public
    <Directory $project_dir/backend/storage/app/public>
        AllowOverride None
        Require all granted
        Options FollowSymLinks
    </Directory>

    ErrorLog \${APACHE_LOG_DIR}/line-reservation_error.log
    CustomLog \${APACHE_LOG_DIR}/line-reservation_access.log combined
</VirtualHost>
EOF
    fi
    
    echo "✅ Apache 配置已更新"
}

# 更新環境變數
update_env_var() {
    local key="$1"
    local val="$2"
    local file="$3"
    
    if [ ! -f "$file" ]; then
        echo "⚠️ 警告：檔案不存在 $file"
        return 1
    fi
    
    # 臨時調整權限以便寫入
    local original_perms=$(stat -c %a "$file" 2>/dev/null)
    sudo chmod 644 "$file" 2>/dev/null || { echo "❌ 無法調整檔案權限: $file"; return 1; }
    sudo chown $USER:$USER "$file" 2>/dev/null || { echo "❌ 無法取得檔案寫入權限: $file"; return 1; }
    
    # 確保當前目錄有寫入權限給 sed 創建臨時檔案
    local current_dir=$(dirname "$file")
    local temp_dir_perms=$(stat -c %a "$current_dir" 2>/dev/null)
    sudo chmod 755 "$current_dir" 2>/dev/null || true
    sudo chown $USER:$USER "$current_dir" 2>/dev/null || true
    
    if grep -q "^${key}=" "$file" 2>/dev/null; then
        sed -i "s|^${key}=.*|${key}=${val}|" "$file" || { echo "❌ 更新環境變數失敗: $key"; return 1; }
        echo "✅ 已更新 $key=$val"
    else
        echo "${key}=${val}" >> "$file" || { echo "❌ 添加環境變數失敗: $key"; return 1; }
        echo "✅ 已添加 $key=$val"
    fi
    
    # 恢復目錄權限
    sudo chown www-data:www-data "$current_dir" 2>/dev/null || true
    sudo chmod "$temp_dir_perms" "$current_dir" 2>/dev/null || true
    
    # 恢復安全權限 (600 - 僅擁有者可讀寫)
    sudo chown www-data:www-data "$file" 2>/dev/null || true
    sudo chmod 600 "$file" 2>/dev/null || true
}

# 更新域名/IP
update_domain() {
    echo "💾 自動備份環境配置..."
    create_backup "env"
    
    echo "🌐 【環境配置】域名/IP 配置更新..."
    read_current_settings
    
    echo ""
    echo "當前域名/IP: $CURRENT_DOMAIN"
    read -p "請輸入新的域名或IP (按 Enter 保持不變): " NEW_DOMAIN
    
    if [ -z "$NEW_DOMAIN" ]; then
        echo "✅ 保持原設定: $CURRENT_DOMAIN"
        return
    fi
    
    echo "🔄 更新後端設定..."
    cd "$PROJECT_DIR/backend" || { echo "❌ 無法進入後端目錄"; return 1; }
    update_env_var "APP_URL" "${CURRENT_PROTOCOL}://$NEW_DOMAIN" ".env"
    update_env_var "FRONTEND_URL" "${CURRENT_PROTOCOL}://$NEW_DOMAIN" ".env"
    update_env_var "SANCTUM_STATEFUL_DOMAINS" "$NEW_DOMAIN" ".env"
    update_env_var "SESSION_DOMAIN" "$NEW_DOMAIN" ".env"
    update_env_var "CORS_ALLOWED_ORIGINS" "${CURRENT_PROTOCOL}://$NEW_DOMAIN" ".env"
    
    echo "🔄 更新前端設定..."
    cd "$PROJECT_DIR/frontend" || { echo "❌ 無法進入前端目錄"; return 1; }
    update_env_var "VITE_API_BASE_URL" "${CURRENT_PROTOCOL}://$NEW_DOMAIN/api" ".env"
    update_env_var "VITE_APP_BASE_URL" "${CURRENT_PROTOCOL}://$NEW_DOMAIN" ".env"
    update_env_var "VITE_APP_URL" "${CURRENT_PROTOCOL}://$NEW_DOMAIN" ".env"
    update_env_var "VITE_BACKEND_URL" "${CURRENT_PROTOCOL}://$NEW_DOMAIN/api" ".env"
    
    cd "$PROJECT_DIR" || { echo "❌ 無法返回專案目錄"; return 1; }
    echo "✅ 域名/IP 已更新為: $NEW_DOMAIN"
    
    echo "🔍 檢查配置更新結果..."
    if [ -f "$PROJECT_DIR/backend/.env" ]; then
        echo "後端配置檢查:"
        # 臨時調整權限以便檢查
        sudo chmod 644 "$PROJECT_DIR/backend/.env" 2>/dev/null || true
        grep "^APP_URL=" "$PROJECT_DIR/backend/.env" || echo "⚠️ APP_URL 未找到"
        grep "^CORS_ALLOWED_ORIGINS=" "$PROJECT_DIR/backend/.env" || echo "⚠️ CORS_ALLOWED_ORIGINS 未找到"
        # 恢復權限
        sudo chmod 600 "$PROJECT_DIR/backend/.env" 2>/dev/null || true
    fi
    
    if [ -f "$PROJECT_DIR/frontend/.env" ]; then
        echo "前端配置檢查:"
        # 臨時調整權限以便檢查
        sudo chmod 644 "$PROJECT_DIR/frontend/.env" 2>/dev/null || true
        grep "^VITE_API_BASE_URL=" "$PROJECT_DIR/frontend/.env" || echo "⚠️ VITE_API_BASE_URL 未找到"
        # 恢復權限
        sudo chmod 600 "$PROJECT_DIR/frontend/.env" 2>/dev/null || true
    fi
    
    # 檢查是否從 IP 地址改為域名，如果是且當前為 HTTP，詢問是否啟用 SSL
    local was_ip=false
    local is_domain=false
    
    if [[ $CURRENT_DOMAIN =~ ^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
        was_ip=true
    fi
    
    if [[ ! $NEW_DOMAIN =~ ^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
        is_domain=true
    fi
    
    # 更新 Apache 配置
    echo "🔧 更新 Apache 配置..."
    local use_ssl_flag
    if [ "$CURRENT_PROTOCOL" = "https" ]; then
        use_ssl_flag="true"
    else
        use_ssl_flag="false"
    fi
    update_apache_config "$NEW_DOMAIN" "$use_ssl_flag"
    
    echo "🔄 重新載入 Apache 配置..."
    sudo systemctl reload apache2
    
    # 如果從 IP 改為域名且當前為 HTTP，詢問是否啟用 SSL
    if [ "$was_ip" = true ] && [ "$is_domain" = true ] && [ "$CURRENT_PROTOCOL" = "http" ]; then
        echo ""
        echo "🔒 檢測到您從 IP 地址更改為域名，現在可以啟用 SSL 了！"
        read -p "是否要啟用 SSL? (y/N): " ENABLE_SSL_AFTER_DOMAIN
        if [[ "$ENABLE_SSL_AFTER_DOMAIN" =~ ^[Yy]$ ]]; then
            echo "🔄 正在啟用 SSL..."
            
            # 更新環境變數為 HTTPS
            cd "$PROJECT_DIR/backend" || { echo "❌ 無法進入後端目錄"; return 1; }
            update_env_var "APP_URL" "https://$NEW_DOMAIN" ".env"
            update_env_var "FRONTEND_URL" "https://$NEW_DOMAIN" ".env"
            update_env_var "CORS_ALLOWED_ORIGINS" "https://$NEW_DOMAIN" ".env"
            update_env_var "SESSION_SECURE_COOKIE" "true" ".env"
            
            cd "$PROJECT_DIR/frontend" || { echo "❌ 無法進入前端目錄"; return 1; }
            update_env_var "VITE_API_BASE_URL" "https://$NEW_DOMAIN/api" ".env"
            update_env_var "VITE_APP_BASE_URL" "https://$NEW_DOMAIN" ".env"
            update_env_var "VITE_APP_URL" "https://$NEW_DOMAIN" ".env"
            update_env_var "VITE_BACKEND_URL" "https://$NEW_DOMAIN/api" ".env"
            
            cd "$PROJECT_DIR" || { echo "❌ 無法返回專案目錄"; return 1; }
            
            # 更新 Apache 配置為 SSL
            update_apache_config "$NEW_DOMAIN" "true"
            sudo systemctl reload apache2
            
            # 獲取 SSL 憑證
            echo "🔒 設定 SSL 憑證..."
            echo "正在獲取 SSL 憑證，這可能需要幾分鐘..."
            if sudo certbot --apache -d "$NEW_DOMAIN" --non-interactive --agree-tos --email "admin@$NEW_DOMAIN" --redirect; then
                echo "✅ SSL 憑證設置成功！"
                echo "✅ 網站現在可以通過 https://$NEW_DOMAIN 訪問"
            else
                echo "⚠️ SSL 憑證設置失敗，請手動執行: sudo certbot --apache -d $NEW_DOMAIN"
                echo "✅ 域名已更新，但仍使用 HTTP"
            fi
        fi
    fi
}

# 切換 SSL
toggle_ssl() {
    echo "💾 自動備份環境配置..."
    create_backup "env"
    
    echo "🔒 【安全配置】SSL 證書狀態切換..."
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
    cd "$PROJECT_DIR/backend" || { echo "❌ 無法進入後端目錄"; return 1; }
    update_env_var "APP_URL" "${NEW_PROTOCOL}://$CURRENT_DOMAIN" ".env"
    update_env_var "FRONTEND_URL" "${NEW_PROTOCOL}://$CURRENT_DOMAIN" ".env"
    update_env_var "CORS_ALLOWED_ORIGINS" "${NEW_PROTOCOL}://$CURRENT_DOMAIN" ".env"
    
    echo "🔄 更新前端設定..."
    cd "$PROJECT_DIR/frontend" || { echo "❌ 無法進入前端目錄"; return 1; }
    update_env_var "VITE_API_BASE_URL" "${NEW_PROTOCOL}://$CURRENT_DOMAIN/api" ".env"
    update_env_var "VITE_APP_BASE_URL" "${NEW_PROTOCOL}://$CURRENT_DOMAIN" ".env"
    update_env_var "VITE_APP_URL" "${NEW_PROTOCOL}://$CURRENT_DOMAIN" ".env"
    update_env_var "VITE_BACKEND_URL" "${NEW_PROTOCOL}://$CURRENT_DOMAIN/api" ".env"
    
    cd "$PROJECT_DIR" || { echo "❌ 無法返回專案目錄"; return 1; }
    
    # 更新 Apache 配置
    echo "🔧 更新 Apache 配置..."
    local use_ssl_flag
    if [ "$NEW_PROTOCOL" = "https" ]; then
        use_ssl_flag="true"
    else
        use_ssl_flag="false"
    fi
    update_apache_config "$CURRENT_DOMAIN" "$use_ssl_flag"
    
    echo "🔄 重新載入 Apache 配置..."
    sudo systemctl reload apache2
    
    echo "✅ SSL 設定已更新為: $NEW_PROTOCOL"
    
    if [ "$NEW_PROTOCOL" = "https" ]; then
        echo "🔒 設定 SSL 憑證..."
        echo "正在獲取 SSL 憑證，這可能需要幾分鐘..."
        if sudo certbot --apache -d "$CURRENT_DOMAIN" --non-interactive --agree-tos --email "admin@$CURRENT_DOMAIN" --redirect; then
            echo "✅ SSL 憑證設置成功！"
        else
            echo "⚠️ SSL 憑證設置失敗，請手動執行: sudo certbot --apache -d $CURRENT_DOMAIN"
        fi
    fi
}

# 重建前端
rebuild_frontend() {
    echo "🏗️ 【前端建置】執行 Frontend 重新建置..."
    cd "$PROJECT_DIR/frontend" || { echo "❌ 無法進入前端目錄"; return 1; }
    
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
    if ! npm install; then
        echo "❌ npm install 失敗"
        cd "$PROJECT_DIR" || return 1
        return 1
    fi
    
    echo "🏗️ 建立生產版本..."
    if ! npm run build; then
        echo "❌ npm run build 失敗"
        cd "$PROJECT_DIR" || return 1
        return 1
    fi
    
    echo "🔧 設定權限..."
    # 設置基本擁有者權限 - 整個 frontend 目錄
    sudo chown -R www-data:www-data .
    
    # 移除不必要的執行權限，僅保留讀取權限
    find dist -type f -exec sudo chmod 644 {} \; 2>/dev/null || true
    find dist -type d -exec sudo chmod 755 {} \; 2>/dev/null || true
    
    cd "$PROJECT_DIR" || { echo "❌ 無法返回專案目錄"; return 1; }
    
    echo "✅ 前端重建完成"
}

# 清除後端快取
clear_backend_cache() {
    echo "🧹 【後端維護】清理 Laravel 快取系統..."
    cd "$PROJECT_DIR/backend" || { echo "❌ 無法進入後端目錄"; return 1; }
    
    # 確保權限正確
    sudo chown -R $USER:$USER .
    
    echo "清除配置快取..."
    php artisan config:clear || echo "⚠️ config:clear 可能失敗"
    php artisan config:cache || echo "⚠️ config:cache 可能失敗"
    
    echo "清除路由快取..."
    php artisan route:clear || echo "⚠️ route:clear 可能失敗"
    php artisan route:cache || echo "⚠️ route:cache 可能失敗"
    
    echo "清除視圖快取..."
    php artisan view:clear || echo "⚠️ view:clear 可能失敗"
    
    echo "清除應用快取..."
    php artisan cache:clear || echo "⚠️ cache:clear 可能失敗"
    
    echo "🔧 設定權限..."
    # 設置基本擁有者權限 - 整個 backend 目錄
    sudo chown -R www-data:www-data .
    
    # 設置寫入權限但限制為 755，不使用 775
    sudo chmod -R 755 storage bootstrap/cache 2>/dev/null || true
    
    cd "$PROJECT_DIR" || { echo "❌ 無法返回專案目錄"; return 1; }
    
    echo "✅ 後端快取清除完成"
}

# 完整重建
full_rebuild() {
    echo "🔄 【系統重建】執行完整系統重建流程..."
    clear_backend_cache
    rebuild_frontend
    
    echo "🔧 最終權限設定..."
    set_secure_permissions
    
    echo "✅ 完整重建完成"
}

# 恢復 Git 並更新代碼
restore_git_and_update() {
    echo "🔄 【版本控制】Git 倉庫恢復與代碼同步..."
    
    # 確保權限
    sudo chown -R $USER:$USER "$PROJECT_DIR"
    
    # 檢查是否已有 Git
    if [ -d ".git" ]; then
        echo "✅ Git 目錄已存在"
        read -p "是否要重新初始化 Git? (y/N): " REINIT_GIT
        if [[ "$REINIT_GIT" =~ ^[Yy]$ ]]; then
            echo "🗑️ 移除現有 Git..."
            sudo rm -rf .git
        else
            echo "📥 使用現有 Git 拉取更新..."
            git pull origin main || echo "⚠️ Git 更新可能失敗，請檢查"
            cleanup_git_and_rebuild
            return
        fi
    fi
    
    echo "🔧 設定 Git..."
    if ! git config --global --add safe.directory "$PROJECT_DIR"; then
        echo "⚠️ Git 安全設定可能失敗"
    fi
    
    echo "🔄 初始化 Git 倉庫..."
    if ! git init; then
        echo "❌ Git 初始化失敗"
        return 1
    fi
    
    echo "🔗 添加遠端倉庫..."
    read -p "請輸入 Git 倉庫 URL (預設: https://github.com/spencerkuku/line-reservation.git): " REPO_URL
    if [ -z "$REPO_URL" ]; then
        REPO_URL="https://github.com/spencerkuku/line-reservation.git"
    fi
    
    if ! git remote add origin "$REPO_URL"; then
        echo "❌ 添加遠端倉庫失敗"
        return 1
    fi
    
    echo "📥 拉取最新代碼..."
    if ! git fetch origin; then
        echo "❌ 拉取代碼失敗，請檢查網路連線和倉庫URL"
        return 1
    fi
    
    echo "🌿 選擇分支..."
    read -p "請輸入要使用的分支名稱 (預設: main): " BRANCH_NAME
    if [ -z "$BRANCH_NAME" ]; then
        BRANCH_NAME="main"
    fi
    
    echo "🔄 切換到分支: $BRANCH_NAME"
    if ! git checkout -b "$BRANCH_NAME" "origin/$BRANCH_NAME" 2>/dev/null && ! git checkout "$BRANCH_NAME" 2>/dev/null; then
        echo "❌ 無法切換到分支 $BRANCH_NAME"
        echo "📋 可用的遠端分支:"
        git branch -r 2>/dev/null || echo "無法列出遠端分支"
        return 1
    fi
    
    echo "🔄 執行完整重建..."
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
    echo "📥 【代碼更新】拉取最新代碼並執行重建..."
    
    if [ ! -d ".git" ]; then
        echo "❌ 沒有 Git 倉庫，請選擇選項 7 來恢復 Git"
        return 1
    fi
    
    # 確保權限
    sudo chown -R $USER:$USER "$PROJECT_DIR" || { echo "❌ 無法設定權限"; return 1; }
    
    echo "📥 拉取最新代碼..."
    if ! git pull origin main; then
        echo "❌ Git 更新失敗，請檢查網路連線或手動處理衝突"
        return 1
    fi
    
    echo "🔄 執行完整重建和清理..."
    cleanup_git_and_rebuild
    
    echo "✅ 代碼更新並重建完成"
}
cleanup_git_and_rebuild() {
    echo "🔄 執行完整重建..."
    clear_backend_cache
    rebuild_frontend
    
    echo "�️ 清理 Git 資料..."
    cleanup_git_files
    
    echo "� 最終權限設定..."
    set_secure_permissions
    
    echo "✅ 完整重建和清理完成"
}

# 重啟服務
restart_services() {
    echo "⚙️ 【服務管理】重啟 Web 服務器與相關服務..."
    sudo systemctl reload apache2
    
    echo "🔄 重啟 PHP-FPM..."
    sudo systemctl restart php8.3-fpm || sudo systemctl restart php-fpm || echo "⚠️ PHP-FPM 重啟可能失敗"
    
    echo "✅ 服務重啟完成"
}

# 檢查狀態
check_status() {
    echo "📊 【系統監控】檢查系統與服務運行狀態..."
    
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
    echo "📋 【日誌分析】查看系統與應用日誌..."
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
        read -p "請選擇操作 (0-15): " CHOICE
        
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
                restore_git_and_update
                ;;
            8)
                restart_services
                ;;
            9)
                check_status
                ;;
            10)
                view_logs
                ;;
            11)
                create_backup "env"
                ;;
            12)
                create_backup "project"
                ;;
            13)
                create_backup "db"
                ;;
            14)
                create_backup "full"
                ;;
            15)
                restore_backup
                ;;
            0)
                echo "👋 感謝使用！系統已安全退出"
                exit 0
                ;;
            *)
                echo "❌ 無效選擇，請輸入 0-15 之間的數字"
                ;;
        esac
        
        echo ""
        read -p "按 Enter 繼續..."
    done
}

# 執行主程序
main
