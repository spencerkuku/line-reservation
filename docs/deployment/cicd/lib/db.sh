#!/bin/bash
# ===================================================
# 資料庫模組 - lib/db.sh
# MySQL 設置、憑證管理、備份與恢復
# ===================================================

# 防止重複載入
if [ -n "$_DB_SH_LOADED" ]; then
    return 0
fi
_DB_SH_LOADED=1

# 載入核心模組
source "$(dirname "${BASH_SOURCE[0]}")/core.sh"

# ===== 資料庫初始化 =====
setup_database() {
    local db_name="${1:-$DB_NAME}"
    local db_user="${2:-$DB_USER}"
    
    log_step "設置 MySQL 資料庫..."
    
    # 啟動 MySQL 服務
    sudo systemctl start mysql
    sudo systemctl enable mysql
    
    # 生成密碼
    local db_pass=$(generate_db_password)
    
    # 獲取 MySQL 命令
    local mysql_cmd=$(get_mysql_command)
    if [ -z "$mysql_cmd" ]; then
        log_error "無法連接 MySQL"
        return 1
    fi
    
    # 建立資料庫和使用者
    log_step "建立資料庫: $db_name"
    $mysql_cmd -e "CREATE DATABASE IF NOT EXISTS \`${db_name}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    
    log_step "建立使用者: $db_user"
    $mysql_cmd -e "CREATE USER IF NOT EXISTS '${db_user}'@'localhost' IDENTIFIED WITH mysql_native_password BY '${db_pass}';"
    $mysql_cmd -e "ALTER USER '${db_user}'@'localhost' IDENTIFIED WITH mysql_native_password BY '${db_pass}';"
    $mysql_cmd -e "GRANT ALL PRIVILEGES ON \`${db_name}\`.* TO '${db_user}'@'localhost';"
    $mysql_cmd -e "FLUSH PRIVILEGES;"
    
    # 保存憑證
    save_db_credentials "$db_name" "$db_user" "$db_pass"
    
    log_success "資料庫設置完成"
    echo "$db_pass"
}

generate_db_password() {
    openssl rand -hex 16
}

save_db_credentials() {
    local db_name="$1"
    local db_user="$2"
    local db_pass="$3"
    
    local cred_file="${CREDENTIALS_FILE:-$USER_HOME/.line-reservation-credentials}"
    
    cat > "$cred_file" <<EOF
# LINE Reservation 資料庫憑證
# 生成時間: $(date)

Database: $db_name
Username: $db_user
Password: $db_pass
EOF
    
    chmod 600 "$cred_file"
    log_success "憑證已保存: $cred_file"
    
    # 同時備份到專案目錄
    if [ -d "$PROJECT_DIR" ]; then
        cp "$cred_file" "$PROJECT_DIR/db_credentials.txt"
        chmod 600 "$PROJECT_DIR/db_credentials.txt"
    fi
}

# ===== 資料庫連線 =====
get_mysql_command() {
    # 嘗試不同的連接方式
    if sudo mysql --defaults-file=/etc/mysql/debian.cnf -e "SELECT 1;" &>/dev/null 2>&1; then
        echo "sudo mysql --defaults-file=/etc/mysql/debian.cnf"
    elif sudo mysql -e "SELECT 1;" &>/dev/null 2>&1; then
        echo "sudo mysql"
    elif mysql -e "SELECT 1;" &>/dev/null 2>&1; then
        echo "mysql"
    else
        echo ""
    fi
}

test_db_connection() {
    local db_name="${1:-$DB_NAME}"
    local db_user="${2:-$DB_USER}"
    local db_pass="$3"
    
    if [ -z "$db_pass" ]; then
        db_pass=$(read_db_password)
    fi
    
    if MYSQL_PWD="$db_pass" mysql -u "$db_user" -e "SELECT 1;" "$db_name" &>/dev/null 2>&1; then
        log_success "資料庫連線成功"
        return 0
    else
        log_error "資料庫連線失敗"
        return 1
    fi
}

