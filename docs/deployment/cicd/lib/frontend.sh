#!/bin/bash
# ===================================================
# 前端建置模組 - lib/frontend.sh
# NPM 安裝、Vite 建置
# ===================================================

# 防止重複載入
if [ -n "$_FRONTEND_SH_LOADED" ]; then
    return 0
fi
_FRONTEND_SH_LOADED=1

# 載入核心模組
source "$(dirname "${BASH_SOURCE[0]}")/core.sh"

# ===== Node.js 檢查 =====
check_nodejs() {
    if command -v node &>/dev/null; then
        return 0
    fi
    return 1
}

check_npm() {
    if command -v npm &>/dev/null; then
        return 0
    fi
    return 1
}

get_node_version() {
    if check_nodejs; then
        node --version
    else
        echo "未安裝"
    fi
}

get_npm_version() {
    if check_npm; then
        npm --version
    else
        echo "未安裝"
    fi
}

# ===== 前端環境配置 =====
setup_frontend_env() {
    local project_dir="$1"
    local api_url="$2"
    local app_url="$3"
    
    log_step "設置前端環境..."
    
    local frontend_dir="$project_dir/frontend"
    local env_file="$frontend_dir/.env"
    local env_example="$frontend_dir/.env.example"
    
    # 如果 .env 不存在，從範例複製或建立新檔
    if [ ! -f "$env_file" ]; then
        if [ -f "$env_example" ]; then
            cp "$env_example" "$env_file"
            log_info "已從 .env.example 建立 .env"
        else
            touch "$env_file"
            log_info "已建立新的 .env"
        fi
    fi
    
    # 更新 Vite 環境變數
    update_env_var "VITE_API_BASE_URL" "$api_url" "$env_file"
    update_env_var "VITE_APP_BASE_URL" "$app_url" "$env_file"
    update_env_var "VITE_APP_URL" "$app_url" "$env_file"
    
    log_success "前端環境配置完成"
}

configure_frontend_for_unified() {
    local project_dir="$1"
    local domain="$2"
    local protocol="${3:-http}"
    
    log_step "配置前端統一模式..."
    
    local api_url="${protocol}://${domain}/api"
    local app_url="${protocol}://${domain}"
    
    setup_frontend_env "$project_dir" "$api_url" "$app_url"
    
    log_success "統一模式前端配置完成"
}

configure_frontend_for_cloudflare() {
    local project_dir="$1"
    local backend_domain="$2"
    local backend_protocol="${3:-https}"
    
    log_step "配置前端 Cloudflare Pages 模式..."
    
    local api_url="${backend_protocol}://${backend_domain}/api"
    
    # 本地開發使用
    local env_file="$project_dir/frontend/.env"
    update_env_var "VITE_API_BASE_URL" "$api_url" "$env_file"
    
    # 生成 Cloudflare Pages 環境變數模板
    generate_cloudflare_env_template "$project_dir" "${backend_protocol}://${backend_domain}"
    
    log_success "Cloudflare Pages 前端配置完成"
}

# ===== NPM 管理 =====
npm_install() {
    local project_dir="$1"
    local clean_install="${2:-false}"
    
    log_step "執行 NPM 安裝..."
    
    local frontend_dir="$project_dir/frontend"
    
    if [ ! -f "$frontend_dir/package.json" ]; then
        log_error "找不到 package.json: $frontend_dir"
        return 1
    fi
    
    cd "$frontend_dir"
    
    # 是否需要清理重裝
    if [ "$clean_install" = "true" ]; then
        npm_clean "$project_dir"
    fi
    
    log_info "正在安裝 npm 套件..."
    npm install
    
    if [ $? -eq 0 ]; then
        log_success "NPM 套件安裝完成"
        return 0
    else
        log_error "NPM 安裝失敗"
        return 1
    fi
}

npm_ci() {
    local project_dir="$1"
    
    log_step "執行 NPM CI (清淨安裝)..."
    
    cd "$project_dir/frontend"
    npm ci
    
    if [ $? -eq 0 ]; then
        log_success "NPM CI 完成"
        return 0
    else
        log_error "NPM CI 失敗"
        return 1
    fi
}

npm_clean() {
    local project_dir="$1"
    
    log_step "清理 NPM 快取..."
    
    local frontend_dir="$project_dir/frontend"
    
    if [ -d "$frontend_dir/node_modules" ]; then
        rm -rf "$frontend_dir/node_modules"
        log_info "已刪除 node_modules"
    fi
    
    if [ -f "$frontend_dir/package-lock.json" ]; then
        rm -f "$frontend_dir/package-lock.json"
        log_info "已刪除 package-lock.json"
    fi
    
    log_success "NPM 清理完成"
}

# ===== 前端建置 =====
build_frontend() {
    local project_dir="$1"
    
    log_step "建置前端..."
    
    local frontend_dir="$project_dir/frontend"
    
    cd "$frontend_dir"
    
    # 檢查 node_modules
    if [ ! -d "node_modules" ]; then
        log_warning "node_modules 不存在，先執行 npm install"
        npm_install "$project_dir"
    fi
    
    log_info "正在執行 npm run build..."
    npm run build
    
    if [ $? -eq 0 ]; then
        log_success "前端建置完成"
        
        # 驗證輸出
        verify_build_output "$project_dir"
        return 0
    else
        log_error "前端建置失敗"
        return 1
    fi
}

