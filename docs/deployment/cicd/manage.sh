#!/bin/bash
# ===================================================
# 管理控制台 - manage.sh
# LINE Reservation System 生產環境管理工具
# 版本: 2.0
# ===================================================

# 載入配置
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "$SCRIPT_DIR/config.sh"

# 載入所有模組
source "$LIB_DIR/core.sh"
source "$LIB_DIR/system.sh"
source "$LIB_DIR/db.sh"
source "$LIB_DIR/git.sh"
source "$LIB_DIR/backend.sh"
source "$LIB_DIR/frontend.sh"
source "$LIB_DIR/apache.sh"
source "$LIB_DIR/ssl.sh"
source "$LIB_DIR/backup.sh"
source "$LIB_DIR/cloudflare.sh"
source "$LIB_DIR/menu.sh"

# ===== 錯誤處理 =====
trap 'log_error "操作失敗於第 $LINENO 行"' ERR

# ===== 前置檢查 =====
pre_checks() {
    # 檢查非 root
    if [[ $EUID -eq 0 ]]; then
        log_error "請不要用 root 用戶執行！"
        exit 1
    fi
    
    # 載入已儲存的配置
    load_saved_config
    
    # 檢查專案目錄
    if [ ! -d "$PROJECT_DIR" ]; then
        log_warning "專案目錄不存在: $PROJECT_DIR"
        log_info "請先執行 ./deploy.sh 進行初始部署"
        
        if confirm_action "是否要現在配置專案路徑?"; then
            local new_path=$(read_with_default "專案路徑" "/var/www/line-reservation")
            save_config_item "PROJECT_DIR" "$new_path"
            PROJECT_DIR="$new_path"
        else
            exit 1
        fi
    fi
    
    # 檢查 .env 是否存在
    if [ ! -f "$PROJECT_DIR/backend/.env" ]; then
        log_warning "後端 .env 不存在，可能尚未完成部署"
    fi
}

# ===== 快速操作函數 =====
quick_update() {
    log_header "快速更新"
    
    log_step "1/4 拉取最新代碼..."
    git_pull "$PROJECT_DIR"
    
    log_step "2/4 更新後端..."
    composer_install "$PROJECT_DIR" "true"
    run_migrations "$PROJECT_DIR" "true"
    
    log_step "3/4 更新前端..."
    if [ "$DEPLOYMENT_MODE" != "api_only" ]; then
        npm_install "$PROJECT_DIR"
        build_frontend "$PROJECT_DIR"
    else
        log_info "API 模式，跳過前端建置"
    fi
    
    log_step "4/4 重建快取..."
    rebuild_cache "$PROJECT_DIR"
    restart_web_services
    
    log_success "快速更新完成!"
}

quick_cache_clear() {
    log_header "清除所有快取"
    
    clear_all_cache "$PROJECT_DIR"
    restart_web_services
    
    log_success "快取已清除!"
}

quick_restart() {
    log_header "重啟服務"
    
    restart_web_services
    
    log_success "服務已重啟!"
}

# ===== 選單處理 =====
process_menu_choice() {
    local choice="$1"
    
    case $choice in
        # 環境配置管理
        1)
            log_header "更新域名配置"
            local new_domain=$(read_with_default "請輸入新的域名/IP" "$BACKEND_DOMAIN")
            
            if confirm_action "確定要更新為 $new_domain 嗎?"; then
                update_backend_domain "$new_domain" "$PROJECT_DIR"
            fi
            ;;
        2)
            handle_ssl_menu
            ;;
        3)
            handle_cloudflare_menu
            ;;
            
        # 建置與部署
        4)
            log_header "重建前端"
            if [ "$DEPLOYMENT_MODE" = "api_only" ]; then
                skip_frontend_build
            else
                if confirm_action "確定要重建前端嗎?"; then
                    rebuild_frontend "$PROJECT_DIR"
                fi
            fi
            ;;
        5)
            log_header "清除後端快取"
            quick_cache_clear
            ;;
        6)
            log_header "完整系統重建"
            if confirm_action "這將重建整個系統，確定繼續嗎?"; then
                # 後端
                composer_install "$PROJECT_DIR" "true"
                rebuild_cache "$PROJECT_DIR"
                
                # 前端
                if [ "$DEPLOYMENT_MODE" != "api_only" ]; then
                    rebuild_frontend "$PROJECT_DIR" "true"
                fi
                
                restart_web_services
                log_success "完整系統重建完成"
            fi
            ;;
            
        # 版本控制
        7)
            log_header "代碼更新"
            if confirm_action "確定要更新代碼嗎?"; then
                quick_update
            fi
            ;;
        8)
            handle_git_menu
            ;;
            
        # 系統服務
        9)
            quick_restart
            ;;
        10)
            check_all_services
            get_system_info
            show_current_status
            ;;
        11)
            show_log_menu
            ;;
            
        # 備份
        12)
            show_backup_menu
            ;;
            
        0)
            log_success "感謝使用！系統已安全退出"
            exit 0
            ;;
        *)
            log_error "無效選擇，請輸入 0-12 之間的數字"
            ;;
    esac
}