read_db_credentials() {
    local cred_file="${CREDENTIALS_FILE:-$USER_HOME/.line-reservation-credentials}"
    
    if [ ! -f "$cred_file" ]; then
        log_error "找不到憑證檔案: $cred_file"
        return 1
    fi
    
    local db_name=$(grep "^Database:" "$cred_file" | cut -d' ' -f2)
    local db_user=$(grep "^Username:" "$cred_file" | cut -d' ' -f2)
    local db_pass=$(grep "^Password:" "$cred_file" | cut -d' ' -f2)
    
    echo "$db_name|$db_user|$db_pass"
}

read_db_password() {
    local cred_file="${CREDENTIALS_FILE:-$USER_HOME/.line-reservation-credentials}"
    
    if [ -f "$cred_file" ]; then
        grep "^Password:" "$cred_file" | cut -d' ' -f2
    else
        # 嘗試從 .env 讀取
        safe_read_env "$PROJECT_DIR/backend/.env" "DB_PASSWORD" ""
    fi
}

# ===== 資料庫備份 =====
backup_database() {
    local backup_dir="${1:-$DB_BACKUP_DIR}"
    local timestamp=$(get_timestamp)
    local backup_file="$backup_dir/line_reservation_backup_${timestamp}.sql"
    
    log_step "備份資料庫..."
    
    # 確保備份目錄存在
    mkdir -p "$backup_dir"
    chmod 700 "$backup_dir"
    
    # 讀取憑證
    local db_pass=$(read_db_password)
    local db_name="${DB_NAME:-line_reservation}"
    local db_user="${DB_USER:-line_user}"
    
    if [ -z "$db_pass" ]; then
        log_error "無法讀取資料庫密碼"
        return 1
    fi
    
    # 執行備份
    if MYSQL_PWD="$db_pass" mysqldump \
        --user="$db_user" \
        --host="${DB_HOST:-localhost}" \
        --port="${DB_PORT:-3306}" \
        --single-transaction \
        --routines \
        --triggers \
        --events \
        --hex-blob \
        --databases "$db_name" > "$backup_file" 2>/dev/null; then
        
        # 壓縮備份
        gzip "$backup_file"
        chmod 600 "${backup_file}.gz"
        
        log_success "資料庫備份完成: ${backup_file}.gz"
        echo "${backup_file}.gz"
        return 0
    else
        log_error "資料庫備份失敗"
        return 1
    fi
}

setup_backup_cron() {
    local backup_script="${1:-$BACKUP_SCRIPTS_DIR/database_backup.sh}"
    
    log_step "設置自動備份排程..."
    
    # 檢查是否已存在
    if crontab -l 2>/dev/null | grep -q "database_backup.sh"; then
        log_info "備份排程已存在"
        return 0
    fi
    
    # 添加每日凌晨 2 點備份
    (crontab -l 2>/dev/null; echo "0 2 * * * $backup_script >> $LOG_DIR/backup.log 2>&1") | crontab -
    
    log_success "已設置每日自動備份 (凌晨 2:00)"
}

create_backup_script() {
    local script_path="${1:-$BACKUP_SCRIPTS_DIR/database_backup.sh}"
    
    log_step "建立備份腳本..."
    
    mkdir -p "$(dirname "$script_path")"
    
    cat > "$script_path" <<'BACKUP_EOF'
#!/bin/bash
# LINE Reservation 自動資料庫備份腳本

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "$SCRIPT_DIR/../config.sh" 2>/dev/null || true
source "$SCRIPT_DIR/../lib/core.sh" 2>/dev/null || true
source "$SCRIPT_DIR/../lib/db.sh" 2>/dev/null || true

# 配置
BACKUP_DIR="${DB_BACKUP_DIR:-$HOME/line-reservation-backups/database}"
RETENTION_DAYS="${BACKUP_RETENTION_DAYS:-30}"

# 執行備份
backup_database "$BACKUP_DIR"

# 清理舊備份
find "$BACKUP_DIR" -name "*.sql.gz" -mtime +$RETENTION_DAYS -delete 2>/dev/null || true

echo "$(date): 備份完成"
BACKUP_EOF

    chmod 700 "$script_path"
    log_success "備份腳本已建立: $script_path"
}

