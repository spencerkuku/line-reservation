#!/bin/bash
# ===================================================
# 選單 UI 模組 - lib/menu.sh
# 互動式選單介面
# ===================================================

# 防止重複載入
if [ -n "$_MENU_SH_LOADED" ]; then
    return 0
fi
_MENU_SH_LOADED=1

# 載入核心模組
source "$(dirname "${BASH_SOURCE[0]}")/core.sh"

# ===== 部署選單 (deploy.sh 使用) =====
show_deploy_menu() {
    clear
    echo ""
    echo "╔══════════════════════════════════════════════════════════╗"
    echo "║         🚀 LINE Reservation 部署系統 v2.0               ║"
    echo "╠══════════════════════════════════════════════════════════╣"
    echo "║                                                          ║"
    echo "║  請選擇部署模式:                                         ║"
    echo "║                                                          ║"
    echo "║  1) 🖥️  統一部署 (前後端同伺服器)                        ║"
    echo "║     - 前端 Vue.js 建置到本機                             ║"
    echo "║     - 後端 Laravel API 同伺服器                          ║"
    echo "║     - 適合單一伺服器環境                                 ║"
    echo "║                                                          ║"
    echo "║  2) ☁️  純後端 API 部署 (Headless Mode)                  ║"
    echo "║     - 僅部署 Laravel 後端 API                            ║"
    echo "║     - 前端使用 Cloudflare Pages                          ║"
    echo "║     - 自動配置 CORS 跨域設定                             ║"
    echo "║     - 適合前後端分離架構                                 ║"
    echo "║                                                          ║"
    echo "║  0) 退出                                                 ║"
    echo "║                                                          ║"
    echo "╚══════════════════════════════════════════════════════════╝"
    echo ""
}

prompt_deployment_mode() {
    show_deploy_menu
    
    local choice
    read -p "請選擇 [1/2/0]: " choice
    
    case "$choice" in
        1) echo "unified" ;;
        2) echo "api_only" ;;
        0) echo "exit" ;;
        *) 
            log_error "無效選項，請重新選擇"
            prompt_deployment_mode
            ;;
    esac
}

prompt_domain_config() {
    local mode="${1:-unified}"
    
    echo ""
    log_step "配置域名/IP..."
    echo ""
    
    if [ "$mode" = "api_only" ]; then
        echo "請輸入後端 API 伺服器的域名或 IP"
    else
        echo "請輸入伺服器的域名或 IP"
    fi
    echo "範例: example.com 或 192.168.1.100"
    echo ""
    
    local domain
    local default_ip=$(get_server_ip)
    read -p "域名/IP [$default_ip]: " domain
    domain=${domain:-$default_ip}
    
    # 移除協議前綴和尾部斜線
    domain="${domain#http://}"
    domain="${domain#https://}"
    domain="${domain%/}"
    
    echo "$domain"
}

prompt_ssl_config() {
    local domain="$1"
    
    echo ""
    
    # IP 地址無法使用 SSL
    if is_ip_address "$domain"; then
        log_warning "IP 地址無法使用 SSL 憑證，將跳過 SSL 設置"
        echo "false"
        return 0
    fi
    
    local response
    read -p "是否要設置 SSL 憑證? (需要有效域名) (y/N): " response
    response=${response:-N}
    
    if [[ "$response" =~ ^[Yy]$ ]]; then
        echo "true"
    else
        echo "false"
    fi
}

prompt_cloudflare_config() {
    echo ""
    log_step "Cloudflare Pages 配置..."
    echo ""
    echo "請輸入您的 Cloudflare Pages 前端域名"
    echo "範例:"
    echo "  - myapp.pages.dev (Cloudflare 自動分配)"
    echo "  - app.example.com (自訂域名)"
    echo ""
    
    local domain
    read -p "Cloudflare Pages 域名: " domain
    
    # 移除協議前綴
    domain="${domain#http://}"
    domain="${domain#https://}"
    domain="${domain%/}"
    
    echo "$domain"
}