# ===== SSL 選單 =====
handle_ssl_menu() {
    while true; do
        show_ssl_submenu
        read -p "請選擇 [0-4]: " choice
        
        case "$choice" in
            1)
                if [ -n "$BACKEND_DOMAIN" ]; then
                    interactive_ssl_setup "$BACKEND_DOMAIN"
                else
                    log_error "請先設定域名"
                fi
                ;;
            2)
                renew_ssl_certificate
                ;;
            3)
                if [ -n "$BACKEND_DOMAIN" ]; then
                    check_ssl_expiry "$BACKEND_DOMAIN"
                fi
                ;;
            4)
                local current_ssl=$(grep "^SESSION_SECURE_COOKIE=" "$PROJECT_DIR/backend/.env" 2>/dev/null | cut -d= -f2)
                toggle_ssl "$BACKEND_DOMAIN" "$current_ssl" "$PROJECT_DIR"
                ;;
            0)
                break
                ;;
            *)
                log_error "無效選項"
                ;;
        esac
        
        [ "$choice" != "0" ] && pause_for_input
    done
}

# ===== Cloudflare 選單 =====
handle_cloudflare_menu() {
    while true; do
        show_cloudflare_submenu
        read -p "請選擇 [0-6]: " choice
        
        case "$choice" in
            1)
                configure_cloudflare_integration "$PROJECT_DIR"
                ;;
            2)
                show_cloudflare_status "$PROJECT_DIR"
                ;;
            3)
                verify_cloudflare_config "$PROJECT_DIR"
                ;;
            4)
                echo ""
                log_info "請輸入新的 Cloudflare Pages 域名"
                local new_domain
                read -p "域名: " new_domain
                
                if [ -n "$new_domain" ]; then
                    update_cloudflare_domain "$new_domain" "$PROJECT_DIR"
                fi
                ;;
            5)
                local domain=$(read_with_default "請輸入統一域名" "$BACKEND_DOMAIN")
                if confirm_action "確定要切換到統一模式嗎?"; then
                    switch_back_to_unified_mode "$domain" "$PROJECT_DIR"
                fi
                ;;
            6)
                if [ -n "$BACKEND_DOMAIN" ]; then
                    test_cors_headers "https://${BACKEND_DOMAIN}"
                else
                    log_error "請先設定後端域名"
                fi
                ;;
            0)
                break
                ;;
            *)
                log_error "無效選項"
                ;;
        esac
        
        [ "$choice" != "0" ] && pause_for_input
    done
}

# ===== Git 選單 =====
handle_git_menu() {
    while true; do
        show_git_submenu
        read -p "請選擇 [0-6]: " choice
        
        case "$choice" in
            1)
                interactive_git_pull "$PROJECT_DIR"
                ;;
            2)
                git_fetch "$PROJECT_DIR"
                ;;
            3)
                local branch=$(read_with_default "請輸入分支名稱" "main")
                git_checkout_branch "$PROJECT_DIR" "$branch"
                ;;
            4)
                interactive_git_restore "$PROJECT_DIR"
                ;;
            5)
                if confirm_action "確定要強制重置嗎? 這會丟失所有本地更改!"; then
                    git_reset_hard "$PROJECT_DIR"
                fi
                ;;
            6)
                cd "$PROJECT_DIR"
                echo ""
                git status
                echo ""
                git log --oneline -5
                ;;
            0)
                break
                ;;
            *)
                log_error "無效選項"
                ;;
        esac
        
        [ "$choice" != "0" ] && pause_for_input
    done
}

# ===== 顯示幫助 =====
show_help() {
    echo ""
    echo "LINE Reservation 管理控制台"
    echo ""
    echo "用法: ./manage.sh [命令]"
    echo ""
    echo "命令:"
    echo "  (無參數)         啟動互動式選單"
    echo "  update           快速更新 (pull + rebuild)"
    echo "  cache            清除所有快取"
    echo "  restart          重啟 Web 服務"
    echo "  status           顯示系統狀態"
    echo "  backup           建立備份"
    echo "  help             顯示此幫助"
    echo ""
    echo "範例:"
    echo "  ./manage.sh              # 互動式選單"
    echo "  ./manage.sh update       # 快速更新"
    echo "  ./manage.sh cache        # 清除快取"
    echo ""
}

# ===== 主程式 =====
main() {
    # 解析命令列參數
    case "${1:-}" in
        update)
            pre_checks
            quick_update
            exit 0
            ;;
        cache|clear)
            pre_checks
            quick_cache_clear
            exit 0
            ;;
        restart)
            pre_checks
            quick_restart
            exit 0
            ;;
        status)
            pre_checks
            check_all_services
            get_system_info
            show_current_status
            exit 0
            ;;
        backup)
            pre_checks
            show_backup_menu
            exit 0
            ;;
        help|--help|-h)
            show_help
            exit 0
            ;;
        "")
            # 無參數，啟動互動式選單
            ;;
        *)
            log_error "未知命令: $1"
            show_help
            exit 1
            ;;
    esac
    
    # 前置檢查
    pre_checks
    
    # 主選單迴圈
    while true; do
        show_main_menu
        
        echo ""
        read -p "請選擇操作 [0-12]: " choice
        
        process_menu_choice "$choice"
        
        if [ "$choice" != "0" ]; then
            pause_for_input
        fi
    done
}

# 執行主程式
main "$@"