# ===== 資料庫恢復 =====
restore_database() {
    local backup_file="$1"
    
    if [ ! -f "$backup_file" ]; then
        log_error "備份檔案不存在: $backup_file"
        return 1
    fi
    
    log_step "恢復資料庫..."
    
    # 讀取憑證
    local db_pass=$(read_db_password)
    local db_name="${DB_NAME:-line_reservation}"
    local db_user="${DB_USER:-line_user}"
    
    if [ -z "$db_pass" ]; then
        log_error "無法讀取資料庫密碼"
        return 1
    fi
    
    # 先備份當前資料庫
    log_step "先備份當前資料庫狀態..."
    backup_database
    
    # 恢復
    if [[ "$backup_file" == *.gz ]]; then
        # 壓縮檔案
        if gunzip -c "$backup_file" | MYSQL_PWD="$db_pass" mysql -u "$db_user"; then
            log_success "資料庫恢復完成"
            return 0
        fi
    else
        # 未壓縮檔案
        if MYSQL_PWD="$db_pass" mysql -u "$db_user" < "$backup_file"; then
            log_success "資料庫恢復完成"
            return 0
        fi
    fi
    
    log_error "資料庫恢復失敗"
    return 1
}

list_db_backups() {
    local backup_dir="${1:-$DB_BACKUP_DIR}"
    
    if [ ! -d "$backup_dir" ]; then
        log_warning "備份目錄不存在: $backup_dir"
        return 1
    fi
    
    echo ""
    echo "📋 可用的資料庫備份:"
    echo ""
    
    local i=1
    local backups=()
    
    while IFS= read -r backup; do
        backups+=("$backup")
        local name=$(basename "$backup")
        local size=$(du -h "$backup" 2>/dev/null | cut -f1)
        local date=$(echo "$name" | grep -oP '\d{8}_\d{6}' || echo "未知")
        echo "  $i) $name (大小: $size)"
        ((i++))
    done < <(find "$backup_dir" -name "*.sql*" -type f | sort -r)
    
    if [ ${#backups[@]} -eq 0 ]; then
        log_warning "沒有找到備份檔案"
        return 1
    fi
    
    # 輸出備份陣列供選擇使用
    printf '%s\n' "${backups[@]}"
}

# ===== 資料庫維護 =====
optimize_database() {
    log_step "優化資料庫..."
    
    local db_pass=$(read_db_password)
    local db_name="${DB_NAME:-line_reservation}"
    local db_user="${DB_USER:-line_user}"
    
    if [ -z "$db_pass" ]; then
        log_error "無法讀取資料庫密碼"
        return 1
    fi
    
    MYSQL_PWD="$db_pass" mysqlcheck -u "$db_user" --optimize "$db_name" 2>/dev/null
    
    log_success "資料庫優化完成"
}

check_database_size() {
    local db_pass=$(read_db_password)
    local db_name="${DB_NAME:-line_reservation}"
    local db_user="${DB_USER:-line_user}"
    
    if [ -z "$db_pass" ]; then
        log_error "無法讀取資料庫密碼"
        return 1
    fi
    
    echo ""
    echo "📊 資料庫大小:"
    MYSQL_PWD="$db_pass" mysql -u "$db_user" -e "
        SELECT 
            table_schema AS 'Database',
            ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'Size (MB)'
        FROM information_schema.tables
        WHERE table_schema = '$db_name'
        GROUP BY table_schema;
    " 2>/dev/null
}

log_info "資料庫模組已載入"
