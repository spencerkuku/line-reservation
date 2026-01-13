#!/bin/bash
# ===================================================
# 全域配置檔案 - config.sh
# LINE Reservation 部署系統
# ===================================================

# ===== 專案路徑配置 =====
export USER_HOME=$(eval echo "~$USER")
export PROJECT_DIR="${PROJECT_DIR:-/var/www/line-reservation}"
export BACKUP_BASE_DIR="$USER_HOME/line-reservation-backups"
export SCRIPTS_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
export LIB_DIR="$SCRIPTS_DIR/lib"

# ===== 備份目錄結構 =====
export DB_BACKUP_DIR="$BACKUP_BASE_DIR/database"
export PROJECT_BACKUP_DIR="$BACKUP_BASE_DIR/project-backups"
export LOG_DIR="$BACKUP_BASE_DIR/logs"
export BACKUP_SCRIPTS_DIR="$BACKUP_BASE_DIR/scripts"

# ===== 部署模式配置 =====
# "unified"   = 前後端同伺服器 (傳統模式)
# "api_only"  = 純後端 API 模式 (Headless Mode - 前端使用 Cloudflare Pages)
export DEPLOYMENT_MODE="${DEPLOYMENT_MODE:-unified}"

# ===== Cloudflare Pages 配置 (API Only 模式專用) =====
# 填入你的 Cloudflare Pages 前端域名，例如: myapp.pages.dev
export CLOUDFLARE_FRONTEND_DOMAIN="${CLOUDFLARE_FRONTEND_DOMAIN:-}"
export CLOUDFLARE_FRONTEND_PROTOCOL="${CLOUDFLARE_FRONTEND_PROTOCOL:-https}"

# ===== 後端 API 配置 =====
export BACKEND_DOMAIN="${BACKEND_DOMAIN:-}"
export BACKEND_PROTOCOL="${BACKEND_PROTOCOL:-https}"
export USE_SSL="${USE_SSL:-false}"

# ===== 資料庫配置 =====
export DB_NAME="${DB_NAME:-line_reservation}"
export DB_USER="${DB_USER:-line_user}"
export DB_HOST="${DB_HOST:-127.0.0.1}"
export DB_PORT="${DB_PORT:-3306}"

# ===== Git 配置 =====
export GIT_REPO_URL="${GIT_REPO_URL:-https://github.com/spencerkuku/line-reservation.git}"
export GIT_BRANCH="${GIT_BRANCH:-main}"

# ===== 部署源配置 =====
# "git"     = 從 Git 倉庫 clone/pull (開發用)
# "release" = 從 GitHub Release 下載 (生產用)
export DEPLOY_SOURCE="${DEPLOY_SOURCE:-release}"
export GITHUB_RELEASE_TAG="${GITHUB_RELEASE_TAG:-latest}"
export DEPLOY_TARGET_DIR="${DEPLOY_TARGET_DIR:-$USER_HOME/line-reservation}"

# ===== 備份配置 =====
export BACKUP_RETENTION_DAYS="${BACKUP_RETENTION_DAYS:-30}"
export PROJECT_BACKUP_RETENTION_DAYS="${PROJECT_BACKUP_RETENTION_DAYS:-14}"

# ===== 憑證檔案路徑 =====
export CREDENTIALS_FILE="$USER_HOME/.line-reservation-credentials"

# ===== 持久化配置檔路徑 =====
export CONFIG_STORE="$USER_HOME/.line-reservation-config"

