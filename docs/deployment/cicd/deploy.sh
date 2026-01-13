#!/bin/bash
# ===================================================
# 主部署腳本 - deploy.sh
# LINE Reservation System 完整部署
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
source "$LIB_DIR/cloudflare.sh"
source "$LIB_DIR/menu.sh"

# ===== 錯誤處理 =====
set -e
trap 'log_error "部署失敗於第 $LINENO 行"; exit 1' ERR

# ===== 顯示歡迎訊息 =====
show_welcome() {
    clear
    echo ""
    echo "╔══════════════════════════════════════════════════════════╗"
    echo "║          🚀 LINE Reservation 部署系統 v2.0              ║"
    echo "║                                                          ║"
    echo "║  支援模式:                                               ║"
    echo "║  • 統一部署 (前後端同伺服器)                             ║"
    echo "║  • Headless API (Cloudflare Pages 前端)                  ║"
    echo "╚══════════════════════════════════════════════════════════╝"
    echo ""
}

# ===== 主部署流程 =====
deploy_unified() {
    local domain="$1"
    local use_ssl="${2:-false}"
    
    log_header "統一部署模式"
    
    # 1. 系統套件安裝
    log_step "步驟 1/12: 安裝系統套件..."
    install_all_packages "$use_ssl"
    
    # 2. 資料庫設置
    log_step "步驟 2/12: 設置資料庫..."
    setup_database
    
    # 3. 取得專案代碼
    log_step "步驟 3/12: 取得專案代碼..."
    deploy_from_source "$DEPLOY_SOURCE" "$PROJECT_DIR"
    
    # 4. 後端設置
    log_step "步驟 4/12: 安裝後端套件..."
    composer_install "$PROJECT_DIR" "true"
    
    # 5. Laravel 環境配置
    log_step "步驟 5/12: 配置 Laravel 環境..."
    local protocol="http"
    [ "$use_ssl" = "true" ] && protocol="https"
    
    setup_laravel_env "$PROJECT_DIR" "$domain" "$protocol" \
        "$DB_NAME" "$DB_USER" "$DB_PASSWORD"
    configure_laravel_for_unified "$PROJECT_DIR" "$domain" "$protocol"
    generate_app_key "$PROJECT_DIR"
    
    # 6. 資料庫遷移
    log_step "步驟 6/12: 執行資料庫遷移..."
    run_migrations "$PROJECT_DIR" "true"
    
    # 7. 前端套件
    log_step "步驟 7/12: 安裝前端套件..."
    npm_install "$PROJECT_DIR"
    
    # 8. 前端環境配置
    log_step "步驟 8/12: 配置前端環境..."
    configure_frontend_for_unified "$PROJECT_DIR" "$domain" "$protocol"
    
    # 9. 前端建置
    log_step "步驟 9/12: 建置前端..."
    build_frontend "$PROJECT_DIR"
    
    # 10. Apache 配置
    log_step "步驟 10/12: 配置 Apache..."
    local php_handler=$(detect_php_fpm_handler)
    switch_to_unified_mode "$domain" "$PROJECT_DIR" "$php_handler"
    
    # 11. SSL 設置
    if [ "$use_ssl" = "true" ]; then
        log_step "步驟 11/12: 設置 SSL 憑證..."
        setup_ssl_certificate "$domain"
    else
        log_step "步驟 11/12: 跳過 SSL..."
    fi
    
    # 12. 權限設置
    log_step "步驟 12/12: 設置權限..."
    set_backend_permissions "$PROJECT_DIR"
    create_storage_link "$PROJECT_DIR"
    rebuild_cache "$PROJECT_DIR"
    
    # 儲存配置
    save_config_item "DEPLOYMENT_MODE" "unified"
    save_config_item "BACKEND_DOMAIN" "$domain"
    save_all_config
    
    # 顯示完成訊息
    show_deploy_complete_unified "$domain" "$use_ssl"
}

