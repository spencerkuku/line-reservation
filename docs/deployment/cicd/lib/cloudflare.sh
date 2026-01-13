#!/bin/bash
# ===================================================
# 🌟 Cloudflare Pages 整合模組 - lib/cloudflare.sh 🌟
# 純後端 API 模式 (Headless) 的核心模組
# 處理 Cloudflare Pages 前端與自架後端的整合配置
# ===================================================

# 防止重複載入
if [ -n "$_CLOUDFLARE_SH_LOADED" ]; then
    return 0
fi
_CLOUDFLARE_SH_LOADED=1

# 載入依賴模組
source "$(dirname "${BASH_SOURCE[0]}")/core.sh"
source "$(dirname "${BASH_SOURCE[0]}")/backend.sh"
source "$(dirname "${BASH_SOURCE[0]}")/apache.sh"

# ===== 主配置函數 =====
configure_cloudflare_integration() {
    local project_dir="${1:-$PROJECT_DIR}"
    
    log_header "🌟 Cloudflare Pages 整合配置"
    
    echo ""
    echo "此模式將設定純後端 API (Headless Mode)"
    echo "前端將由 Cloudflare Pages 託管"
    echo ""
    
    # 1. 取得 Cloudflare Pages 前端域名
    local frontend_domain=$(prompt_cloudflare_domain)
    if [ -z "$frontend_domain" ]; then
        log_error "未輸入前端域名"
        return 1
    fi
    
    # 2. 確認後端 API 域名
    local backend_domain="${BACKEND_DOMAIN:-}"
    if [ -z "$backend_domain" ]; then
        backend_domain=$(read_with_default "請輸入後端 API 域名" "api.example.com")
    fi
    
    # 3. 確認協議
    local backend_protocol="https"
    local frontend_protocol="https"  # Cloudflare Pages 預設 HTTPS
    
    echo ""
    echo "📋 配置確認:"
    echo "   前端網址: ${frontend_protocol}://${frontend_domain}"
    echo "   後端 API: ${backend_protocol}://${backend_domain}"
    echo ""
    
    if ! confirm_action "確認以上配置?"; then
        log_info "已取消"
        return 1
    fi
    
    # 4. 更新 Laravel 配置
    log_step "配置 Laravel..."
    configure_laravel_cors "$project_dir" "$frontend_domain" "$frontend_protocol"
    configure_laravel_sanctum "$project_dir" "$frontend_domain" "$backend_domain"
    configure_laravel_session "$project_dir"
    configure_laravel_frontend_url "$project_dir" "$frontend_domain" "$frontend_protocol"
    
    # 5. 更新 Apache 配置
    log_step "配置 Apache..."
    switch_to_api_mode "$backend_domain" "${frontend_protocol}://${frontend_domain}" "$project_dir"
    
    # 6. 生成 Cloudflare 環境變數模板
    log_step "生成 Cloudflare Pages 環境變數模板..."
    generate_cloudflare_env_file "$project_dir" "$backend_domain" "$backend_protocol"
    
    # 7. 儲存配置
    save_config_item "DEPLOYMENT_MODE" "api_only"
    save_config_item "CLOUDFLARE_FRONTEND_DOMAIN" "$frontend_domain"
    save_config_item "BACKEND_DOMAIN" "$backend_domain"
    
    # 8. 顯示部署摘要
    show_deployment_summary "$backend_domain" "$frontend_domain"
    
    log_success "🌟 Cloudflare Pages 整合配置完成!"
}

prompt_cloudflare_domain() {
    echo ""
    log_info "請輸入您的 Cloudflare Pages 前端域名"
    echo "  範例:"
    echo "  - myapp.pages.dev (Cloudflare 自動分配)"
    echo "  - app.example.com (自訂域名)"
    echo ""
    
    local domain
    read -p "前端域名: " domain
    
    # 移除可能的協議前綴
    domain="${domain#http://}"
    domain="${domain#https://}"
    domain="${domain%/}"
    
    if validate_cloudflare_domain "$domain"; then
        echo "$domain"
    else
        log_error "無效的域名格式"
        return 1
    fi
}

