#!/bin/bash
# ===================================================
# SSL 憑證管理模組 - lib/ssl.sh
# Let's Encrypt 憑證獲取、更新、切換
# ===================================================

# 防止重複載入
if [ -n "$_SSL_SH_LOADED" ]; then
    return 0
fi
_SSL_SH_LOADED=1

# 載入核心模組
source "$(dirname "${BASH_SOURCE[0]}")/core.sh"

# ===== SSL 憑證獲取 =====
setup_ssl_certificate() {
    local domain="$1"
    local email="${2:-admin@$domain}"
    
    log_step "設置 SSL 憑證..."
    
    # 前置檢查
    if ! check_ssl_prerequisites "$domain"; then
        return 1
    fi
    
    # 確保 Apache 配置正確並運行
    sudo systemctl reload apache2
    
    # 檢查 Apache 狀態
    if ! sudo systemctl is-active --quiet apache2; then
        log_error "Apache 未正常運行"
        return 1
    fi
    
    # 測試配置
    if ! sudo apachectl configtest; then
        log_error "Apache 配置有語法錯誤"
        return 1
    fi
    
    log_info "正在獲取 SSL 憑證，這可能需要幾分鐘..."
    
    # 獲取憑證
    if sudo certbot --apache -d "$domain" --non-interactive --agree-tos --email "$email" --redirect; then
        log_success "SSL 憑證設置成功！"
        
        # 驗證憑證
        if verify_ssl_files "$domain"; then
            log_success "憑證檔案驗證通過"
        fi
        
        # 設置自動更新
        setup_ssl_auto_renew
        
        return 0
    else
        log_error "SSL 憑證設置失敗"
        echo ""
        log_info "常見問題排除："
        echo "  1. 確認域名 DNS 已正確指向此伺服器"
        echo "  2. 確認防火牆已開放 80 和 443 端口"
        echo "  3. 手動執行: sudo certbot --apache -d $domain"
        return 1
    fi
}

check_ssl_prerequisites() {
    local domain="$1"
    
    # 檢查是否為 IP 地址
    if is_ip_address "$domain"; then
        log_error "IP 地址無法使用 SSL 憑證，請使用域名"
        return 1
    fi
    
    # 檢查 certbot 是否安裝
    if ! command -v certbot &>/dev/null; then
        log_error "Certbot 未安裝"
        log_info "請執行: sudo apt install certbot python3-certbot-apache"
        return 1
    fi
    
    log_success "SSL 前置檢查通過"
    return 0
}