deploy_api_only() {
    local backend_domain="$1"
    local frontend_domain="$2"
    local use_ssl="${3:-true}"
    
    log_header "純 API 模式部署 (Headless)"
    
    # 1. 系統套件安裝 (不含 Node.js)
    log_step "步驟 1/12: 安裝系統套件..."
    install_packages_for_api_only "$use_ssl"
    
    # 2. 資料庫設置
    log_step "步驟 2/12: 設置資料庫..."
    setup_database
    
    # 3. 取得專案代碼
    log_step "步驟 3/12: 取得專案代碼..."
    deploy_from_source "$DEPLOY_SOURCE" "$PROJECT_DIR"
    
    # 4. 後端設置
    log_step "步驟 4/12: 安裝後端套件..."
    composer_install "$PROJECT_DIR" "true"
    
    # 5. Laravel 環境配置 (API 模式)
    log_step "步驟 5/12: 配置 Laravel 環境 (API 模式)..."
    local backend_protocol="https"
    local frontend_protocol="https"
    
    setup_laravel_env "$PROJECT_DIR" "$backend_domain" "$backend_protocol" \
        "$DB_NAME" "$DB_USER" "$DB_PASSWORD"
    configure_laravel_for_api_only "$PROJECT_DIR" "$backend_domain" "$frontend_domain" \
        "$backend_protocol" "$frontend_protocol"
    generate_app_key "$PROJECT_DIR"
    
    # 6. 資料庫遷移
    log_step "步驟 6/12: 執行資料庫遷移..."
    run_migrations "$PROJECT_DIR" "true"
    
    # 7-8. 跳過前端 (由 Cloudflare Pages 託管)
    log_step "步驟 7-8/12: 跳過前端建置 (Cloudflare Pages 託管)..."
    skip_frontend_build
    
    # 9. 生成 Cloudflare 環境模板
    log_step "步驟 9/12: 生成 Cloudflare Pages 環境模板..."
    generate_cloudflare_env_file "$PROJECT_DIR" "$backend_domain" "$backend_protocol"
    
    # 10. Apache 配置 (API 模式 + CORS)
    log_step "步驟 10/12: 配置 Apache (API 模式)..."
    local php_handler=$(detect_php_fpm_handler)
    switch_to_api_mode "$backend_domain" "${frontend_protocol}://${frontend_domain}" "$PROJECT_DIR" "$php_handler"
    
    # 11. SSL 設置
    if [ "$use_ssl" = "true" ]; then
        log_step "步驟 11/12: 設置 SSL 憑證..."
        setup_ssl_certificate "$backend_domain"
    else
        log_step "步驟 11/12: 跳過 SSL..."
    fi
    
    # 12. 權限設置
    log_step "步驟 12/12: 設置權限..."
    set_backend_permissions "$PROJECT_DIR"
    create_storage_link "$PROJECT_DIR"
    rebuild_cache "$PROJECT_DIR"
    
    # 儲存配置
    save_config_item "DEPLOYMENT_MODE" "api_only"
    save_config_item "BACKEND_DOMAIN" "$backend_domain"
    save_config_item "CLOUDFLARE_FRONTEND_DOMAIN" "$frontend_domain"
    save_all_config
    
    # 顯示完成訊息
    show_deploy_complete_api_only "$backend_domain" "$frontend_domain"
}

# ===== 完成訊息 =====
show_deploy_complete_unified() {
    local domain="$1"
    local use_ssl="$2"
    
    local protocol="http"
    [ "$use_ssl" = "true" ] && protocol="https"
    
    echo ""
    echo "╔══════════════════════════════════════════════════════════╗"
    echo "║            ✅ 統一部署完成!                              ║"
    echo "╠══════════════════════════════════════════════════════════╣"
    echo "║                                                          ║"
    echo "║  🌐 網站網址: ${protocol}://${domain}"
    echo "║  📡 API 網址: ${protocol}://${domain}/api"
    echo "║                                                          ║"
    echo "║  📋 管理控制台:                                          ║"
    echo "║     ./manage.sh                                          ║"
    echo "║                                                          ║"
    echo "╚══════════════════════════════════════════════════════════╝"
    echo ""
}

