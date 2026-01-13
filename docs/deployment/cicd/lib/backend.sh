#!/bin/bash
# ===================================================
# 後端部署模組 - lib/backend.sh
# Composer、Laravel 配置、快取管理
# ===================================================

# 防止重複載入
if [ -n "$_BACKEND_SH_LOADED" ]; then
    return 0
fi
_BACKEND_SH_LOADED=1

# 載入核心模組
source "$(dirname "${BASH_SOURCE[0]}")/core.sh"

# ===== Composer 管理 =====
install_composer() {
    log_step "安裝 Composer..."
    
    if command -v composer &>/dev/null; then
        log_info "Composer 已安裝"
        composer --version
        return 0
    fi
    
    log_info "正在下載並安裝 Composer..."
    
    cd /tmp
    curl -sS https://getcomposer.org/installer | php
    
    if [ -f "composer.phar" ]; then
        sudo mv composer.phar /usr/local/bin/composer
        sudo chmod +x /usr/local/bin/composer
        log_success "Composer 安裝成功"
        composer --version
        return 0
    else
        log_error "Composer 安裝失敗"
        return 1
    fi
}

check_composer() {
    if command -v composer &>/dev/null; then
        return 0
    fi
    return 1
}

composer_install() {
    local project_dir="$1"
    local is_production="${2:-true}"
    
    log_step "執行 Composer Install..."
    
    if [ ! -f "$project_dir/backend/composer.json" ]; then
        log_error "找不到 composer.json: $project_dir/backend"
        return 1
    fi
    
    cd "$project_dir/backend"
    
    if [ "$is_production" = "true" ]; then
        log_info "生產模式安裝 (最佳化 autoloader, 排除 dev 套件)"
        composer install --optimize-autoloader --no-dev --no-interaction
    else
        log_info "開發模式安裝"
        composer install --no-interaction
    fi
    
    if [ $? -eq 0 ]; then
        log_success "Composer 套件安裝完成"
        return 0
    else
        log_error "Composer 安裝失敗"
        return 1
    fi
}

composer_update() {
    local project_dir="$1"
    
    log_step "執行 Composer Update..."
    
    cd "$project_dir/backend"
    composer update --no-interaction
    
    if [ $? -eq 0 ]; then
        log_success "Composer 套件更新完成"
        return 0
    else
        log_error "Composer 更新失敗"
        return 1
    fi
}

# ===== Laravel 環境配置 =====
setup_laravel_env() {
    local project_dir="$1"
    local domain="$2"
    local protocol="${3:-http}"
    local db_name="$4"
    local db_user="$5"
    local db_password="$6"
    
    log_step "設置 Laravel 環境..."
    
    local backend_dir="$project_dir/backend"
    local env_file="$backend_dir/.env"
    local env_example="$backend_dir/.env.example"
    
    # 如果 .env 不存在，從範例複製
    if [ ! -f "$env_file" ]; then
        if [ -f "$env_example" ]; then
            cp "$env_example" "$env_file"
            log_info "已從 .env.example 建立 .env"
        else
            log_error "找不到 .env.example"
            return 1
        fi
    fi
    
    # 更新基本配置
    update_env_var "APP_NAME" "LineReservation" "$env_file"
    update_env_var "APP_ENV" "production" "$env_file"
    update_env_var "APP_DEBUG" "false" "$env_file"
    update_env_var "APP_URL" "${protocol}://${domain}" "$env_file"
    
    # 更新資料庫配置
    if [ -n "$db_name" ]; then
        update_env_var "DB_CONNECTION" "mysql" "$env_file"
        update_env_var "DB_HOST" "127.0.0.1" "$env_file"
        update_env_var "DB_PORT" "3306" "$env_file"
        update_env_var "DB_DATABASE" "$db_name" "$env_file"
        update_env_var "DB_USERNAME" "$db_user" "$env_file"
        update_env_var "DB_PASSWORD" "$db_password" "$env_file"
    fi
    
    # 強制將 Cache 和 Session 設為 file，避免因為 migration 尚未執行導致報錯
    log_info "設定 Cache/Session 為 file 模式以確保安裝順利..."
    update_env_var "CACHE_DRIVER" "file" "$env_file"
    update_env_var "SESSION_DRIVER" "file" "$env_file"
    update_env_var "CACHE_STORE" "file" "$env_file"
    
    log_success "Laravel 環境配置完成"
}

enable_database_cache() {
    local project_dir="$1"
    local env_file="$project_dir/backend/.env"
    
    log_step "啟用資料庫快取..."
    update_env_var "CACHE_DRIVER" "database" "$env_file"
    log_success "已切換至資料庫快取"
}

