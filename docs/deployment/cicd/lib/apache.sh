#!/bin/bash
# ===================================================
# Apache 配置模組 - lib/apache.sh
# VirtualHost 配置生成 (統一模式 / 純 API 模式)
# ===================================================

# 防止重複載入
if [ -n "$_APACHE_SH_LOADED" ]; then
    return 0
fi
_APACHE_SH_LOADED=1

# 載入核心模組
source "$(dirname "${BASH_SOURCE[0]}")/core.sh"

# ===== 配置檔路徑 =====
APACHE_SITES_DIR="/etc/apache2/sites-available"
APACHE_UNIFIED_CONF="$APACHE_SITES_DIR/line-reservation.conf"
APACHE_API_CONF="$APACHE_SITES_DIR/line-reservation-api.conf"
APACHE_SSL_CONF="$APACHE_SITES_DIR/line-reservation-le-ssl.conf"

# ===== 統一模式配置 (前後端同伺服器) =====
generate_unified_config() {
    local domain="$1"
    local use_ssl="${2:-false}"
    local php_handler="${3:-$(detect_php_fpm_handler)}"
    local project_dir="${4:-$PROJECT_DIR}"
    
    log_step "生成統一模式 Apache 配置..."
    
    if [ "$use_ssl" = "true" ]; then
        generate_unified_http_redirect "$domain"
        generate_unified_ssl_config "$domain" "$php_handler" "$project_dir"
    else
        generate_unified_http_config "$domain" "$php_handler" "$project_dir"
    fi
    
    log_success "Apache 配置已生成"
}

generate_unified_http_config() {
    local domain="$1"
    local php_handler="$2"
    local project_dir="$3"
    
    sudo tee "$APACHE_UNIFIED_CONF" > /dev/null <<EOF
<VirtualHost *:80>
    ServerName $domain
    DocumentRoot $project_dir/frontend/dist

    # 前端靜態檔案服務
    <Directory $project_dir/frontend/dist>
        AllowOverride All
        Require all granted
        Options FollowSymLinks
        
$(generate_directory_security)
        
        # Vue Router 歷史模式支援
        RewriteEngine On
        RewriteBase /
        RewriteRule ^index\.html\$ - [L]
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteCond %{REQUEST_URI} !^/api/
        RewriteCond %{REQUEST_URI} !^/storage/
        RewriteRule . /index.html [L]
    </Directory>

    # API 路由代理到後端
    RewriteEngine On
    RewriteRule ^/api/(.*)\$ $project_dir/backend/public/index.php [QSA,L]

    <Directory $project_dir/backend/public>
        AllowOverride All
        Require all granted
        Options FollowSymLinks

$(generate_directory_security)

$(generate_php_fpm_handler "$php_handler")
    </Directory>

    # 後端存儲檔案
    Alias /storage $project_dir/backend/storage/app/public
    <Directory $project_dir/backend/storage/app/public>
        AllowOverride None
        Require all granted
        Options FollowSymLinks
        
        # 禁止執行 PHP
        <FilesMatch "\.(php|phtml|php3|php4|php5|phar)\$">
            Require all denied
        </FilesMatch>
    </Directory>

$(generate_security_headers)

    ErrorLog \${APACHE_LOG_DIR}/line-reservation_error.log
    CustomLog \${APACHE_LOG_DIR}/line-reservation_access.log combined
</VirtualHost>
EOF
}