show_deploy_complete_api_only() {
    local backend_domain="$1"
    local frontend_domain="$2"
    
    echo ""
    echo "╔══════════════════════════════════════════════════════════╗"
    echo "║            ✅ API 模式部署完成!                          ║"
    echo "╠══════════════════════════════════════════════════════════╣"
    echo "║                                                          ║"
    echo "║  ⚙️  後端 API: https://${backend_domain}/api"
    echo "║  🖥️  前端網址: https://${frontend_domain}"
    echo "║                                                          ║"
    echo "║  📋 Cloudflare Pages 設定:                               ║"
    echo "║     請在 Cloudflare 控制台設定環境變數:                  ║"
    echo "║     VITE_API_BASE_URL = https://${backend_domain}/api"
    echo "║                                                          ║"
    echo "║     詳細說明請查看:                                      ║"
    echo "║     frontend/.env.cloudflare                             ║"
    echo "║                                                          ║"
    echo "║  📋 管理控制台:                                          ║"
    echo "║     ./manage.sh                                          ║"
    echo "║                                                          ║"
    echo "╚══════════════════════════════════════════════════════════╝"
    echo ""
}

# ===== 互動式部署 =====
interactive_deploy() {
    show_welcome
    
    # 選擇部署模式
    local mode=$(prompt_deployment_mode)
    
    if [ "$mode" = "exit" ]; then
        log_info "已取消部署"
        exit 0
    fi
    
    # 選擇部署來源
    local deploy_source=$(prompt_deploy_source)
    export DEPLOY_SOURCE="$deploy_source"
    
    # 如果選擇 release，詢問版本和目標目錄
    local release_tag="latest"
    if [ "$deploy_source" = "release" ]; then
        release_tag=$(prompt_release_tag)
        export GITHUB_RELEASE_TAG="$release_tag"
        
        local target_dir=$(prompt_target_directory "/var/www/line-reservation")
        export PROJECT_DIR="$target_dir"
    fi
    
    # 取得域名配置
    local domain
    domain=$(prompt_domain_config "$mode")
    
    # SSL 配置
    local use_ssl
    use_ssl=$(prompt_ssl_config "$domain")
    
    # API Only 模式需要 Cloudflare 域名
    local cloudflare_domain=""
    if [ "$mode" = "api_only" ]; then
        cloudflare_domain=$(prompt_cloudflare_config)
        
        if [ -z "$cloudflare_domain" ]; then
            log_error "API 模式需要 Cloudflare Pages 域名"
            exit 1
        fi
    fi
    
    # 顯示確認
    echo ""
    log_header "部署配置確認"
    echo ""
    echo "📋 部署配置:"
    if [ "$mode" = "unified" ]; then
        echo "   部署模式: 統一部署 (前後端同伺服器)"
        echo "   域名/IP: $domain"
    else
        echo "   部署模式: 純 API 模式 (Headless)"
        echo "   後端域名: $domain"
        echo "   前端域名: $cloudflare_domain (Cloudflare Pages)"
    fi
    echo "   使用 SSL: $([ "$use_ssl" = "true" ] && echo "是" || echo "否")"
    echo "   部署來源: $([ "$deploy_source" = "release" ] && echo "GitHub Release" || echo "Git Repository")"
    if [ "$deploy_source" = "release" ]; then
        echo "   Release 版本: $release_tag"
    fi
    echo "   安裝路徑: $PROJECT_DIR"
    echo ""
    
    if ! confirm_action "確認以上配置並開始部署?"; then
        log_info "已取消部署"
        exit 0
    fi
    
    # 儲存部署源配置
    save_config_item "DEPLOY_SOURCE" "$deploy_source"
    if [ "$deploy_source" = "release" ]; then
        save_config_item "GITHUB_RELEASE_TAG" "$release_tag"
        save_config_item "DEPLOY_TARGET_DIR" "$PROJECT_DIR"
    fi
    
    # 執行部署
    if [ "$mode" = "unified" ]; then
        deploy_unified "$domain" "$use_ssl"
    else
        deploy_api_only "$domain" "$cloudflare_domain" "$use_ssl"
    fi
}