configure_laravel_for_unified() {
    local project_dir="$1"
    local domain="$2"
    local protocol="${3:-http}"
    
    log_step "配置 Laravel 統一模式 (前後端同域名)..."
    
    local env_file="$project_dir/backend/.env"
    
    if [ ! -f "$env_file" ]; then
        log_error "找不到 .env 檔案"
        return 1
    fi
    
    # 統一模式 - 前後端相同域名
    update_env_var "FRONTEND_URL" "${protocol}://${domain}" "$env_file"
    update_env_var "CORS_ALLOWED_ORIGINS" "${protocol}://${domain}" "$env_file"
    update_env_var "SANCTUM_STATEFUL_DOMAINS" "${domain}" "$env_file"
    
    # Cookie 設定 (同域名)
    update_env_var "SESSION_DOMAIN" "${domain}" "$env_file"
    update_env_var "SESSION_SAME_SITE" "lax" "$env_file"
    
    if [ "$protocol" = "https" ]; then
        update_env_var "SESSION_SECURE_COOKIE" "true" "$env_file"
    else
        update_env_var "SESSION_SECURE_COOKIE" "false" "$env_file"
    fi
    
    log_success "統一模式配置完成"
}

configure_laravel_for_api_only() {
    local project_dir="$1"
    local backend_domain="$2"
    local frontend_domain="$3"
    local backend_protocol="${4:-https}"
    local frontend_protocol="${5:-https}"
    
    log_step "配置 Laravel API 模式 (Cloudflare Pages 前端)..."
    
    local env_file="$project_dir/backend/.env"
    
    if [ ! -f "$env_file" ]; then
        log_error "找不到 .env 檔案"
        return 1
    fi
    
    # 提取 frontend 域名 (不含協議)
    local frontend_host="${frontend_domain#*://}"
    
    # API 模式配置
    update_env_var "APP_URL" "${backend_protocol}://${backend_domain}" "$env_file"
    update_env_var "FRONTEND_URL" "${frontend_protocol}://${frontend_host}" "$env_file"
    update_env_var "CORS_ALLOWED_ORIGINS" "${frontend_protocol}://${frontend_host}" "$env_file"
    update_env_var "SANCTUM_STATEFUL_DOMAINS" "${frontend_host},${backend_domain}" "$env_file"
    
    # 跨域 Cookie 設定
    update_env_var "SESSION_DOMAIN" "" "$env_file"
    update_env_var "SESSION_SAME_SITE" "none" "$env_file"
    update_env_var "SESSION_SECURE_COOKIE" "true" "$env_file"  # 跨域必須 HTTPS
    
    log_success "API 模式配置完成"
    echo ""
    echo "📋 跨域配置摘要:"
    echo "   後端 API: ${backend_protocol}://${backend_domain}"
    echo "   前端網址: ${frontend_protocol}://${frontend_host}"
    echo "   CORS: ${frontend_protocol}://${frontend_host}"
    echo "   Sanctum: ${frontend_host},${backend_domain}"
}

generate_app_key() {
    local project_dir="$1"
    
    log_step "生成 Laravel APP_KEY..."
    
    cd "$project_dir/backend"
    
    # 檢查是否已有有效的 key（不只是空行）
    local current_key=$(grep "^APP_KEY=" .env 2>/dev/null | cut -d= -f2 | tr -d ' ')
    
    if [ -n "$current_key" ] && [ "$current_key" != "base64:" ]; then
        log_info "APP_KEY 已存在: ${current_key:0:20}..."
        return 0
    fi
    
    log_info "生成新的 APP_KEY..."
    php artisan key:generate --force
    
    if [ $? -eq 0 ]; then
        log_success "APP_KEY 生成成功"
        return 0
    else
        log_error "APP_KEY 生成失敗"
        return 1
    fi
}

# ===== 資料庫遷移 =====
run_migrations() {
    local project_dir="$1"
    local force="${2:-true}"
    
    log_step "執行資料庫遷移..."
    
    cd "$project_dir/backend"
    
    if [ "$force" = "true" ]; then
        php artisan migrate --force
    else
        php artisan migrate
    fi
    
    if [ $? -eq 0 ]; then
        log_success "資料庫遷移完成"
        return 0
    else
        log_error "資料庫遷移失敗"
        return 1
    fi
}

run_seeders() {
    local project_dir="$1"
    
    log_step "執行資料填充..."
    
    cd "$project_dir/backend"
    php artisan db:seed --force
    
    if [ $? -eq 0 ]; then
        log_success "資料填充完成"
        return 0
    else
        log_error "資料填充失敗"
        return 1
    fi
}

# ===== Laravel 快取 =====
clear_all_cache() {
    local project_dir="$1"
    
    log_step "清除所有 Laravel 快取..."
    
    cd "$project_dir/backend"
    
    php artisan config:clear 2>/dev/null
    php artisan route:clear 2>/dev/null
    php artisan view:clear 2>/dev/null
    php artisan cache:clear 2>/dev/null
    php artisan event:clear 2>/dev/null
    
    log_success "所有快取已清除"
}

clear_config_cache() {
    local project_dir="$1"
    
    log_step "重建配置快取..."
    
    cd "$project_dir/backend"
    php artisan config:clear
    php artisan config:cache
    
    log_success "配置快取已重建"
}

clear_route_cache() {
    local project_dir="$1"
    
    log_step "重建路由快取..."
    
    cd "$project_dir/backend"
    php artisan route:clear
    php artisan route:cache
    
    log_success "路由快取已重建"
}

