#!/bin/bash
# ===================================================
# 系統套件安裝模組 - lib/system.sh
# 系統套件安裝、服務管理
# ===================================================

# 防止重複載入
if [ -n "$_SYSTEM_SH_LOADED" ]; then
    return 0
fi
_SYSTEM_SH_LOADED=1

# 載入核心模組
source "$(dirname "${BASH_SOURCE[0]}")/core.sh"

# ===== 系統更新 =====
update_system() {
    log_step "更新系統套件..."
    
    sudo apt update
    
    if confirm_action "是否要升級所有套件?"; then
        sudo apt upgrade -y
    fi
    
    log_success "系統更新完成"
}

# ===== Node.js 安裝 =====
install_nodejs() {
    local version="${1:-20}"
    
    log_step "安裝 Node.js $version..."
    
    if command -v node &>/dev/null; then
        local current_version=$(node -v)
        log_info "Node.js 已安裝: $current_version"
        
        if ! confirm_action "是否要重新安裝?"; then
            return 0
        fi
    fi
    
    # 使用 NodeSource
    curl -fsSL "https://deb.nodesource.com/setup_${version}.x" | sudo -E bash -
    sudo apt install -y nodejs
    
    if command -v node &>/dev/null; then
        log_success "Node.js 安裝成功: $(node -v)"
        log_info "NPM 版本: $(npm -v)"
        return 0
    else
        log_error "Node.js 安裝失敗"
        return 1
    fi
}

# ===== PHP 安裝 =====
install_php() {
    local version="${1:-8.3}"
    
    log_step "安裝 PHP $version..."
    
    # 添加 PHP 倉庫
    sudo add-apt-repository -y ppa:ondrej/php
    sudo apt update
    
    # 安裝 PHP 和必要擴展
    local packages=(
        "php${version}-fpm"
        "php${version}-mysql"
        "php${version}-xml"
        "php${version}-curl"
        "php${version}-mbstring"
        "php${version}-zip"
        "php${version}-gd"
        "php${version}-bcmath"
        "php${version}-intl"
        "php${version}-readline"
        "php${version}-cli"
    )
    
    sudo apt install -y "${packages[@]}"
    
    # 啟用 PHP-FPM
    sudo systemctl enable "php${version}-fpm"
    sudo systemctl start "php${version}-fpm"
    
    if command -v php &>/dev/null; then
        log_success "PHP 安裝成功: $(php -v | head -1)"
        return 0
    else
        log_error "PHP 安裝失敗"
        return 1
    fi
}

# ===== Apache 安裝 =====
install_apache() {
    log_step "安裝 Apache..."
    
    sudo apt install -y apache2
    
    # 啟用必要模組
    sudo a2enmod rewrite
    sudo a2enmod ssl
    sudo a2enmod headers
    sudo a2enmod proxy
    sudo a2enmod proxy_fcgi
    
    # 啟用服務
    sudo systemctl enable apache2
    sudo systemctl start apache2
    
    if systemctl is-active --quiet apache2; then
        log_success "Apache 安裝成功"
        return 0
    else
        log_error "Apache 安裝失敗"
        return 1
    fi
}

# ===== MySQL 安裝 =====
install_mysql() {
    log_step "安裝 MySQL..."
    
    sudo apt install -y mysql-server
    
    # 啟用服務
    sudo systemctl enable mysql
    sudo systemctl start mysql
    
    if systemctl is-active --quiet mysql; then
        log_success "MySQL 安裝成功"
        
        if confirm_action "是否要執行安全配置? (mysql_secure_installation)"; then
            sudo mysql_secure_installation
        fi
        
        return 0
    else
        log_error "MySQL 安裝失敗"
        return 1
    fi
}

# ===== Certbot 安裝 =====
install_certbot() {
    log_step "安裝 Certbot..."
    
    sudo apt install -y certbot python3-certbot-apache
    
    if command -v certbot &>/dev/null; then
        log_success "Certbot 安裝成功: $(certbot --version)"
        return 0
    else
        log_error "Certbot 安裝失敗"
        return 1
    fi
}