generate_unified_ssl_config() {
    local domain="$1"
    local php_handler="$2"
    local project_dir="$3"
    
    sudo tee "$APACHE_SSL_CONF" > /dev/null <<EOF
<IfModule mod_ssl.c>
<VirtualHost *:443>
    ServerName $domain
    ServerAlias www.$domain
    DocumentRoot $project_dir/frontend/dist

    <Directory $project_dir/frontend/dist>
        AllowOverride All
        Require all granted
        Options FollowSymLinks

$(generate_directory_security)

        RewriteEngine On
        RewriteBase /
        RewriteRule ^index\.html\$ - [L]
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteCond %{REQUEST_URI} !^/api/
        RewriteCond %{REQUEST_URI} !^/storage/
        RewriteRule . /index.html [L]
    </Directory>

    RewriteEngine On
    RewriteRule ^/api/(.*)\$ $project_dir/backend/public/index.php [QSA,L]

    <Directory $project_dir/backend/public>
        AllowOverride All
        Require all granted
        Options FollowSymLinks
        
$(generate_directory_security)

$(generate_php_fpm_handler "$php_handler")
    </Directory>

    Alias /storage $project_dir/backend/storage/app/public
    <Directory $project_dir/backend/storage/app/public>
        AllowOverride None
        Require all granted
        Options FollowSymLinks
        
        <FilesMatch "\.(php|phtml|php3|php4|php5|phar)\$">
            Require all denied
        </FilesMatch>
    </Directory>

$(generate_security_headers)

    ErrorLog \${APACHE_LOG_DIR}/line-reservation_error.log
    CustomLog \${APACHE_LOG_DIR}/line-reservation_access.log combined

    # SSL 配置將由 Certbot 自動添加
</VirtualHost>
</IfModule>
EOF
}

generate_unified_http_redirect() {
    local domain="$1"
    
    sudo tee "$APACHE_UNIFIED_CONF" > /dev/null <<EOF
<VirtualHost *:80>
    ServerName $domain
    # 所有 HTTP 流量跳轉到 HTTPS
    Redirect permanent / https://$domain/
</VirtualHost>
EOF
}

# ===== 純 API 模式配置 (Headless / Cloudflare Pages) =====
generate_api_only_config() {
    local backend_domain="$1"
    local frontend_domain="$2"  # Cloudflare Pages 域名
    local use_ssl="${3:-false}"
    local php_handler="${4:-$(detect_php_fpm_handler)}"
    local project_dir="${5:-$PROJECT_DIR}"
    local frontend_protocol="${6:-https}"
    
    log_step "生成純 API 模式 Apache 配置..."
    
    local frontend_origin="${frontend_protocol}://${frontend_domain}"
    
    if [ "$use_ssl" = "true" ]; then
        generate_api_http_redirect "$backend_domain"
        generate_api_ssl_config "$backend_domain" "$frontend_origin" "$php_handler" "$project_dir"
    else
        generate_api_http_config "$backend_domain" "$frontend_origin" "$php_handler" "$project_dir"
    fi
    
    log_success "純 API 模式配置已生成"
}

generate_api_http_config() {
    local domain="$1"
    local frontend_origin="$2"
    local php_handler="$3"
    local project_dir="$4"
    
    sudo tee "$APACHE_API_CONF" > /dev/null <<EOF
<VirtualHost *:80>
    ServerName $domain
    DocumentRoot $project_dir/backend/public

$(generate_api_cors_headers "$frontend_origin")

    <Directory $project_dir/backend/public>
        AllowOverride All
        Require all granted
        Options FollowSymLinks

$(generate_directory_security)

$(generate_php_fpm_handler "$php_handler")
    </Directory>

    # 後端存儲檔案
    Alias /storage $project_dir/backend/storage/app/public
    <Directory $project_dir/backend/storage/app/public>
        AllowOverride None
        Require all granted
        Options FollowSymLinks
        
        <FilesMatch "\.(php|phtml|php3|php4|php5|phar)\$">
            Require all denied
        </FilesMatch>
    </Directory>

$(generate_security_headers)

    ErrorLog \${APACHE_LOG_DIR}/line-reservation-api_error.log
    CustomLog \${APACHE_LOG_DIR}/line-reservation-api_access.log combined
</VirtualHost>
EOF
}