clear_view_cache() {
    local project_dir="$1"
    
    log_step "清除視圖快取..."
    
    cd "$project_dir/backend"
    php artisan view:clear
    php artisan view:cache
    
    log_success "視圖快取已重建"
}

clear_app_cache() {
    local project_dir="$1"
    
    log_step "清除應用快取..."
    
    cd "$project_dir/backend"
    php artisan cache:clear
    
    log_success "應用快取已清除"
}

rebuild_cache() {
    local project_dir="$1"
    
    log_step "重建所有快取..."
    
    cd "$project_dir/backend"
    
    # 修正：使用 sudo -u www-data 執行，確保能讀取 .env 並寫入 storage
    sudo -u www-data php artisan optimize:clear 2>/dev/null || {
        sudo -u www-data php artisan config:clear
        sudo -u www-data php artisan route:clear
        sudo -u www-data php artisan view:clear
        sudo -u www-data php artisan cache:clear
    }
    
    # 重建快取
    sudo -u www-data php artisan optimize 2>/dev/null || {
        sudo -u www-data php artisan config:cache
        sudo -u www-data php artisan route:cache
        sudo -u www-data php artisan view:cache
    }
    
    log_success "快取重建完成"
}

# ===== Storage 連結 =====
create_storage_link() {
    local project_dir="$1"
    
    log_step "建立 Storage 連結..."
    
    cd "$project_dir/backend"
    
    # 移除舊連結
    if [ -L "$project_dir/backend/public/storage" ]; then
        rm -f "$project_dir/backend/public/storage"
    fi
    
    sudo php artisan storage:link
    
    if [ $? -eq 0 ]; then
        log_success "Storage 連結建立成功"
        return 0
    else
        log_error "Storage 連結建立失敗"
        return 1
    fi
}

# ===== 權限設置 =====
set_backend_permissions() {
    local project_dir="$1"
    local web_user="${2:-www-data}"
    
    log_step "設置後端目錄權限..."
    
    local backend_dir="$project_dir/backend"
    
    # Storage 和 Cache 需要可寫入
    sudo chown -R "$web_user:$web_user" "$backend_dir/storage" 2>/dev/null
    sudo chmod -R 775 "$backend_dir/storage" 2>/dev/null
    
    sudo chown -R "$web_user:$web_user" "$backend_dir/bootstrap/cache" 2>/dev/null
    sudo chmod -R 775 "$backend_dir/bootstrap/cache" 2>/dev/null
    
    # .env 檔案保護
    if [ -f "$backend_dir/.env" ]; then
        sudo chmod 640 "$backend_dir/.env"
        sudo chown "$web_user:$web_user" "$backend_dir/.env"
    fi
    
    log_success "後端權限設置完成"
}

# ===== 健康檢查 =====
check_backend_health() {
    local project_dir="$1"
    
    log_step "後端健康檢查..."
    
    cd "$project_dir/backend"
    
    # 檢查 PHP
    if ! command -v php &>/dev/null; then
        log_error "PHP 未安裝"
        return 1
    fi
    
    # 檢查 Composer 套件
    if [ ! -d "vendor" ]; then
        log_error "Composer 套件未安裝"
        return 1
    fi
    
    # 檢查 .env
    if [ ! -f ".env" ]; then
        log_error ".env 檔案不存在"
        return 1
    fi
    
    # 檢查 APP_KEY
    local app_key=$(grep "^APP_KEY=" .env | cut -d= -f2)
    if [ -z "$app_key" ]; then
        log_error "APP_KEY 未設置"
        return 1
    fi
    
    log_success "後端健康檢查通過"
    return 0
}

test_database_connection() {
    local project_dir="$1"
    
    log_step "測試資料庫連線..."
    
    cd "$project_dir/backend"
    
    php artisan tinker --execute="DB::connection()->getPdo();" 2>/dev/null
    
    if [ $? -eq 0 ]; then
        log_success "資料庫連線正常"
        return 0
    else
        log_error "資料庫連線失敗"
        return 1
    fi
}

# ===== 完整後端部署 =====
deploy_backend() {
    local project_dir="$1"
    local domain="$2"
    local protocol="${3:-http}"
    local deployment_mode="${4:-unified}"
    local frontend_domain="$5"
    
    log_header "部署後端"
    
    # Composer 安裝
    if ! composer_install "$project_dir" "true"; then
        return 1
    fi
    
    # 設置環境
    if [ "$deployment_mode" = "api_only" ] && [ -n "$frontend_domain" ]; then
        configure_laravel_for_api_only "$project_dir" "$domain" "$frontend_domain" "$protocol" "https"
    else
        configure_laravel_for_unified "$project_dir" "$domain" "$protocol"
    fi
    
    # 生成 APP_KEY
    generate_app_key "$project_dir"
    
    # 執行遷移
    run_migrations "$project_dir" "true"
    
    # 建立 Storage 連結
    create_storage_link "$project_dir"
    
    # 設置權限
    set_backend_permissions "$project_dir"
    
    # 重建快取
    rebuild_cache "$project_dir"
    
    log_success "後端部署完成"
}

log_info "後端模組已載入"