# ===== 完整安裝 =====
install_all_packages() {
    local use_ssl="${1:-true}"
    local php_version="${2:-8.3}"
    local node_version="${3:-20}"
    
    log_header "安裝所有必要套件"
    
    echo ""
    echo "將安裝以下套件:"
    echo "  - Apache 2"
    echo "  - PHP $php_version + FPM + 擴展"
    echo "  - MySQL Server"
    echo "  - Node.js $node_version"
    echo "  - Composer"
    if [ "$use_ssl" = "true" ]; then
        echo "  - Certbot (SSL)"
    fi
    echo ""
    
    if ! confirm_action "確定要繼續嗎?"; then
        log_info "已取消"
        return 1
    fi
    
    # 更新系統
    sudo apt update
    
    # 安裝基礎工具
    sudo apt install -y curl wget git unzip
    
    # 安裝各個元件
    install_apache
    install_php "$php_version"
    install_mysql
    install_nodejs "$node_version"
    install_composer
    
    if [ "$use_ssl" = "true" ]; then
        install_certbot
    fi
    
    log_success "所有套件安裝完成"
    
    # 顯示版本資訊
    echo ""
    echo "📋 已安裝版本:"
    echo "   Apache: $(apache2 -v 2>/dev/null | head -1)"
    echo "   PHP: $(php -v 2>/dev/null | head -1)"
    echo "   MySQL: $(mysql --version 2>/dev/null)"
    echo "   Node: $(node -v 2>/dev/null)"
    echo "   NPM: $(npm -v 2>/dev/null)"
    echo "   Composer: $(composer --version 2>/dev/null | head -1)"
}

install_packages_for_api_only() {
    local use_ssl="${1:-true}"
    local php_version="${2:-8.3}"
    
    log_header "安裝 API 模式套件 (不含 Node.js)"
    
    echo ""
    echo "將安裝以下套件:"
    echo "  - Apache 2"
    echo "  - PHP $php_version + FPM + 擴展"
    echo "  - MySQL Server"
    echo "  - Composer"
    if [ "$use_ssl" = "true" ]; then
        echo "  - Certbot (SSL)"
    fi
    echo ""
    echo "注意: Node.js 不會安裝 (前端由 Cloudflare Pages 託管)"
    echo ""
    
    if ! confirm_action "確定要繼續嗎?"; then
        log_info "已取消"
        return 1
    fi
    
    # 更新系統
    sudo apt update
    
    # 安裝基礎工具
    sudo apt install -y curl wget git unzip
    
    # 安裝各個元件
    install_apache
    install_php "$php_version"
    install_mysql
    install_composer
    
    if [ "$use_ssl" = "true" ]; then
        install_certbot
    fi
    
    log_success "API 模式套件安裝完成"
}

install_composer() {
    log_step "安裝 Composer..."
    
    if command -v composer &>/dev/null; then
        log_info "Composer 已安裝"
        return 0
    fi
    
    cd /tmp
    curl -sS https://getcomposer.org/installer | php
    sudo mv composer.phar /usr/local/bin/composer
    sudo chmod +x /usr/local/bin/composer
    
    if command -v composer &>/dev/null; then
        log_success "Composer 安裝成功"
        return 0
    else
        log_error "Composer 安裝失敗"
        return 1
    fi
}

# ===== 服務管理 =====
start_service() {
    local service_name="$1"
    
    log_step "啟動 $service_name..."
    
    sudo systemctl start "$service_name"
    
    if systemctl is-active --quiet "$service_name"; then
        log_success "$service_name 已啟動"
        return 0
    else
        log_error "$service_name 啟動失敗"
        return 1
    fi
}

stop_service() {
    local service_name="$1"
    
    log_step "停止 $service_name..."
    
    sudo systemctl stop "$service_name"
    
    if ! systemctl is-active --quiet "$service_name"; then
        log_success "$service_name 已停止"
        return 0
    else
        log_error "$service_name 停止失敗"
        return 1
    fi
}

restart_service() {
    local service_name="$1"
    
    log_step "重啟 $service_name..."
    
    sudo systemctl restart "$service_name"
    
    if systemctl is-active --quiet "$service_name"; then
        log_success "$service_name 已重啟"
        return 0
    else
        log_error "$service_name 重啟失敗"
        return 1
    fi
}

reload_service() {
    local service_name="$1"
    
    log_step "重載 $service_name..."
    
    sudo systemctl reload "$service_name"
    
    log_success "$service_name 已重載"
}

enable_service() {
    local service_name="$1"
    
    log_step "啟用 $service_name 開機自動啟動..."
    
    sudo systemctl enable "$service_name"
    
    log_success "$service_name 已啟用"
}

check_service_status() {
    local service_name="$1"
    
    if systemctl is-active --quiet "$service_name"; then
        echo "✅ $service_name: 運行中"
        return 0
    else
        echo "❌ $service_name: 未運行"
        return 1
    fi
}

# ===== 服務群組操作 =====
restart_web_services() {
    log_step "重啟 Web 服務..."
    
    # 重啟 PHP-FPM (偵測版本)
    for version in 8.3 8.2 8.1 8.0; do
        if systemctl is-enabled --quiet "php${version}-fpm" 2>/dev/null; then
            sudo systemctl restart "php${version}-fpm"
            log_info "已重啟 php${version}-fpm"
            break
        fi
    done
    
    # 重啟 Apache
    sudo systemctl restart apache2
    
    log_success "Web 服務已重啟"
}

