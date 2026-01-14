#!/bin/bash
# ===================================================
# 核心共用函數模組 - lib/core.sh
# 提供日誌、環境變數操作、權限設置等基礎功能
# ===================================================

# 防止重複載入
if [ -n "$_CORE_SH_LOADED" ]; then
    return 0
fi
_CORE_SH_LOADED=1

# ===== 顏色定義 =====
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
MAGENTA='\033[0;35m'
NC='\033[0m' # No Color

# ===== 日誌函數 =====
log_info() {
    echo -e "${BLUE}ℹ️  $1${NC}"
}

log_success() {
    echo -e "${GREEN}✅ $1${NC}"
}

log_warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

log_error() {
    echo -e "${RED}❌ $1${NC}"
}

log_step() {
    echo -e "${CYAN}🔧 $1${NC}"
}

log_header() {
    echo ""
    echo -e "${MAGENTA}=============================================${NC}"
    echo -e "${MAGENTA}$1${NC}"
    echo -e "${MAGENTA}=============================================${NC}"
}

# ===== 環境變數操作 =====
safe_read_env() {
    local env_file="$1"
    local key="$2"
    local default_value="${3:-}"
    
    if [ ! -f "$env_file" ]; then
        echo "$default_value"
        return 1
    fi
    
    # 臨時調整權限以便讀取
    local original_perms=$(stat -c %a "$env_file" 2>/dev/null || echo "644")
    sudo chmod 644 "$env_file" 2>/dev/null || true
    
    # 讀取值 - 處理有引號和無引號的情況
    local value=$(grep "^${key}=" "$env_file" 2>/dev/null | head -1 | cut -d'=' -f2- | sed 's/^["'\'']*//;s/["'\'']*$//' || echo "")
    
    # 恢復安全權限
    sudo chmod "$original_perms" "$env_file" 2>/dev/null || true
    
    if [ -n "$value" ]; then
        echo "$value"
    else
        echo "$default_value"
    fi
}

update_env_var() {
    local key="$1"
    local value="$2"
    local file="$3"
    
    if [ ! -f "$file" ]; then
        log_error "環境檔案不存在: $file"
        return 1
    fi
    
    # 取得目錄路徑
    local dir=$(dirname "$file")
    
    # 記錄原始權限
    local original_file_perms=$(stat -c %a "$file" 2>/dev/null || echo "600")
    local original_file_owner=$(stat -c %U:%G "$file" 2>/dev/null || echo "www-data:www-data")
    local original_dir_owner=$(stat -c %U:%G "$dir" 2>/dev/null || echo "www-data:www-data")
    
    # 【修正】同時調整「目錄」和「檔案」的擁有者給當前用戶
    sudo chown $USER:$USER "$dir" 2>/dev/null || true
    sudo chown $USER:$USER "$file" 2>/dev/null || true
    sudo chmod 644 "$file" 2>/dev/null || true
    
    # 使用 awk 修改內容 (現在可以安全建立暫存檔了)
    if grep -q "^${key}=" "$file" 2>/dev/null; then
        local temp_file="${file}.tmp"
        awk -v key="$key" -v value="$value" '
            BEGIN { found=0 }
            $0 ~ "^" key "=" { print key "=" value; found=1; next }
            { print }
        ' "$file" > "$temp_file"
        mv "$temp_file" "$file"
    else
        echo "${key}=${value}" >> "$file"
    fi
    
    # 【修正】恢復權限
    sudo chown "$original_dir_owner" "$dir" 2>/dev/null || true
    sudo chown "$original_file_owner" "$file" 2>/dev/null || true
    sudo chmod "$original_file_perms" "$file" 2>/dev/null || true
    
    return 0
}

# ===== 權限設置 =====
set_secure_permissions() {
    local project_dir="${1:-$PROJECT_DIR}"
    
    log_step "設置生產環境安全權限..."
    
    # 1. 設置基本擁有者權限 (統一設為 www-data)
    sudo chown -R www-data:www-data "$project_dir"
    
    # 2. 設置目錄權限 (755)
    # 使用 -prune 排除 .git 目錄，避免影響 Git 運作
    find "$project_dir" -name ".git" -prune -o -type d -exec sudo chmod 755 {} \;
    
    # 3. 設置一般檔案權限 (644) - 【關鍵修正】
    # 排除 .git (版本控制)、node_modules (前端套件)、vendor (後端套件)
    # 這些目錄內含有需要執行權限的二進制檔案 (如 git hooks, binaries)
    find "$project_dir" \
        -path "$project_dir/.git" -prune -o \
        -path "$project_dir/frontend/node_modules" -prune -o \
        -path "$project_dir/backend/vendor" -prune -o \
        -type f -exec sudo chmod 644 {} \;

    # 4. 設置 Laravel 必要的寫入權限目錄
    if [ -d "$project_dir/backend/storage" ]; then
        # storage 和 cache 需要 Web Server 可寫入
        sudo chmod -R 755 "$project_dir/backend/storage" "$project_dir/backend/bootstrap/cache"
        sudo chown -R www-data:www-data "$project_dir/backend/storage" "$project_dir/backend/bootstrap/cache"
        
        # 額外建議：設置 SGID 位元，確保新建檔案繼承群組
        sudo find "$project_dir/backend/storage" -type d -exec sudo chmod g+s {} \;
        sudo find "$project_dir/backend/bootstrap/cache" -type d -exec sudo chmod g+s {} \;
    fi
    
    # 5. 嚴格保護敏感配置檔案 (600 - 僅擁有者可讀寫)
    local secure_files=(
        "$project_dir/frontend/.env"
        "$project_dir/backend/.env"
        "$project_dir/db_credentials.txt"
    )
    
    for file in "${secure_files[@]}"; do
        if [ -f "$file" ]; then
            sudo chmod 600 "$file"
        fi
    done
    
    # 6. 確保關鍵執行檔有執行權限 (755)
    if [ -f "$project_dir/backend/artisan" ]; then
        sudo chmod 755 "$project_dir/backend/artisan"
    fi
    
    # 7. 將當前用戶加入 www-data 群組 (以便您能讀取日誌或進行操作)
    sudo usermod -a -G www-data "$USER" 2>/dev/null || true
    
    log_success "安全權限設置完成 (已保留執行檔與 Git 權限)"
}