# ===== 管理選單 (manage.sh 使用) =====
show_main_menu() {
    clear
    echo ""
    echo "╔══════════════════════════════════════════════════════════╗"
    echo "║     🛠️  LINE Reservation 生產環境管理控制台              ║"
    echo "╠══════════════════════════════════════════════════════════╣"
    echo "║                                                          ║"
    show_deployment_mode_indicator
    echo "║                                                          ║"
    echo "║  📁 【環境配置管理】                                     ║"
    echo "║   1) 域名/IP 配置更新                                    ║"
    echo "║   2) SSL 證書管理                                        ║"
    echo "║   3) ☁️ Cloudflare Pages 配置                            ║"
    echo "║                                                          ║"
    echo "║  🏗️ 【建置與部署管理】                                   ║"
    echo "║   4) 前端重新建置                                        ║"
    echo "║   5) 後端快取清理                                        ║"
    echo "║   6) 完整系統重建                                        ║"
    echo "║                                                          ║"
    echo "║  🔄 【版本控制與更新】                                   ║"
    echo "║   7) 代碼更新 (Git Pull + 重建)                          ║"
    echo "║   8) Git 倉庫管理                                        ║"
    echo "║                                                          ║"
    echo "║  ⚙️ 【系統服務管理】                                     ║"
    echo "║   9) 重啟 Web 服務                                       ║"
    echo "║  10) 系統狀態監控                                        ║"
    echo "║  11) 日誌查看                                            ║"
    echo "║                                                          ║"
    echo "║  💾 【備份與恢復】                                       ║"
    echo "║  12) 備份管理                                            ║"
    echo "║                                                          ║"
    echo "║   0) 安全退出                                            ║"
    echo "║                                                          ║"
    echo "╚══════════════════════════════════════════════════════════╝"
    echo ""
}

show_cloudflare_submenu() {
    echo ""
    echo "╔══════════════════════════════════════════════════════════╗"
    echo "║           ☁️ Cloudflare Pages 配置                       ║"
    echo "╠══════════════════════════════════════════════════════════╣"
    echo "║                                                          ║"
    echo "║  1) 🌟 配置 Cloudflare Pages 整合                        ║"
    echo "║  2) 📋 查看當前配置                                      ║"
    echo "║  3) ✅ 驗證配置                                          ║"
    echo "║  4) 🔄 更新前端域名                                      ║"
    echo "║  5) 🔀 切換到統一模式                                    ║"
    echo "║  6) 🧪 測試 CORS                                         ║"
    echo "║                                                          ║"
    echo "║  0) 返回主選單                                           ║"
    echo "║                                                          ║"
    echo "╚══════════════════════════════════════════════════════════╝"
    echo ""
}

show_git_submenu() {
    echo ""
    echo "╔══════════════════════════════════════════════════════════╗"
    echo "║              🔄 Git 倉庫管理                             ║"
    echo "╠══════════════════════════════════════════════════════════╣"
    echo "║                                                          ║"
    echo "║  1) Git Pull (更新代碼)                                  ║"
    echo "║  2) Git Fetch (獲取遠端)                                 ║"
    echo "║  3) 切換分支                                             ║"
    echo "║  4) 恢復檔案 (Restore)                                   ║"
    echo "║  5) 強制重置 (Reset --hard)                              ║"
    echo "║  6) 查看狀態                                             ║"
    echo "║                                                          ║"
    echo "║  0) 返回主選單                                           ║"
    echo "║                                                          ║"
    echo "╚══════════════════════════════════════════════════════════╝"
    echo ""
}

show_ssl_submenu() {
    echo ""
    echo "╔══════════════════════════════════════════════════════════╗"
    echo "║              🔐 SSL 證書管理                             ║"
    echo "╠══════════════════════════════════════════════════════════╣"
    echo "║                                                          ║"
    echo "║  1) 設置 SSL 憑證 (Let's Encrypt)                        ║"
    echo "║  2) 更新 SSL 憑證                                        ║"
    echo "║  3) 檢查憑證狀態                                         ║"
    echo "║  4) 切換 SSL 開關                                        ║"
    echo "║                                                          ║"
    echo "║  0) 返回主選單                                           ║"
    echo "║                                                          ║"
    echo "╚══════════════════════════════════════════════════════════╝"
    echo ""
}