# ===== 載入持久化配置 =====
load_saved_config() {
    if [ -f "$CONFIG_STORE" ]; then
        # 安全載入配置檔案
        while IFS='=' read -r key value; do
            # 跳過註解和空行
            [[ "$key" =~ ^#.*$ ]] && continue
            [[ -z "$key" ]] && continue
            
            # 移除值的引號
            value=$(echo "$value" | sed 's/^["'\'']*//;s/["'\'']*$//')
            
            # 設置環境變數
            case "$key" in
                DEPLOYMENT_MODE) export DEPLOYMENT_MODE="$value" ;;
                CLOUDFLARE_FRONTEND_DOMAIN) export CLOUDFLARE_FRONTEND_DOMAIN="$value" ;;
                CLOUDFLARE_FRONTEND_PROTOCOL) export CLOUDFLARE_FRONTEND_PROTOCOL="$value" ;;
                BACKEND_DOMAIN) export BACKEND_DOMAIN="$value" ;;
                BACKEND_PROTOCOL) export BACKEND_PROTOCOL="$value" ;;
                USE_SSL) export USE_SSL="$value" ;;
                DEPLOY_SOURCE) export DEPLOY_SOURCE="$value" ;;
                GITHUB_RELEASE_TAG) export GITHUB_RELEASE_TAG="$value" ;;
                DEPLOY_TARGET_DIR) export DEPLOY_TARGET_DIR="$value" ;;
                GIT_REPO_URL) export GIT_REPO_URL="$value" ;;
                GIT_BRANCH) export GIT_BRANCH="$value" ;;
            esac
        done < "$CONFIG_STORE"
    fi
}

# ===== 保存單一配置項 =====
save_config_item() {
    local key="$1"
    local value="$2"
    
    # 確保配置檔案存在
    touch "$CONFIG_STORE" 2>/dev/null || return 1
    chmod 600 "$CONFIG_STORE" 2>/dev/null || true
    
    # 如果 key 已存在，更新它；否則追加
    if grep -q "^${key}=" "$CONFIG_STORE" 2>/dev/null; then
        sed -i "s|^${key}=.*|${key}=${value}|" "$CONFIG_STORE"
    else
        echo "${key}=${value}" >> "$CONFIG_STORE"
    fi
}

# ===== 讀取單一配置項 =====
read_config_item() {
    local key="$1"
    local default="${2:-}"
    
    if [ -f "$CONFIG_STORE" ]; then
        local value=$(grep "^${key}=" "$CONFIG_STORE" 2>/dev/null | cut -d'=' -f2- | sed 's/^["'\'']*//;s/["'\'']*$//')
        if [ -n "$value" ]; then
            echo "$value"
            return 0
        fi
    fi
    echo "$default"
}

# ===== 保存所有當前配置 =====
save_all_config() {
    cat > "$CONFIG_STORE" <<EOF
# LINE Reservation 配置檔案
# 自動生成於 $(date)

DEPLOYMENT_MODE=${DEPLOYMENT_MODE}
CLOUDFLARE_FRONTEND_DOMAIN=${CLOUDFLARE_FRONTEND_DOMAIN}
CLOUDFLARE_FRONTEND_PROTOCOL=${CLOUDFLARE_FRONTEND_PROTOCOL}
BACKEND_DOMAIN=${BACKEND_DOMAIN}
BACKEND_PROTOCOL=${BACKEND_PROTOCOL}
USE_SSL=${USE_SSL}
DEPLOY_SOURCE=${DEPLOY_SOURCE}
GITHUB_RELEASE_TAG=${GITHUB_RELEASE_TAG}
DEPLOY_TARGET_DIR=${DEPLOY_TARGET_DIR}
GIT_REPO_URL=${GIT_REPO_URL}
GIT_BRANCH=${GIT_BRANCH}
EOF
    chmod 600 "$CONFIG_STORE" 2>/dev/null || true
}

# ===== 顯示當前配置 =====
show_current_config() {
    echo ""
    echo "📋 當前配置:"
    echo "  部署模式: $DEPLOYMENT_MODE"
    echo "  後端域名: ${BACKEND_DOMAIN:-未設定}"
    echo "  後端協議: $BACKEND_PROTOCOL"
    echo "  使用 SSL: $USE_SSL"
    if [ "$DEPLOYMENT_MODE" = "api_only" ]; then
        echo "  Cloudflare 前端: ${CLOUDFLARE_FRONTEND_DOMAIN:-未設定}"
        echo "  前端協議: $CLOUDFLARE_FRONTEND_PROTOCOL"
    fi
}

# 自動載入已保存的配置
load_saved_config