is_ip_address() {
    local input="$1"
    if [[ $input =~ ^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
        return 0
    fi
    return 1
}

# ===== 憑證管理 =====
renew_ssl_certificate() {
    log_step "更新 SSL 憑證..."
    
    if sudo certbot renew --quiet; then
        log_success "憑證更新完成"
        sudo systemctl reload apache2
        return 0
    else
        log_error "憑證更新失敗"
        return 1
    fi
}

setup_ssl_auto_renew() {
    log_step "設置憑證自動更新..."
    
    # 檢查是否已存在
    if crontab -l 2>/dev/null | grep -q "certbot renew"; then
        log_info "自動更新排程已存在"
        return 0
    fi
    
    # 每天中午 12 點檢查更新
    (crontab -l 2>/dev/null; echo "0 12 * * * /usr/bin/certbot renew --quiet") | crontab -
    
    log_success "已設置憑證自動更新"
}

check_ssl_expiry() {
    local domain="$1"
    
    log_step "檢查憑證到期日..."
    
    local cert_file="/etc/letsencrypt/live/$domain/fullchain.pem"
    
    if [ ! -f "$cert_file" ]; then
        log_error "找不到憑證檔案: $cert_file"
        return 1
    fi
    
    local expiry_date=$(sudo openssl x509 -enddate -noout -in "$cert_file" 2>/dev/null | cut -d= -f2)
    
    if [ -n "$expiry_date" ]; then
        echo ""
        echo "📜 憑證資訊:"
        echo "   域名: $domain"
        echo "   到期日: $expiry_date"
        
        # 計算剩餘天數
        local expiry_epoch=$(date -d "$expiry_date" +%s 2>/dev/null)
        local now_epoch=$(date +%s)
        local days_left=$(( (expiry_epoch - now_epoch) / 86400 ))
        
        echo "   剩餘天數: $days_left 天"
        
        if [ "$days_left" -lt 30 ]; then
            log_warning "憑證將在 $days_left 天後到期，建議盡快更新"
        fi
    fi
}

# ===== 憑證驗證 =====
verify_ssl_files() {
    local domain="$1"
    
    local cert_file="/etc/letsencrypt/live/$domain/fullchain.pem"
    local key_file="/etc/letsencrypt/live/$domain/privkey.pem"
    
    if [ -f "$cert_file" ] && [ -f "$key_file" ]; then
        echo ""
        echo "📜 憑證檔案:"
        echo "   憑證: $cert_file"
        echo "   私鑰: $key_file"
        return 0
    else
        log_error "憑證檔案不完整"
        return 1
    fi
}

test_ssl_connection() {
    local domain="$1"
    
    log_step "測試 SSL 連線..."
    
    if curl -sI "https://$domain" &>/dev/null; then
        log_success "HTTPS 連線正常"
        return 0
    else
        log_error "HTTPS 連線失敗"
        return 1
    fi
}

# ===== SSL 切換 =====
enable_ssl() {
    local domain="$1"
    local project_dir="${2:-$PROJECT_DIR}"
    
    log_step "啟用 SSL..."
    
    # 檢查是否為 IP
    if is_ip_address "$domain"; then
        log_error "IP 地址無法使用 SSL"
        return 1
    fi
    
    # 更新環境變數
    update_env_for_ssl "$domain" "true" "$project_dir"
    
    # 設置憑證
    setup_ssl_certificate "$domain"
    
    return $?
}

disable_ssl() {
    local domain="$1"
    local project_dir="${2:-$PROJECT_DIR}"
    
    log_step "停用 SSL..."
    
    # 更新環境變數
    update_env_for_ssl "$domain" "false" "$project_dir"
    
    log_success "SSL 已停用"
}

toggle_ssl() {
    local domain="$1"
    local current_ssl="$2"
    local project_dir="${3:-$PROJECT_DIR}"
    
    if [ "$current_ssl" = "true" ]; then
        if confirm_action "確定要停用 SSL 嗎?"; then
            disable_ssl "$domain" "$project_dir"
            return $?
        fi
    else
        if confirm_action "確定要啟用 SSL 嗎?"; then
            enable_ssl "$domain" "$project_dir"
            return $?
        fi
    fi
    
    log_info "已取消"
    return 1
}

# ===== 環境更新 =====
update_env_for_ssl() {
    local domain="$1"
    local use_ssl="$2"
    local project_dir="${3:-$PROJECT_DIR}"
    
    local protocol="http"
    [ "$use_ssl" = "true" ] && protocol="https"
    
    log_step "更新環境變數 (協議: $protocol)..."
    
    # 更新後端
    if [ -f "$project_dir/backend/.env" ]; then
        update_env_var "APP_URL" "${protocol}://${domain}" "$project_dir/backend/.env"
        update_env_var "FRONTEND_URL" "${protocol}://${domain}" "$project_dir/backend/.env"
        update_env_var "CORS_ALLOWED_ORIGINS" "${protocol}://${domain}" "$project_dir/backend/.env"
        update_env_var "SESSION_SECURE_COOKIE" "$use_ssl" "$project_dir/backend/.env"
    fi
    
    # 更新前端
    if [ -f "$project_dir/frontend/.env" ]; then
        update_env_var "VITE_API_BASE_URL" "${protocol}://${domain}/api" "$project_dir/frontend/.env"
        update_env_var "VITE_APP_BASE_URL" "${protocol}://${domain}" "$project_dir/frontend/.env"
        update_env_var "VITE_APP_URL" "${protocol}://${domain}" "$project_dir/frontend/.env"
    fi
    
    log_success "環境變數已更新"
}

# ===== 互動式 SSL 設置 =====
interactive_ssl_setup() {
    local domain="$1"
    
    log_header "SSL 憑證設置"
    
    if is_ip_address "$domain"; then
        log_error "IP 地址無法使用 SSL 憑證"
        log_info "請使用域名來設置 SSL"
        return 1
    fi
    
    echo ""
    echo "將為 $domain 設置 Let's Encrypt SSL 憑證"
    echo ""
    
    local email=$(read_with_default "請輸入電子郵件 (用於憑證通知)" "admin@$domain")
    
    if confirm_action "確定要設置 SSL 嗎?"; then
        setup_ssl_certificate "$domain" "$email"
    else
        log_info "已取消"
    fi
}

log_info "SSL 模組已載入"