# ===== 狀態顯示 =====
show_current_status() {
    echo ""
    echo "📊 當前配置狀態:"
    echo "   部署模式:   ${DEPLOYMENT_MODE:-未設定}"
    echo "   專案目錄:   ${PROJECT_DIR:-未設定}"
    echo "   後端域名:   ${BACKEND_DOMAIN:-未設定}"
    
    if [ "$DEPLOYMENT_MODE" = "api_only" ]; then
        echo "   前端域名:   ${CLOUDFLARE_FRONTEND_DOMAIN:-未設定} (Cloudflare Pages)"
    fi
    
    echo ""
}

show_deployment_mode_indicator() {
    if [ "$DEPLOYMENT_MODE" = "api_only" ]; then
        echo "║  📍 當前模式: ☁️ Headless API (Cloudflare Pages)       ║"
    else
        echo "║  📍 當前模式: 🖥️ 統一部署 (前後端同伺服器)            ║"
    fi
}

# ===== 選單處理 =====
handle_main_menu_choice() {
    local choice="$1"
    
    case "$choice" in
        1) handle_domain_update ;;
        2) handle_ssl_menu ;;
        3) handle_cloudflare_menu ;;
        4) handle_frontend_rebuild ;;
        5) handle_backend_cache ;;
        6) handle_full_rebuild ;;
        7) handle_code_update ;;
        8) handle_git_menu ;;
        9) handle_restart_services ;;
        10) handle_system_status ;;
        11) handle_log_viewer ;;
        12) handle_backup_menu ;;
        0) 
            log_info "感謝使用，再見！"
            exit 0
            ;;
        *)
            log_error "無效選項，請重新選擇"
            ;;
    esac
}

handle_cloudflare_menu_choice() {
    local choice="$1"
    
    case "$choice" in
        1) configure_cloudflare_integration ;;
        2) show_cloudflare_status ;;
        3) verify_cloudflare_config ;;
        4) 
            local new_domain=$(prompt_cloudflare_config)
            if [ -n "$new_domain" ]; then
                update_cloudflare_domain "$new_domain"
            fi
            ;;
        5)
            local domain=$(read_with_default "請輸入統一域名" "$BACKEND_DOMAIN")
            switch_back_to_unified_mode "$domain"
            ;;
        6)
            local api_url="https://${BACKEND_DOMAIN}"
            test_cors_headers "$api_url"
            ;;
        0) return 0 ;;
        *)
            log_error "無效選項"
            ;;
    esac
}

handle_git_menu_choice() {
    local choice="$1"
    
    case "$choice" in
        1) interactive_git_pull "$PROJECT_DIR" ;;
        2) git_fetch "$PROJECT_DIR" ;;
        3)
            local branch=$(read_with_default "請輸入分支名稱" "main")
            git_checkout_branch "$PROJECT_DIR" "$branch"
            ;;
        4) interactive_git_restore "$PROJECT_DIR" ;;
        5)
            if confirm_action "確定要強制重置嗎? 這會丟失所有本地更改!"; then
                git_reset_hard "$PROJECT_DIR"
            fi
            ;;
        6)
            cd "$PROJECT_DIR"
            git status
            ;;
        0) return 0 ;;
        *)
            log_error "無效選項"
            ;;
    esac
}

handle_ssl_menu_choice() {
    local choice="$1"
    
    case "$choice" in
        1) interactive_ssl_setup "$BACKEND_DOMAIN" ;;
        2) renew_ssl_certificate ;;
        3) check_ssl_expiry "$BACKEND_DOMAIN" ;;
        4)
            local current_ssl=$(grep "^SESSION_SECURE_COOKIE=" "$PROJECT_DIR/backend/.env" | cut -d= -f2)
            toggle_ssl "$BACKEND_DOMAIN" "$current_ssl"
            ;;
        0) return 0 ;;
        *)
            log_error "無效選項"
            ;;
    esac
}

