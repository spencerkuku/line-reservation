#!/bin/bash
# ===================================================
# 備份/恢復模組 - lib/backup.sh
# Env、Project、Full 備份與恢復
# ===================================================

# 防止重複載入
if [ -n "$_BACKUP_SH_LOADED" ]; then
    return 0
fi
_BACKUP_SH_LOADED=1

# 載入核心模組和資料庫模組
source "$(dirname "${BASH_SOURCE[0]}")/core.sh"
source "$(dirname "${BASH_SOURCE[0]}")/db.sh"

# ===== 備份目錄 =====
get_backup_dir() {
    local backup_type="${1:-all}"
    local base_dir="${BACKUP_DIR:-$HOME/line-reservation-backups}"
    
    case "$backup_type" in
        env)      echo "$base_dir/env" ;;
        project)  echo "$base_dir/project-backups" ;; 
        db)       echo "$base_dir/database" ;;        
        full)     echo "$base_dir/full" ;;
        *)        echo "$base_dir" ;;
    esac
}

ensure_backup_dirs() {
    log_step "確保備份目錄存在..."
    
    local dirs=(
        "$(get_backup_dir env)"
        "$(get_backup_dir project)"
        "$(get_backup_dir db)"
        "$(get_backup_dir full)"
    )
    
    for dir in "${dirs[@]}"; do
        if [ ! -d "$dir" ]; then
            mkdir -p "$dir"
            sudo chown -R $USER:$USER "$(dirname "$dir")" 2>/dev/null
            chmod 755 "$dir"
            log_info "已建立: $dir"
        fi
    done
    
    log_success "備份目錄已就緒"
}

# ===== 環境配置備份 =====
backup_env() {
    local project_dir="${1:-$PROJECT_DIR}"
    local backup_dir="${2:-$(get_backup_dir env)}"
    
    log_step "備份環境配置..."
    
    ensure_backup_dirs
    
    local timestamp=$(date +%Y%m%d_%H%M%S)
    local backup_name="env_backup_${timestamp}"
    local backup_path="$backup_dir/$backup_name"
    
    mkdir -p "$backup_path"
    
    # 使用 sudo cp 解決權限不足
    if [ -f "$project_dir/backend/.env" ]; then
        sudo cp "$project_dir/backend/.env" "$backup_path/backend.env"
        sudo chown $USER:$USER "$backup_path/backend.env"
        log_info "已備份 backend/.env"
    fi
    
    if [ -f "$project_dir/frontend/.env" ]; then
        sudo cp "$project_dir/frontend/.env" "$backup_path/frontend.env"
        sudo chown $USER:$USER "$backup_path/frontend.env"
        log_info "已備份 frontend/.env"
    fi
    
    if [ -f "$project_dir/db_credentials.txt" ]; then
        sudo cp "$project_dir/db_credentials.txt" "$backup_path/db_credentials.txt"
        sudo chown $USER:$USER "$backup_path/db_credentials.txt"
        log_info "已備份 db_credentials.txt"
    fi
    
    # 壓縮
    cd "$backup_dir"
    tar -czf "${backup_name}.tar.gz" "$backup_name"
    rm -rf "$backup_name"
    
    chmod 600 "${backup_name}.tar.gz"
    
    log_success "環境配置備份完成: ${backup_name}.tar.gz"
    echo "$backup_dir/${backup_name}.tar.gz"
}

restore_env() {
    local backup_file="$1"
    local project_dir="${2:-$PROJECT_DIR}"
    
    log_step "恢復環境配置..."
    
    if [ ! -f "$backup_file" ]; then
        log_error "備份檔案不存在: $backup_file"
        return 1
    fi
    
    local temp_dir=$(mktemp -d)
    
    tar -xzf "$backup_file" -C "$temp_dir"
    
    local extracted_dir=$(find "$temp_dir" -maxdepth 1 -type d -name "env_backup_*" | head -1)
    
    if [ -z "$extracted_dir" ]; then
        log_error "備份格式錯誤"
        rm -rf "$temp_dir"
        return 1
    fi
    
    # 使用 sudo cp 恢復
    if [ -f "$extracted_dir/backend.env" ]; then
        sudo cp "$extracted_dir/backend.env" "$project_dir/backend/.env"
        sudo chown www-data:www-data "$project_dir/backend/.env"
        sudo chmod 600 "$project_dir/backend/.env"
        log_info "已恢復 backend/.env"
    fi
    
    if [ -f "$extracted_dir/frontend.env" ]; then
        sudo cp "$extracted_dir/frontend.env" "$project_dir/frontend/.env"
        sudo chown www-data:www-data "$project_dir/frontend/.env"
        sudo chmod 600 "$project_dir/frontend/.env"
        log_info "已恢復 frontend/.env"
    fi
    
    if [ -f "$extracted_dir/db_credentials.txt" ]; then
        sudo cp "$extracted_dir/db_credentials.txt" "$project_dir/db_credentials.txt"
        sudo chown www-data:www-data "$project_dir/db_credentials.txt"
        sudo chmod 600 "$project_dir/db_credentials.txt"
        log_info "已恢復 db_credentials.txt"
    fi
    
    rm -rf "$temp_dir"
    
    log_success "環境配置恢復完成"
}