generate_api_ssl_config() {
    local domain="$1"
    local frontend_origin="$2"
    local php_handler="$3"
    local project_dir="$4"
    
    sudo tee "$APACHE_SITES_DIR/line-reservation-api-ssl.conf" > /dev/null <<EOF
<IfModule mod_ssl.c>
<VirtualHost *:443>
    ServerName $domain
    DocumentRoot $project_dir/backend/public

$(generate_api_cors_headers "$frontend_origin")

    <Directory $project_dir/backend/public>
        AllowOverride All
        Require all granted
        Options FollowSymLinks

$(generate_directory_security)

$(generate_php_fpm_handler "$php_handler")
    </Directory>

    Alias /storage $project_dir/backend/storage/app/public
    <Directory $project_dir/backend/storage/app/public>
        AllowOverride None
        Require all granted
        Options FollowSymLinks
        
        <FilesMatch "\.(php|phtml|php3|php4|php5|phar)\$">
            Require all denied
        </FilesMatch>
    </Directory>

$(generate_security_headers)

    ErrorLog \${APACHE_LOG_DIR}/line-reservation-api_error.log
    CustomLog \${APACHE_LOG_DIR}/line-reservation-api_access.log combined

    # SSL 配置將由 Certbot 自動添加
</VirtualHost>
</IfModule>
EOF
}

generate_api_http_redirect() {
    local domain="$1"
    
    sudo tee "$APACHE_API_CONF" > /dev/null <<EOF
<VirtualHost *:80>
    ServerName $domain
    Redirect permanent / https://$domain/
</VirtualHost>
EOF
}

generate_api_cors_headers() {
    local allowed_origin="$1"
    
    cat <<EOF
    # CORS 配置 - 允許 Cloudflare Pages 前端跨域請求
    <IfModule mod_headers.c>
        # 處理預檢請求
        RewriteEngine On
        RewriteCond %{REQUEST_METHOD} OPTIONS
        RewriteRule ^(.*)\$ \$1 [R=200,L]
        
        Header always set Access-Control-Allow-Origin "$allowed_origin"
        Header always set Access-Control-Allow-Methods "GET, POST, PUT, PATCH, DELETE, OPTIONS"
        Header always set Access-Control-Allow-Headers "Content-Type, Authorization, X-Requested-With, Accept, Origin, X-XSRF-TOKEN"
        Header always set Access-Control-Allow-Credentials "true"
        Header always set Access-Control-Max-Age "86400"
    </IfModule>
EOF
}

# ===== 通用配置元件 =====
generate_php_fpm_handler() {
    local php_handler="$1"
    
    cat <<EOF
        <FilesMatch "\.php\$">
            SetHandler "$php_handler"
        </FilesMatch>
EOF
}

generate_security_headers() {
    cat <<EOF
    # 安全標頭
    <IfModule mod_headers.c>
        Header always set X-Content-Type-Options "nosniff"
        Header always set X-Frame-Options "SAMEORIGIN"
        Header always set Referrer-Policy "strict-origin-when-cross-origin"
        Header always set X-XSS-Protection "1; mode=block"
    </IfModule>
EOF
}

generate_directory_security() {
    cat <<EOF
        # 安全配置 - 禁止訪問敏感文件
        <FilesMatch "^\.(git|env|htaccess|htpasswd)">
            Require all denied
        </FilesMatch>
        
        <FilesMatch "\.(json|lock|md|txt|yml|yaml|xml)\$">
            Require all denied
        </FilesMatch>
EOF
}

# ===== Apache 模組管理 =====
enable_apache_modules() {
    log_step "啟用 Apache 模組..."
    
    local modules=(rewrite ssl headers proxy proxy_fcgi setenvif expires)
    
    for mod in "${modules[@]}"; do
        sudo a2enmod "$mod" 2>/dev/null || true
    done
    
    log_success "Apache 模組已啟用"
}

