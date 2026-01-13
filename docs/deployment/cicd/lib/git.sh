#!/bin/bash
# ===================================================
# Git 版本控制模組 - lib/git.sh
# Git 初始化、Pull、分支管理
# ===================================================

# 防止重複載入
if [ -n "$_GIT_SH_LOADED" ]; then
    return 0
fi
_GIT_SH_LOADED=1

# 載入核心模組
source "$(dirname "${BASH_SOURCE[0]}")/core.sh"

# ===== Git 初始化 =====
git_clone() {
    local repo_url="${1:-$GIT_REPO_URL}"
    local target_dir="${2:-$PROJECT_DIR}"
    local branch="${3:-$GIT_BRANCH}"
    
    log_step "克隆專案: $repo_url"
    
    # 確保目標目錄的父目錄存在
    local parent_dir=$(dirname "$target_dir")
    sudo mkdir -p "$parent_dir"
    
    # 克隆
    if sudo git clone -b "$branch" "$repo_url" "$target_dir"; then
        sudo chown -R $USER:$USER "$target_dir"
        git_set_safe_directory "$target_dir"
        log_success "專案克隆完成"
        return 0
    else
        log_error "專案克隆失敗"
        return 1
    fi
}

git_init_existing() {
    local project_dir="${1:-$PROJECT_DIR}"
    local repo_url="${2:-$GIT_REPO_URL}"
    
    log_step "在現有目錄初始化 Git..."
    
    cd "$project_dir" || return 1
    
    # 設置安全目錄
    git_set_safe_directory "$project_dir"
    
    # 初始化
    if ! git init; then
        log_error "Git 初始化失敗"
        return 1
    fi
    
    # 添加遠端
    if ! git remote add origin "$repo_url" 2>/dev/null; then
        # 如果已存在，更新 URL
        git remote set-url origin "$repo_url"
    fi
    
    log_success "Git 初始化完成"
    return 0
}

# ===== Git 更新 =====
git_pull() {
    local project_dir="${1:-$PROJECT_DIR}"
    local branch="${2:-$GIT_BRANCH}"
    
    log_step "拉取最新代碼 (分支: $branch)..."
    
    cd "$project_dir" || return 1
    
    # 確保權限
    sudo chown -R $USER:$USER "$project_dir"
    git_set_safe_directory "$project_dir"
    
    if git pull origin "$branch"; then
        log_success "代碼更新完成"
        return 0
    else
        log_error "代碼更新失敗"
        return 1
    fi
}

git_fetch() {
    local project_dir="${1:-$PROJECT_DIR}"
    
    log_step "獲取遠端更新..."
    
    cd "$project_dir" || return 1
    git_set_safe_directory "$project_dir"
    
    if git fetch origin; then
        log_success "遠端更新獲取完成"
        return 0
    else
        log_error "獲取遠端更新失敗"
        return 1
    fi
}

# ===== 分支管理 =====
git_checkout_branch() {
    local branch="$1"
    local project_dir="${2:-$PROJECT_DIR}"
    
    log_step "切換到分支: $branch"
    
    cd "$project_dir" || return 1
    
    # 首先嘗試切換到已存在的分支
    if git checkout "$branch" 2>/dev/null; then
        log_success "已切換到分支: $branch"
        return 0
    fi
    
    # 嘗試從遠端建立新分支
    if git checkout -b "$branch" "origin/$branch" 2>/dev/null; then
        log_success "已從遠端建立並切換到分支: $branch"
        return 0
    fi
    
    log_error "無法切換到分支: $branch"
    return 1
}

git_list_branches() {
    local project_dir="${1:-$PROJECT_DIR}"
    
    cd "$project_dir" || return 1
    
    echo ""
    echo "📋 本地分支:"
    git branch 2>/dev/null | sed 's/^/  /'
    
    echo ""
    echo "📋 遠端分支:"
    git branch -r 2>/dev/null | sed 's/^/  /'
}

git_current_branch() {
    local project_dir="${1:-$PROJECT_DIR}"
    
    cd "$project_dir" 2>/dev/null || return 1
    git rev-parse --abbrev-ref HEAD 2>/dev/null
}