list_env_backups() {
    local backup_dir="${1:-$(get_backup_dir env)}"
    
    if [ ! -d "$backup_dir" ]; then
        echo "無備份"
        return
    fi
    
    ls -lt "$backup_dir"/env_backup_*.tar.gz 2>/dev/null | head -10 | while read -r line; do
        echo "  $line"
    done
}

# ===== 專案檔案備份 =====
backup_project() {
    local project_dir="${1:-$PROJECT_DIR}"
    local backup_dir="${2:-$(get_backup_dir project)}"
    
    log_step "備份專案檔案..."
    
    ensure_backup_dirs
    
    local timestamp=$(date +%Y%m%d_%H%M%S)
    local backup_name="project_backup_${timestamp}.tar.gz"
    local backup_path="$backup_dir/$backup_name"
    
    # 使用 sudo tar 解決權限問題
    sudo tar -czf "$backup_path" \
        --exclude='vendor' \
        --exclude='node_modules' \
        --exclude='.git' \
        --exclude='storage/logs/*' \
        --exclude='storage/framework/cache/*' \
        --exclude='storage/framework/sessions/*' \
        --exclude='storage/framework/views/*' \
        --exclude='bootstrap/cache/*' \
        --exclude='frontend/dist' \
        -C "$(dirname "$project_dir")" \
        "$(basename "$project_dir")"
    
    if [ $? -eq 0 ]; then
        sudo chown $USER:$USER "$backup_path"
        chmod 600 "$backup_path"
        local size=$(du -h "$backup_path" | cut -f1)
        log_success "專案備份完成: $backup_name ($size)"
        echo "$backup_path"
    else
        log_error "專案備份失敗"
        return 1
    fi
}

restore_project() {
    local backup_file="$1"
    local restore_dir="${2:-$(dirname "$PROJECT_DIR")}"
    
    log_step "恢復專案檔案..."
    
    if [ ! -f "$backup_file" ]; then
        log_error "備份檔案不存在: $backup_file"
        return 1
    fi
    
    log_warning "這將覆蓋現有專案檔案!"
    
    if ! confirm_action "確定要恢復嗎?"; then
        log_info "已取消"
        return 1
    fi
    
    sudo tar -xzf "$backup_file" -C "$restore_dir"
    
    if [ $? -eq 0 ]; then
        set_secure_permissions "$PROJECT_DIR"
        log_success "專案恢復完成"
    else
        log_error "專案恢復失敗"
        return 1
    fi
}

list_project_backups() {
    local backup_dir="${1:-$(get_backup_dir project)}"
    
    if [ ! -d "$backup_dir" ]; then
        echo "無備份"
        return
    fi
    
    ls -lt "$backup_dir"/project_backup_*.tar.gz 2>/dev/null | head -10 | while read -r line; do
        echo "  $line"
    done
}