set_dev_permissions() {
    local project_dir="${1:-$PROJECT_DIR}"
    
    log_step "設置開發環境權限..."
    sudo chown -R $USER:$USER "$project_dir"
    log_success "開發權限設置完成"
}

# ===== PHP-FPM 偵測 =====
detect_php_fpm_handler() {
    local possible_sockets=(
        "/run/php/php8.3-fpm.sock"
        "/var/run/php/php8.3-fpm.sock"
        "/run/php/php8.2-fpm.sock"
        "/var/run/php/php8.2-fpm.sock"
        "/run/php/php8.1-fpm.sock"
        "/var/run/php/php8.1-fpm.sock"
        "/run/php/php8.0-fpm.sock"
        "/var/run/php/php8.0-fpm.sock"
    )
    
    for sock in "${possible_sockets[@]}"; do
        if [ -S "$sock" ]; then
            echo "proxy:unix:${sock}|fcgi://localhost"
            return 0
        fi
    done
    
    # 回退到 TCP
    echo "proxy:fcgi://127.0.0.1:9000"
}

get_php_fpm_version() {
    # 嘗試從運行中的服務偵測
    local version=$(systemctl list-units --type=service --state=running 2>/dev/null | grep -oP 'php\K\d+\.\d+' | head -1)
    
    if [ -z "$version" ]; then
        # 從 CLI 版本獲取
        version=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;' 2>/dev/null || echo "8.3")
    fi
    
    echo "$version"
}

get_php_fpm_service() {
    local version=$(get_php_fpm_version)
    echo "php${version}-fpm"
}

# ===== 系統檢查 =====
check_root_user() {
    if [[ $EUID -eq 0 ]]; then
        log_error "請不要用 root 用戶執行，改用一般用戶！"
        exit 1
    fi
}

check_project_exists() {
    local project_dir="${1:-$PROJECT_DIR}"
    
    if [ ! -d "$project_dir" ]; then
        log_error "專案目錄不存在: $project_dir"
        return 1
    fi
    return 0
}

check_command_exists() {
    local cmd="$1"
    if ! command -v "$cmd" &>/dev/null; then
        return 1
    fi
    return 0
}

# ===== 互動輔助 =====
confirm_action() {
    local message="${1:-確定要繼續嗎?}"
    local default="${2:-N}"
    
    if [ "$default" = "Y" ]; then
        read -p "$message (Y/n): " response
        response=${response:-Y}
    else
        read -p "$message (y/N): " response
        response=${response:-N}
    fi
    
    if [[ "$response" =~ ^[Yy]$ ]]; then
        return 0
    fi
    return 1
}

read_with_default() {
    local prompt="$1"
    local default="$2"
    local result
    
    if [ -n "$default" ]; then
        read -p "$prompt [$default]: " result
        result=${result:-$default}
    else
        read -p "$prompt: " result
    fi
    
    echo "$result"
}

# ===== 錯誤處理 =====
setup_error_trap() {
    set -e
    trap 'log_error "操作失敗於第 $LINENO 行"; exit 1' ERR
}

cleanup_on_exit() {
    # 清理臨時檔案等
    :
}

# ===== 自動偵測 IP =====
get_server_ip() {
    hostname -I 2>/dev/null | awk '{print $1}' || echo "127.0.0.1"
}

# ===== URL 輔助函數 =====
is_ip_address() {
    local input="$1"
    if [[ $input =~ ^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
        return 0
    fi
    return 1
}

get_domain_from_url() {
    local url="$1"
    echo "$url" | sed -E 's|^https?://||' | sed -E 's|/.*$||' | sed -E 's|:.*$||'
}

get_protocol_from_url() {
    local url="$1"
    if [[ "$url" =~ ^https:// ]]; then
        echo "https"
    else
        echo "http"
    fi
}

# ===== 時間戳記 =====
get_timestamp() {
    date +"%Y%m%d_%H%M%S"
}

get_readable_date() {
    date "+%Y-%m-%d %H:%M:%S"
}

log_info "核心模組已載入"