# ===== Git 狀態 =====
git_status() {
    local project_dir="${1:-$PROJECT_DIR}"
    
    cd "$project_dir" || return 1
    
    echo ""
    echo "📋 Git 狀態:"
    git status
}

git_check_changes() {
    local project_dir="${1:-$PROJECT_DIR}"
    
    cd "$project_dir" || return 1
    
    # 檢查是否有未提交的變更
    if git diff --quiet && git diff --cached --quiet; then
        return 1  # 沒有變更
    else
        return 0  # 有變更
    fi
}

git_is_repo() {
    local dir="${1:-$PROJECT_DIR}"
    
    if [ -d "$dir/.git" ]; then
        return 0
    fi
    return 1
}

# ===== Git 恢復 =====
git_restore() {
    local project_dir="${1:-$PROJECT_DIR}"
    local repo_url="${2:-$GIT_REPO_URL}"
    local branch="${3:-$GIT_BRANCH}"
    
    log_step "恢復 Git 倉庫..."
    
    # 確保權限
    sudo chown -R $USER:$USER "$project_dir"
    
    cd "$project_dir" || return 1
    
    # 檢查是否已有 Git
    if git_is_repo "$project_dir"; then
        if confirm_action "Git 目錄已存在，是否要重新初始化?"; then
            log_step "移除現有 Git..."
            rm -rf .git
        else
            log_info "使用現有 Git 拉取更新..."
            git_pull "$project_dir" "$branch"
            return $?
        fi
    fi
    
    # 初始化
    git_init_existing "$project_dir" "$repo_url"
    
    # 獲取遠端
    if ! git fetch origin; then
        log_error "無法獲取遠端倉庫"
        return 1
    fi
    
    # 切換分支
    git_checkout_branch "$branch" "$project_dir"
    
    log_success "Git 恢復完成"
}

git_reset_hard() {
    local branch="${1:-$GIT_BRANCH}"
    local project_dir="${2:-$PROJECT_DIR}"
    
    log_warning "將重置到遠端分支狀態，本地變更將會遺失！"
    
    if ! confirm_action "確定要硬重置嗎?"; then
        log_info "已取消"
        return 1
    fi
    
    cd "$project_dir" || return 1
    
    git fetch origin
    git reset --hard "origin/$branch"
    
    log_success "已重置到 origin/$branch"
}

# ===== Git 配置 =====
git_set_safe_directory() {
    local dir="$1"
    git config --global --add safe.directory "$dir" 2>/dev/null || true
}

git_configure_user() {
    local name="$1"
    local email="$2"
    
    if [ -n "$name" ]; then
        git config --global user.name "$name"
    fi
    
    if [ -n "$email" ]; then
        git config --global user.email "$email"
    fi
    
    log_success "Git 使用者配置完成"
}

# ===== 互動式 Git 操作 =====
interactive_git_restore() {
    local project_dir="${1:-$PROJECT_DIR}"
    
    log_header "Git 倉庫恢復與同步"
    
    # 詢問倉庫 URL
    local repo_url=$(read_with_default "請輸入 Git 倉庫 URL" "$GIT_REPO_URL")
    
    # 詢問分支
    local branch=$(read_with_default "請輸入分支名稱" "$GIT_BRANCH")
    
    # 執行恢復
    git_restore "$project_dir" "$repo_url" "$branch"
}

interactive_git_pull() {
    local project_dir="${1:-$PROJECT_DIR}"
    
    log_header "Git Pull 更新"
    
    if ! git_is_repo "$project_dir"; then
        log_error "這不是 Git 倉庫，請先執行 Git 恢復"
        return 1
    fi
    
    # 顯示當前狀態
    git_status "$project_dir"
    
    echo ""
    
    # 詢問分支
    local current_branch=$(git_current_branch "$project_dir")
    local branch=$(read_with_default "請輸入要拉取的分支" "$current_branch")
    
    # 執行拉取
    git_pull "$project_dir" "$branch"
}

log_info "Git 模組已載入"