# ===== 完整備份 =====
backup_full() {
    local project_dir="${1:-$PROJECT_DIR}"
    local backup_dir="${2:-$(get_backup_dir full)}"
    
    log_step "執行完整備份 (專案 + 資料庫)..."
    
    ensure_backup_dirs
    
    local timestamp=$(date +%Y%m%d_%H%M%S)
    local full_backup_dir="$backup_dir/full_backup_${timestamp}"
    
    mkdir -p "$full_backup_dir"
    
    local project_backup=$(backup_project "$project_dir" "$full_backup_dir")
    local db_backup=$(backup_database "$full_backup_dir")
    local env_backup=$(backup_env "$project_dir" "$full_backup_dir")
    
    cat > "$full_backup_dir/manifest.txt" << EOF
# 完整備份資訊
# 建立時間: $(date)
# 專案目錄: $project_dir

FILES:
$(ls -la "$full_backup_dir")
EOF
    
    cd "$backup_dir"
    tar -czf "full_backup_${timestamp}.tar.gz" "full_backup_${timestamp}"
    rm -rf "full_backup_${timestamp}"
    
    sudo chown $USER:$USER "full_backup_${timestamp}.tar.gz"
    chmod 600 "full_backup_${timestamp}.tar.gz"
    
    local size=$(du -h "$backup_dir/full_backup_${timestamp}.tar.gz" | cut -f1)
    log_success "完整備份完成: full_backup_${timestamp}.tar.gz ($size)"
}

restore_full() {
    local backup_file="$1"
    local project_dir="${2:-$PROJECT_DIR}"
    
    log_step "執行完整恢復..."
    
    if [ ! -f "$backup_file" ]; then
        log_error "備份檔案不存在: $backup_file"
        return 1
    fi
    
    log_warning "這將完全恢復專案和資料庫!"
    
    if ! confirm_action "確定要執行完整恢復嗎? 這會覆蓋所有現有資料!"; then
        log_info "已取消"
        return 1
    fi
    
    local temp_dir=$(mktemp -d)
    
    tar -xzf "$backup_file" -C "$temp_dir"
    
    local extracted_dir=$(find "$temp_dir" -maxdepth 1 -type d -name "full_backup_*" | head -1)
    
    if [ -z "$extracted_dir" ]; then
        log_error "備份格式錯誤"
        rm -rf "$temp_dir"
        return 1
    fi
    
    # 恢復各組件
    local project_file=$(find "$extracted_dir" -name "project_backup_*.tar.gz" | head -1)
    if [ -n "$project_file" ]; then
        restore_project "$project_file" "$(dirname "$project_dir")"
    fi
    
    local db_file=$(find "$extracted_dir" -name "line_reservation_backup_*.sql*" | head -1)
    if [ -n "$db_file" ]; then
        restore_database "$db_file"
    else
        db_file=$(find "$extracted_dir" -name "db_backup_*.sql*" | head -1)
        if [ -n "$db_file" ]; then
            restore_database "$db_file"
        fi
    fi
    
    local env_file=$(find "$extracted_dir" -name "env_backup_*.tar.gz" | head -1)
    if [ -n "$env_file" ]; then
        restore_env "$env_file" "$project_dir"
    fi
    
    rm -rf "$temp_dir"
    set_secure_permissions "$project_dir"
    log_success "完整恢復完成"
}

# ===== 備份管理 =====
create_backup() {
    local backup_type="$1"
    local project_dir="${2:-$PROJECT_DIR}"
    
    case "$backup_type" in
        env) backup_env "$project_dir" ;;
        project) backup_project "$project_dir" ;;
        db) backup_database "$(get_backup_dir db)" ;;
        full) backup_full "$project_dir" ;;
        *)
            log_error "未知的備份類型: $backup_type"
            return 1
            ;;
    esac
}

restore_backup() {
    local backup_type="$1"
    
    local backup_dir=$(get_backup_dir "$backup_type")
    # 呼叫修正後的 select_backup_to_restore
    local backup_file=$(select_backup_to_restore "$backup_type")
    
    if [ -z "$backup_file" ]; then
        # 這裡不顯示錯誤，因為 select_backup_to_restore 已經 (在 stderr) 顯示過了
        return 1
    fi
    
    case "$backup_type" in
        env) restore_env "$backup_file" ;;
        project) restore_project "$backup_file" ;;
        db) restore_database "$backup_file" ;;
        full) restore_full "$backup_file" ;;
    esac
}

# ===== 備份清理 =====
cleanup_old_backups() {
    local retention_days="${2:-30}"
    log_step "清理超過 $retention_days 天的備份..."
    
    local dirs=(
        "$(get_backup_dir env)"
        "$(get_backup_dir project)"
        "$(get_backup_dir db)"
        "$(get_backup_dir full)"
    )
    
    local total_deleted=0
    for dir in "${dirs[@]}"; do
        if [ -d "$dir" ]; then
            local count=$(find "$dir" -type f \( -name "*.tar.gz" -o -name "*.sql.gz" \) -mtime +$retention_days 2>/dev/null | wc -l)
            if [ "$count" -gt 0 ]; then
                 find "$dir" -type f \( -name "*.tar.gz" -o -name "*.sql.gz" \) -mtime +$retention_days -delete
                 ((total_deleted+=count))
            fi
        fi
    done
    
    if [ "$total_deleted" -gt 0 ]; then
        log_success "已清理 $total_deleted 個過期備份"
    else
        log_info "沒有需要清理的備份"
    fi
}