clean_dist() {
    local project_dir="$1"
    
    log_step "清理 dist 目錄..."
    
    local dist_dir="$project_dir/frontend/dist"
    
    if [ -d "$dist_dir" ]; then
        rm -rf "$dist_dir"
        log_success "dist 目錄已清理"
    else
        log_info "dist 目錄不存在"
    fi
}

# 建議修改 lib/frontend.sh 中的 rebuild_frontend 函式
rebuild_frontend() {
    local project_dir="$1"
    local full_rebuild="${2:-false}"
    
    log_step "重建前端..."
    
    # 【新增】暫時切換權限給當前用戶，確保 npm 能寫入
    log_info "暫時取得目錄權限..."
    sudo chown -R $USER:$USER "$project_dir/frontend"
    
    # 清理 dist
    clean_dist "$project_dir"
    
    # 完整重建包含 npm 清理
    if [ "$full_rebuild" = "true" ]; then
        npm_clean "$project_dir"
        npm_install "$project_dir"
    fi
    
    # 執行建置
    build_frontend "$project_dir"
    local build_status=$?

    # 【新增】恢復安全權限 (只針對 frontend 目錄，或最後統一呼叫 set_secure_permissions)
    # 這裡簡單恢復擁有者，完整權限由後續流程處理
    sudo chown -R www-data:www-data "$project_dir/frontend"
    
    if [ $build_status -eq 0 ]; then
        return 0
    else
        return 1
    fi
}

# ===== Cloudflare Pages 專用 =====
generate_cloudflare_env_template() {
    local project_dir="$1"
    local backend_url="$2"
    
    log_step "生成 Cloudflare Pages 環境變數模板..."
    
    local template_file="$project_dir/frontend/.env.cloudflare"
    
    cat > "$template_file" << EOF
# ===================================================
# Cloudflare Pages 環境變數設定
# ===================================================
# 請在 Cloudflare Pages 控制台設定以下環境變數:
#
# Settings > Environment variables
#
# Production 環境:
# ===================================================

# API 基礎網址 (必填)
VITE_API_BASE_URL=${backend_url}/api

# 應用網址 (Cloudflare Pages 會自動提供)
# VITE_APP_URL=https://your-project.pages.dev

# ===================================================
# 建置設定 (Build settings):
# ===================================================
# Build command: npm run build
# Build output directory: dist
# Root directory: frontend
#
# ===================================================
# 進階設定 (Preview 環境可用不同變數):
# ===================================================
# VITE_API_BASE_URL=${backend_url}/api
#
EOF
    
    log_success "Cloudflare Pages 模板已生成: .env.cloudflare"
    echo ""
    echo "📋 Cloudflare Pages 設定說明:"
    echo "   1. 進入 Cloudflare Pages 控制台"
    echo "   2. Settings > Environment variables"
    echo "   3. 新增: VITE_API_BASE_URL = ${backend_url}/api"
    echo ""
}

skip_frontend_build() {
    log_info "API Only 模式 - 跳過前端建置"
    log_info "前端將由 Cloudflare Pages 託管"
}

# ===== 開發伺服器 =====
start_dev_server() {
    local project_dir="$1"
    local port="${2:-5173}"
    
    log_step "啟動開發伺服器..."
    
    cd "$project_dir/frontend"
    
    log_info "開發伺服器將在 http://localhost:$port 啟動"
    log_warning "這僅供開發使用，請勿在生產環境執行"
    
    npm run dev -- --port "$port"
}

# ===== 驗證 =====
verify_build_output() {
    local project_dir="$1"
    
    local dist_dir="$project_dir/frontend/dist"
    
    if [ ! -d "$dist_dir" ]; then
        log_error "dist 目錄不存在"
        return 1
    fi
    
    # 檢查 index.html
    if [ ! -f "$dist_dir/index.html" ]; then
        log_error "index.html 不存在"
        return 1
    fi
    
    # 檢查 assets 目錄
    if [ ! -d "$dist_dir/assets" ]; then
        log_warning "assets 目錄不存在"
    fi
    
    # 統計檔案
    local file_count=$(find "$dist_dir" -type f | wc -l)
    local total_size=$(du -sh "$dist_dir" | cut -f1)
    
    echo ""
    echo "📦 建置輸出:"
    echo "   目錄: $dist_dir"
    echo "   檔案數: $file_count"
    echo "   總大小: $total_size"
    
    return 0
}

# ===== 完整前端部署 =====
deploy_frontend() {
    local project_dir="$1"
    local domain="$2"
    local protocol="${3:-http}"
    local deployment_mode="${4:-unified}"
    local backend_domain="$5"
    
    log_header "部署前端"
    
    # API Only 模式 - 跳過建置
    if [ "$deployment_mode" = "api_only" ]; then
        skip_frontend_build
        
        if [ -n "$backend_domain" ]; then
            generate_cloudflare_env_template "$project_dir" "${protocol}://${backend_domain}"
        fi
        
        return 0
    fi
    
    # 統一模式 - 完整建置
    configure_frontend_for_unified "$project_dir" "$domain" "$protocol"
    
    # NPM 安裝
    if ! npm_install "$project_dir"; then
        return 1
    fi
    
    # 建置
    if ! build_frontend "$project_dir"; then
        return 1
    fi
    
    log_success "前端部署完成"
}

log_info "前端模組已載入"