validate_cloudflare_domain() {
    local domain="$1"
    
    # 基本格式檢查
    if [ -z "$domain" ]; then
        return 1
    fi
    
    # 不能是 IP 地址
    if [[ "$domain" =~ ^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
        log_error "請使用域名，不要使用 IP 地址"
        return 1
    fi
    
    # 基本域名格式檢查
    if [[ "$domain" =~ ^[a-zA-Z0-9][a-zA-Z0-9.-]+[a-zA-Z0-9]$ ]]; then
        return 0
    fi
    
    return 1
}

# ===== Laravel 配置 =====
configure_laravel_cors() {
    local project_dir="$1"
    local frontend_domain="$2"
    local frontend_protocol="${3:-https}"
    
    log_step "配置 CORS..."
    
    local env_file="$project_dir/backend/.env"
    local cors_origin="${frontend_protocol}://${frontend_domain}"
    
    update_env_var "CORS_ALLOWED_ORIGINS" "$cors_origin" "$env_file"
    
    log_success "CORS 已配置: $cors_origin"
}

configure_laravel_sanctum() {
    local project_dir="$1"
    local frontend_domain="$2"
    local backend_domain="${3:-}"
    
    log_step "配置 Sanctum..."
    
    local env_file="$project_dir/backend/.env"
    
    # Sanctum 需要純域名 (不含協議)
    local stateful_domains="$frontend_domain"
    if [ -n "$backend_domain" ]; then
        stateful_domains="${frontend_domain},${backend_domain}"
    fi
    
    update_env_var "SANCTUM_STATEFUL_DOMAINS" "$stateful_domains" "$env_file"
    
    log_success "Sanctum 已配置: $stateful_domains"
}

configure_laravel_session() {
    local project_dir="$1"
    
    log_step "配置跨域 Session..."
    
    local env_file="$project_dir/backend/.env"
    
    # 跨域 Session 設定
    update_env_var "SESSION_DOMAIN" "" "$env_file"
    update_env_var "SESSION_SAME_SITE" "none" "$env_file"
    update_env_var "SESSION_SECURE_COOKIE" "true" "$env_file"
    
    log_success "Session 已配置為跨域模式"
}

configure_laravel_frontend_url() {
    local project_dir="$1"
    local frontend_domain="$2"
    local frontend_protocol="${3:-https}"
    
    log_step "配置 FRONTEND_URL..."
    
    local env_file="$project_dir/backend/.env"
    local frontend_url="${frontend_protocol}://${frontend_domain}"
    
    update_env_var "FRONTEND_URL" "$frontend_url" "$env_file"
    
    log_success "FRONTEND_URL 已配置: $frontend_url"
}

# ===== 環境變數模板生成 =====
generate_cloudflare_env_file() {
    local project_dir="$1"
    local backend_domain="$2"
    local backend_protocol="${3:-https}"
    
    log_step "生成 Cloudflare Pages 環境變數檔案..."
    
    local env_file="$project_dir/frontend/.env.cloudflare"
    local api_url="${backend_protocol}://${backend_domain}/api"
    
    cat > "$env_file" << EOF
# ===================================================
# Cloudflare Pages 環境變數設定
# 生成時間: $(date)
# ===================================================
# 
# 請在 Cloudflare Pages Dashboard 設定以下環境變數:
# 
# 1. 進入 Cloudflare Dashboard
# 2. Workers & Pages > 您的專案
# 3. Settings > Environment variables
# 4. 新增以下變數:
#
# ===================================================
# Production 環境變數:
# ===================================================

VITE_API_BASE_URL=${api_url}

# ===================================================
# 建置設定 (Build configuration):
# ===================================================
# Framework preset: None
# Build command: npm run build
# Build output directory: dist
# Root directory: frontend
#
# ===================================================
# 可選的環境變數:
# ===================================================
# VITE_APP_NAME=LineReservation
# VITE_APP_ENV=production
#
# ===================================================
# Preview 環境 (開發/測試):
# ===================================================
# 可設定不同的 API 端點用於預覽環境
# VITE_API_BASE_URL=https://staging-api.example.com/api
#
EOF
    
    chmod 644 "$env_file"
    
    log_success "已生成: frontend/.env.cloudflare"
    
    show_cloudflare_env_instructions "$env_file" "$api_url"
}

show_cloudflare_env_instructions() {
    local env_file="$1"
    local api_url="$2"
    
    echo ""
    echo "╔══════════════════════════════════════════════════════════╗"
    echo "║     📋 Cloudflare Pages 環境變數設定說明                 ║"
    echo "╠══════════════════════════════════════════════════════════╣"
    echo "║                                                          ║"
    echo "║  請在 Cloudflare Pages Dashboard 設定:                   ║"
    echo "║                                                          ║"
    echo "║  變數名稱: VITE_API_BASE_URL                             ║"
    echo "║  變數值:   $api_url"
    echo "║                                                          ║"
    echo "║  步驟:                                                   ║"
    echo "║  1. 登入 Cloudflare Dashboard                            ║"
    echo "║  2. Workers & Pages → 選擇您的專案                       ║"
    echo "║  3. Settings → Environment variables                     ║"
    echo "║  4. Add variable → 輸入上述變數                          ║"
    echo "║  5. 重新部署專案                                         ║"
    echo "║                                                          ║"
    echo "╚══════════════════════════════════════════════════════════╝"
    echo ""
}

# ===== 配置驗證 =====
verify_cloudflare_config() {
    local project_dir="${1:-$PROJECT_DIR}"
    
    log_step "驗證 Cloudflare 配置..."
    
    local env_file="$project_dir/backend/.env"
    local errors=0
    
    # 檢查 CORS
    local cors=$(grep "^CORS_ALLOWED_ORIGINS=" "$env_file" 2>/dev/null | cut -d= -f2)
    if [ -z "$cors" ]; then
        log_error "CORS_ALLOWED_ORIGINS 未設定"
        ((errors++))
    else
        log_success "CORS: $cors"
    fi
    
    # 檢查 Sanctum
    local sanctum=$(grep "^SANCTUM_STATEFUL_DOMAINS=" "$env_file" 2>/dev/null | cut -d= -f2)
    if [ -z "$sanctum" ]; then
        log_error "SANCTUM_STATEFUL_DOMAINS 未設定"
        ((errors++))
    else
        log_success "Sanctum: $sanctum"
    fi
    
    # 檢查 Session
    local same_site=$(grep "^SESSION_SAME_SITE=" "$env_file" 2>/dev/null | cut -d= -f2)
    if [ "$same_site" != "none" ]; then
        log_warning "SESSION_SAME_SITE 應設為 'none' (當前: $same_site)"
    fi
    
    # 檢查 FRONTEND_URL
    local frontend_url=$(grep "^FRONTEND_URL=" "$env_file" 2>/dev/null | cut -d= -f2)
    if [ -z "$frontend_url" ]; then
        log_error "FRONTEND_URL 未設定"
        ((errors++))
    else
        log_success "FRONTEND_URL: $frontend_url"
    fi
    
    if [ $errors -eq 0 ]; then
        log_success "所有配置驗證通過"
        return 0
    else
        log_error "發現 $errors 個配置問題"
        return 1
    fi
}

test_cors_headers() {
    local backend_url="$1"
    local frontend_origin="${2:-}"
    
    log_step "測試 CORS 標頭..."
    
    if [ -z "$frontend_origin" ]; then
        frontend_origin=$(grep "^CORS_ALLOWED_ORIGINS=" "$PROJECT_DIR/backend/.env" 2>/dev/null | cut -d= -f2)
    fi
    
    local response=$(curl -sI -X OPTIONS \
        -H "Origin: $frontend_origin" \
        -H "Access-Control-Request-Method: POST" \
        "$backend_url/api" 2>/dev/null)
    
    if echo "$response" | grep -qi "access-control-allow-origin"; then
        log_success "CORS 標頭正常"
        echo "$response" | grep -i "access-control"
        return 0
    else
        log_error "CORS 標頭未正確返回"
        log_info "請確認 Apache CORS 配置"
        return 1
    fi
}

# ===== 狀態顯示 =====
show_cloudflare_status() {
    local project_dir="${1:-$PROJECT_DIR}"
    
    log_header "Cloudflare 配置狀態"
    
    local env_file="$project_dir/backend/.env"
    
    echo ""
    echo "📋 當前配置:"
    echo "   部署模式:     ${DEPLOYMENT_MODE:-未設定}"
    echo "   前端域名:     ${CLOUDFLARE_FRONTEND_DOMAIN:-未設定}"
    echo "   後端域名:     ${BACKEND_DOMAIN:-未設定}"
    echo ""
    
    if [ -f "$env_file" ]; then
        echo "📋 Laravel 環境變數:"
        echo "   CORS:        $(grep "^CORS_ALLOWED_ORIGINS=" "$env_file" | cut -d= -f2)"
        echo "   Sanctum:     $(grep "^SANCTUM_STATEFUL_DOMAINS=" "$env_file" | cut -d= -f2)"
        echo "   FRONTEND_URL: $(grep "^FRONTEND_URL=" "$env_file" | cut -d= -f2)"
        echo "   SESSION:     $(grep "^SESSION_SAME_SITE=" "$env_file" | cut -d= -f2)"
    fi
    
    echo ""
    
    # 檢查 Cloudflare 環境模板
    if [ -f "$project_dir/frontend/.env.cloudflare" ]; then
        log_success "Cloudflare Pages 環境模板已生成"
    else
        log_warning "Cloudflare Pages 環境模板未生成"
    fi
}

show_deployment_summary() {
    local backend_domain="$1"
    local frontend_domain="$2"
    
    echo ""
    echo "╔══════════════════════════════════════════════════════════╗"
    echo "║            🚀 部署摘要 (Headless Mode)                   ║"
    echo "╠══════════════════════════════════════════════════════════╣"
    echo "║                                                          ║"
    echo "║  🖥️  前端 (Cloudflare Pages):                            ║"
    echo "║      https://${frontend_domain}"
    echo "║                                                          ║"
    echo "║  ⚙️  後端 API (自架伺服器):                              ║"
    echo "║      https://${backend_domain}/api                       ║"
    echo "║                                                          ║"
    echo "╠══════════════════════════════════════════════════════════╣"
    echo "║                                                          ║"
    echo "║  📝 下一步:                                              ║"
    echo "║  1. 確認 Apache SSL 憑證已設置                           ║"
    echo "║  2. 在 Cloudflare Pages 設定環境變數                     ║"
    echo "║  3. 測試跨域 API 請求                                    ║"
    echo "║                                                          ║"
    echo "╚══════════════════════════════════════════════════════════╝"
    echo ""
}

# ===== 模式切換 =====
switch_to_headless_mode() {
    local backend_domain="$1"
    local frontend_domain="$2"
    local project_dir="${3:-$PROJECT_DIR}"
    
    log_header "切換到 Headless 模式"
    
    if [ -z "$frontend_domain" ]; then
        frontend_domain=$(prompt_cloudflare_domain)
    fi
    
    # 更新配置
    configure_laravel_for_api_only "$project_dir" "$backend_domain" "$frontend_domain" "https" "https"
    
    # 更新 Apache
    switch_to_api_mode "$backend_domain" "https://${frontend_domain}" "$project_dir"
    
    # 儲存配置
    save_config_item "DEPLOYMENT_MODE" "api_only"
    save_config_item "CLOUDFLARE_FRONTEND_DOMAIN" "$frontend_domain"
    
    log_success "已切換到 Headless 模式"
}

switch_back_to_unified_mode() {
    local domain="$1"
    local project_dir="${2:-$PROJECT_DIR}"
    
    log_header "切換到統一模式"
    
    # 更新 Laravel 配置
    configure_laravel_for_unified "$project_dir" "$domain" "https"
    
    # 更新 Apache
    switch_to_unified_mode "$domain" "$project_dir"
    
    # 儲存配置
    save_config_item "DEPLOYMENT_MODE" "unified"
    save_config_item "CLOUDFLARE_FRONTEND_DOMAIN" ""
    
    log_success "已切換到統一模式"
}

# ===== 快速更新 =====
update_cloudflare_domain() {
    local new_domain="$1"
    local project_dir="${2:-$PROJECT_DIR}"
    
    log_step "更新 Cloudflare 前端域名: $new_domain"
    
    if ! validate_cloudflare_domain "$new_domain"; then
        log_error "無效的域名"
        return 1
    fi
    
    # 取得後端域名
    local backend_domain="${BACKEND_DOMAIN:-}"
    if [ -z "$backend_domain" ]; then
        backend_domain=$(grep "^APP_URL=" "$project_dir/backend/.env" | cut -d= -f2 | sed 's|https\?://||')
    fi
    
    # 更新所有配置
    configure_laravel_cors "$project_dir" "$new_domain" "https"
    configure_laravel_sanctum "$project_dir" "$new_domain" "$backend_domain"
    configure_laravel_frontend_url "$project_dir" "$new_domain" "https"
    
    # 更新 Apache CORS
    switch_to_api_mode "$backend_domain" "https://${new_domain}" "$project_dir"
    
    # 儲存配置
    save_config_item "CLOUDFLARE_FRONTEND_DOMAIN" "$new_domain"
    
    # 重建快取
    cd "$project_dir/backend"
    php artisan config:clear 2>/dev/null
    php artisan config:cache 2>/dev/null
    
    log_success "前端域名已更新為: $new_domain"
}

update_backend_domain() {
    local new_domain="$1"
    local project_dir="${2:-$PROJECT_DIR}"
    
    log_step "更新後端 API 域名: $new_domain"
    
    local env_file="$project_dir/backend/.env"
    local protocol="https"
    
    # 更新 APP_URL
    update_env_var "APP_URL" "${protocol}://${new_domain}" "$env_file"
    
    # 更新 Sanctum
    local frontend_domain="${CLOUDFLARE_FRONTEND_DOMAIN:-}"
    if [ -n "$frontend_domain" ]; then
        configure_laravel_sanctum "$project_dir" "$frontend_domain" "$new_domain"
    fi
    
    # 更新 Apache
    if [ "$DEPLOYMENT_MODE" = "api_only" ]; then
        switch_to_api_mode "$new_domain" "https://${frontend_domain}" "$project_dir"
    else
        switch_to_unified_mode "$new_domain" "$project_dir"
    fi
    
    # 儲存配置
    save_config_item "BACKEND_DOMAIN" "$new_domain"
    
    log_success "後端域名已更新為: $new_domain"
}

# ===== Cloudflare 選單 =====
show_cloudflare_menu() {
    log_header "Cloudflare Pages 整合"
    
    echo ""
    echo "1) 🌟 配置 Cloudflare Pages 整合"
    echo "2) 📋 查看當前配置"
    echo "3) ✅ 驗證配置"
    echo "4) 🔄 更新前端域名"
    echo "5) 🔄 更新後端域名"
    echo "6) 🔀 切換到統一模式"
    echo "7) 🧪 測試 CORS 標頭"
    echo "q) 返回"
    echo ""
    
    read -p "請選擇 [1-7/q]: " choice
    
    case "$choice" in
        1) configure_cloudflare_integration ;;
        2) show_cloudflare_status ;;
        3) verify_cloudflare_config ;;
        4)
            local new_domain=$(prompt_cloudflare_domain)
            if [ -n "$new_domain" ]; then
                update_cloudflare_domain "$new_domain"
            fi
            ;;
        5)
            local new_backend=$(read_with_default "請輸入新的後端域名" "$BACKEND_DOMAIN")
            update_backend_domain "$new_backend"
            ;;
        6)
            local domain=$(read_with_default "請輸入統一域名" "$BACKEND_DOMAIN")
            switch_back_to_unified_mode "$domain"
            ;;
        7)
            local api_url="https://${BACKEND_DOMAIN}"
            test_cors_headers "$api_url"
            ;;
        q) return 0 ;;
        *) log_error "無效選項" ;;
    esac
}

log_info "Cloudflare 模組已載入"