# ===== 處理函數 =====
handle_domain_update() {
    log_header "更新域名配置"
    
    local new_domain=$(read_with_default "請輸入新的域名/IP" "$BACKEND_DOMAIN")
    
    if confirm_action "確定要更新為 $new_domain 嗎?"; then
        update_backend_domain "$new_domain"
    fi
}

handle_ssl_menu() {
    while true; do
        show_ssl_submenu
        read -p "請選擇 [0-4]: " choice
        handle_ssl_menu_choice "$choice"
        [ "$choice" = "0" ] && break
        pause_for_input
    done
}

handle_cloudflare_menu() {
    while true; do
        show_cloudflare_submenu
        read -p "請選擇 [0-6]: " choice
        handle_cloudflare_menu_choice "$choice"
        [ "$choice" = "0" ] && break
        pause_for_input
    done
}

handle_frontend_rebuild() {
    log_header "重建前端"
    
    if [ "$DEPLOYMENT_MODE" = "api_only" ]; then
        skip_frontend_build
    else
        rebuild_frontend "$PROJECT_DIR"
    fi
}

handle_backend_cache() {
    log_header "清理後端快取"
    
    clear_all_cache "$PROJECT_DIR"
}

handle_full_rebuild() {
    log_header "完整系統重建"
    
    if confirm_action "這將重建整個系統，確定繼續嗎?"; then
        # 後端
        composer_install "$PROJECT_DIR" "true"
        rebuild_cache "$PROJECT_DIR"
        
        # 前端 (如果不是 API Only 模式)
        if [ "$DEPLOYMENT_MODE" != "api_only" ]; then
            rebuild_frontend "$PROJECT_DIR" "true"
        fi
        
        # 重啟服務
        restart_web_services
        
        log_success "完整系統重建完成"
    fi
}

handle_code_update() {
    log_header "代碼更新"
    
    # Git Pull
    if ! git_pull "$PROJECT_DIR"; then
        log_error "代碼更新失敗"
        return 1
    fi
    
    # 重建後端
    composer_install "$PROJECT_DIR" "true"
    run_migrations "$PROJECT_DIR" "true"
    rebuild_cache "$PROJECT_DIR"
    
    # 重建前端 (如果不是 API Only 模式)
    if [ "$DEPLOYMENT_MODE" != "api_only" ]; then
        rebuild_frontend "$PROJECT_DIR"
    fi
    
    # 重啟服務
    restart_web_services
    
    log_success "代碼更新完成"
}

handle_git_menu() {
    while true; do
        show_git_submenu
        read -p "請選擇 [0-6]: " choice
        handle_git_menu_choice "$choice"
        [ "$choice" = "0" ] && break
        pause_for_input
    done
}

handle_restart_services() {
    log_header "重啟 Web 服務"
    
    restart_web_services
}

handle_system_status() {
    check_all_services
    get_system_info
}

handle_log_viewer() {
    show_log_menu
}

handle_backup_menu() {
    show_backup_menu
}

# ===== 主迴圈 =====
main_menu_loop() {
    while true; do
        show_main_menu
        read -p "請選擇 [0-12]: " choice
        handle_main_menu_choice "$choice"
        
        if [ "$choice" != "0" ]; then
            pause_for_input
        fi
    done
}

pause_for_input() {
    echo ""
    read -p "按 Enter 繼續..." 
}

# ===== 確認對話框 =====
show_confirm_dialog() {
    local message="$1"
    local default="${2:-n}"
    
    local prompt
    if [ "$default" = "y" ]; then
        prompt="[Y/n]"
    else
        prompt="[y/N]"
    fi
    
    echo ""
    read -p "$message $prompt: " answer
    
    answer="${answer:-$default}"
    
    case "${answer,,}" in
        y|yes) return 0 ;;
        *) return 1 ;;
    esac
}

log_info "選單模組已載入"