check_all_services() {
    log_header "服務狀態檢查"
    
    echo ""
    check_service_status "apache2"
    
    # 檢查 PHP-FPM
    for version in 8.3 8.2 8.1 8.0; do
        if systemctl is-enabled --quiet "php${version}-fpm" 2>/dev/null; then
            check_service_status "php${version}-fpm"
            break
        fi
    done
    
    check_service_status "mysql"
    
    echo ""
}

# ===== 系統資訊 =====
get_system_info() {
    log_header "系統資訊"
    
    echo ""
    echo "📋 系統:"
    echo "   作業系統: $(lsb_release -d 2>/dev/null | cut -f2 || cat /etc/os-release | grep PRETTY_NAME | cut -d'"' -f2)"
    echo "   核心版本: $(uname -r)"
    echo "   主機名稱: $(hostname)"
    echo "   IP 地址: $(hostname -I | awk '{print $1}')"
    echo ""
    
    echo "📋 已安裝版本:"
    echo "   Apache: $(apache2 -v 2>/dev/null | head -1 || echo '未安裝')"
    echo "   PHP: $(php -v 2>/dev/null | head -1 || echo '未安裝')"
    echo "   MySQL: $(mysql --version 2>/dev/null || echo '未安裝')"
    echo "   Node: $(node -v 2>/dev/null || echo '未安裝')"
    echo "   NPM: $(npm -v 2>/dev/null || echo '未安裝')"
    echo "   Composer: $(composer --version 2>/dev/null | head -1 || echo '未安裝')"
    echo ""
}

get_disk_usage() {
    log_header "磁碟使用量"
    
    echo ""
    df -h | grep -E "^/dev|Filesystem"
    echo ""
}

get_memory_usage() {
    log_header "記憶體使用量"
    
    echo ""
    free -h
    echo ""
}

# ===== 日誌查看 =====
view_apache_error_log() {
    local lines="${1:-50}"
    
    log_header "Apache 錯誤日誌 (最後 $lines 行)"
    
    echo ""
    sudo tail -n "$lines" /var/log/apache2/error.log
}

view_apache_access_log() {
    local lines="${1:-30}"
    
    log_header "Apache 訪問日誌 (最後 $lines 行)"
    
    echo ""
    sudo tail -n "$lines" /var/log/apache2/access.log
}

view_laravel_log() {
    local project_dir="${1:-$PROJECT_DIR}"
    local lines="${2:-30}"
    
    local log_file="$project_dir/backend/storage/logs/laravel.log"
    
    log_header "Laravel 日誌 (最後 $lines 行)"
    
    if [ -f "$log_file" ]; then
        echo ""
        tail -n "$lines" "$log_file"
    else
        log_warning "找不到 Laravel 日誌檔案"
    fi
}

view_system_log() {
    local service="${1:-apache2}"
    local lines="${2:-20}"
    
    log_header "$service 系統日誌 (最後 $lines 條)"
    
    echo ""
    sudo journalctl -u "$service" -n "$lines" --no-pager
}

# ===== 日誌選單 =====
show_log_menu() {
    log_header "日誌查看"
    
    echo ""
    echo "1) Apache 錯誤日誌"
    echo "2) Apache 訪問日誌"
    echo "3) Laravel 日誌"
    echo "4) PHP-FPM 日誌"
    echo "5) MySQL 日誌"
    echo "6) 系統資訊"
    echo "7) 磁碟使用量"
    echo "8) 記憶體使用量"
    echo "q) 返回"
    echo ""
    
    read -p "請選擇 [1-8/q]: " choice
    
    case "$choice" in
        1) view_apache_error_log ;;
        2) view_apache_access_log ;;
        3) view_laravel_log ;;
        4) view_system_log "php8.3-fpm" ;;
        5) view_system_log "mysql" ;;
        6) get_system_info ;;
        7) get_disk_usage ;;
        8) get_memory_usage ;;
        q) return 0 ;;
        *) log_error "無效選項" ;;
    esac
}

# ===== 系統選單 =====
show_system_menu() {
    log_header "系統管理"
    
    echo ""
    echo "1) 安裝所有套件 (完整模式)"
    echo "2) 安裝套件 (API Only 模式)"
    echo "3) 檢查服務狀態"
    echo "4) 重啟 Web 服務"
    echo "5) 查看系統資訊"
    echo "6) 查看日誌"
    echo "7) 更新系統"
    echo "q) 返回"
    echo ""
    
    read -p "請選擇 [1-7/q]: " choice
    
    case "$choice" in
        1) install_all_packages ;;
        2) install_packages_for_api_only ;;
        3) check_all_services ;;
        4) restart_web_services ;;
        5) get_system_info ;;
        6) show_log_menu ;;
        7) update_system ;;
        q) return 0 ;;
        *) log_error "無效選項" ;;
    esac
}

log_info "系統模組已載入"