# ===== 顯示幫助 =====
show_help() {
    echo ""
    echo "LINE Reservation 部署腳本"
    echo ""
    echo "用法: ./deploy.sh [選項]"
    echo ""
    echo "選項:"
    echo "  --unified              統一部署模式 (前後端同伺服器)"
    echo "  --api-only             純 API 模式 (Cloudflare Pages 前端)"
    echo "  --domain=<域名>        設定後端域名"
    echo "  --cloudflare=<域名>    設定 Cloudflare Pages 域名 (API 模式必需)"
    echo "  --ssl                  啟用 SSL 憑證"
    echo "  --no-ssl               不使用 SSL"
    echo "  --source=<git|release> 部署來源 (git 或 release，默認: release)"
    echo "  --tag=<版本>           Release 版本標籤 (僅限 release 來源，默認: latest)"
    echo "  --target=<目錄>        安裝目錄 (默認: ~/line-reservation)"
    echo "  --help                 顯示此幫助訊息"
    echo ""
    echo "範例:"
    echo "  ./deploy.sh                                    # 互動式部署"
    echo "  ./deploy.sh --unified --domain=example.com --ssl"
    echo "  ./deploy.sh --api-only --domain=api.example.com --cloudflare=app.pages.dev --ssl"
    echo "  ./deploy.sh --unified --domain=example.com --source=release --tag=v1.0.0"
    echo "  ./deploy.sh --unified --domain=example.com --source=git"
    echo ""
}

# ===== 解析命令列參數 =====
parse_args() {
    DEPLOY_MODE=""
    DEPLOY_DOMAIN=""
    CLOUDFLARE_DOMAIN=""
    USE_SSL=""
    DEPLOY_SOURCE_ARG=""
    RELEASE_TAG_ARG=""
    TARGET_DIR_ARG=""
    
    while [[ $# -gt 0 ]]; do
        case $1 in
            --unified)
                DEPLOY_MODE="unified"
                shift
                ;;
            --api-only)
                DEPLOY_MODE="api_only"
                shift
                ;;
            --domain=*)
                DEPLOY_DOMAIN="${1#*=}"
                shift
                ;;
            --cloudflare=*)
                CLOUDFLARE_DOMAIN="${1#*=}"
                shift
                ;;
            --ssl)
                USE_SSL="true"
                shift
                ;;
            --no-ssl)
                USE_SSL="false"
                shift
                ;;
            --source=*)
                DEPLOY_SOURCE_ARG="${1#*=}"
                shift
                ;;
            --tag=*)
                RELEASE_TAG_ARG="${1#*=}"
                shift
                ;;
            --target=*)
                TARGET_DIR_ARG="${1#*=}"
                shift
                ;;
            --help|-h)
                show_help
                exit 0
                ;;
            *)
                log_error "未知參數: $1"
                show_help
                exit 1
                ;;
        esac
    done
}

# ===== 主程式 =====
main() {
    # 檢查 root
    if [[ $EUID -eq 0 ]]; then
        log_error "請不要用 root 用戶執行，改用一般用戶！"
        exit 1
    fi
    
    # 解析參數
    parse_args "$@"
    
    # 如果有命令列參數，使用非互動模式
    if [ -n "$DEPLOY_MODE" ]; then
        # 驗證必要參數
        if [ -z "$DEPLOY_DOMAIN" ]; then
            log_error "需要指定 --domain=<域名>"
            exit 1
        fi
        
        if [ "$DEPLOY_MODE" = "api_only" ] && [ -z "$CLOUDFLARE_DOMAIN" ]; then
            log_error "API 模式需要指定 --cloudflare=<域名>"
            exit 1
        fi
        
        # 設置部署源配置
        if [ -n "$DEPLOY_SOURCE_ARG" ]; then
            export DEPLOY_SOURCE="$DEPLOY_SOURCE_ARG"
        fi
        
        if [ -n "$RELEASE_TAG_ARG" ]; then
            export GITHUB_RELEASE_TAG="$RELEASE_TAG_ARG"
        fi
        
        if [ -n "$TARGET_DIR_ARG" ]; then
            export PROJECT_DIR="$TARGET_DIR_ARG"
        fi
        
        # 預設 SSL
        USE_SSL="${USE_SSL:-true}"
        
        # 執行部署
        if [ "$DEPLOY_MODE" = "unified" ]; then
            deploy_unified "$DEPLOY_DOMAIN" "$USE_SSL"
        else
            deploy_api_only "$DEPLOY_DOMAIN" "$CLOUDFLARE_DOMAIN" "$USE_SSL"
        fi
    else
        # 無參數時啟動互動模式
        interactive_deploy
    fi
}

# 執行主程式
main "$@"