# ===== 備份狀態 =====
show_backup_status() {
    log_header "備份狀態總覽"
    
    echo ""
    echo "📁 環境配置備份:"
    list_env_backups "$(get_backup_dir env)"
    
    echo ""
    echo "📦 專案備份:"
    list_project_backups "$(get_backup_dir project)"
    
    echo ""
    echo "🗄️ 資料庫備份:"
    if type list_db_backups &>/dev/null; then
        list_db_backups "$(get_backup_dir db)"
    else
        echo "  (db 模組未載入)"
    fi
    
    echo ""
    local base_dir="${BACKUP_DIR:-$HOME/line-reservation-backups}"
    local total_size=$(du -sh "$base_dir" 2>/dev/null | cut -f1)
    echo "💾 備份總大小: ${total_size:-0}"
    echo "📍 備份位置: $base_dir"
}

get_backup_size() {
    local backup_path="$1"
    if [ -f "$backup_path" ]; then
        du -h "$backup_path" | cut -f1
    else
        echo "0"
    fi
}

# ===== 備份選單 =====
show_backup_menu() {
    log_header "備份管理"
    echo ""
    echo "1) 備份環境配置 (.env)"
    echo "2) 備份專案檔案"
    echo "3) 備份資料庫"
    echo "4) 完整備份 (全部)"
    echo "5) 恢復環境配置"
    echo "6) 恢復專案檔案"
    echo "7) 恢復資料庫"
    echo "8) 完整恢復"
    echo "9) 查看備份狀態"
    echo "c) 清理舊備份"
    echo "q) 返回"
    echo ""
    
    read -p "請選擇 [1-9/c/q]: " choice
    
    case "$choice" in
        1) create_backup "env" ;;
        2) create_backup "project" ;;
        3) create_backup "db" ;;
        4) create_backup "full" ;;
        5) restore_backup "env" ;;
        6) restore_backup "project" ;;
        7) restore_backup "db" ;;
        8) restore_backup "full" ;;
        9) show_backup_status ;;
        c) cleanup_old_backups ;;
        q) return 0 ;;
        *) log_error "無效選項" ;;
    esac
}

# 【關鍵修正】選單輸出重定向至 stderr
select_backup_to_restore() {
    local backup_type="$1"
    local backup_dir=$(get_backup_dir "$backup_type")
    
    # 所有的提示訊息都必須加 >&2，才不會被 capture 到變數裡
    log_step "選擇要恢復的備份..." >&2
    
    local pattern
    case "$backup_type" in
        env)     pattern="env_backup_*.tar.gz" ;;
        project) pattern="project_backup_*.tar.gz" ;;
        db)      pattern="line_reservation_backup_*.sql*" ;;
        full)    pattern="full_backup_*.tar.gz" ;;
    esac
    
    local backups=($(ls -t "$backup_dir"/$pattern 2>/dev/null))
    
    if [ ${#backups[@]} -eq 0 ]; then
        if [ "$backup_type" == "db" ]; then
             backups=($(ls -t "$backup_dir"/db_backup_*.sql* 2>/dev/null))
        fi
        
        if [ ${#backups[@]} -eq 0 ]; then
            log_warning "沒有可用的備份" >&2
            return 1
        fi
    fi
    
    echo "" >&2
    echo "可用的備份:" >&2
    local i=1
    for backup in "${backups[@]:0:10}"; do
        local size=$(get_backup_size "$backup")
        local name=$(basename "$backup")
        echo "  $i) $name ($size)" >&2
        ((i++))
    done
    echo "" >&2
    
    read -p "請選擇 [1-${#backups[@]}]: " selection
    
    if [[ "$selection" =~ ^[0-9]+$ ]] && [ "$selection" -ge 1 ] && [ "$selection" -le ${#backups[@]} ]; then
        echo "${backups[$((selection-1))]}"
    else
        log_error "無效選擇" >&2
        return 1
    fi
}

log_info "備份模組已載入"