check_apache_modules() {
    local required_modules=(rewrite ssl headers proxy proxy_fcgi)
    local missing=()
    
    for mod in "${required_modules[@]}"; do
        if ! apache2ctl -M 2>/dev/null | grep -q "${mod}_module"; then
            missing+=("$mod")
        fi
    done
    
    if [ ${#missing[@]} -gt 0 ]; then
        log_warning "缺少 Apache 模組: ${missing[*]}"
        return 1
    fi
    
    log_success "所有必要的 Apache 模組已啟用"
    return 0
}

# ===== 站台管理 =====
enable_site() {
    local conf_name="$1"
    log_step "啟用站台: $conf_name"
    sudo a2ensite "$conf_name" 2>/dev/null || true
}

disable_site() {
    local conf_name="$1"
    log_step "停用站台: $conf_name"
    sudo a2dissite "$conf_name" 2>/dev/null || true
}

reload_apache() {
    log_step "重新載入 Apache..."
    sudo systemctl reload apache2
    log_success "Apache 已重新載入"
}

restart_apache() {
    log_step "重啟 Apache..."
    sudo systemctl restart apache2
    log_success "Apache 已重啟"
}

# ===== 配置驗證 =====
test_apache_config() {
    log_step "測試 Apache 配置..."
    
    if sudo apachectl configtest; then
        log_success "Apache 配置語法正確"
        return 0
    else
        log_error "Apache 配置有語法錯誤"
        return 1
    fi
}

check_apache_status() {
    echo ""
    echo "🌐 Apache 狀態:"
    sudo systemctl status apache2 --no-pager -l 2>/dev/null || echo "Apache 未運行"
}

# ===== 模式切換 =====
switch_to_unified_mode() {
    local domain="$1"
    local use_ssl="${2:-false}"
    
    log_step "切換到統一模式..."
    
    # 停用 API 模式配置
    disable_site "line-reservation-api.conf"
    disable_site "line-reservation-api-ssl.conf"
    
    # 生成並啟用統一模式配置
    generate_unified_config "$domain" "$use_ssl"
    enable_site "line-reservation.conf"
    
    if [ "$use_ssl" = "true" ]; then
        enable_site "line-reservation-le-ssl.conf"
    fi
    
    # 停用預設站台
    disable_site "000-default.conf"
    
    # 測試並重新載入
    if test_apache_config; then
        reload_apache
        log_success "已切換到統一模式"
    else
        log_error "配置有誤，請檢查"
        return 1
    fi
}

switch_to_api_mode() {
    local backend_domain="$1"
    local frontend_domain="$2"
    local use_ssl="${3:-false}"
    local frontend_protocol="${4:-https}"
    
    log_step "切換到純 API 模式..."
    
    # 停用統一模式配置
    disable_site "line-reservation.conf"
    disable_site "line-reservation-le-ssl.conf"
    
    # 生成並啟用 API 模式配置
    generate_api_only_config "$backend_domain" "$frontend_domain" "$use_ssl" "$(detect_php_fpm_handler)" "$PROJECT_DIR" "$frontend_protocol"
    enable_site "line-reservation-api.conf"
    
    if [ "$use_ssl" = "true" ]; then
        enable_site "line-reservation-api-ssl.conf"
    fi
    
    # 停用預設站台
    disable_site "000-default.conf"
    
    # 測試並重新載入
    if test_apache_config; then
        reload_apache
        log_success "已切換到純 API 模式"
    else
        log_error "配置有誤，請檢查"
        return 1
    fi
}

# ===== 全域 Apache 配置 =====
configure_apache_global() {
    local domain="$1"
    
    log_step "配置 Apache 全域設定..."
    
    # 檢查是否已配置
    if grep -q "ServerTokens Prod" /etc/apache2/apache2.conf 2>/dev/null; then
        log_info "全域設定已存在"
        return 0
    fi
    
    sudo tee -a /etc/apache2/apache2.conf > /dev/null <<EOF

# LINE Reservation 安全設定
ServerTokens Prod
ServerSignature Off
ServerName $domain

EOF
    
    log_success "Apache 全域設定完成"
}

log_info "Apache 模組已載入"
