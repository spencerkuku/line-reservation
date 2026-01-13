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

# ===== GitHub Release 部署 =====
download_github_release() {
    local repo="${1}"  # 格式: owner/repo
    local tag="${2:-latest}"
    local target_dir="${3:-$DEPLOY_TARGET_DIR}"
    
    log_step "下載 GitHub Release: $repo ($tag)"
    
    # 確保 jq 已安裝
    if ! command -v jq &>/dev/null; then
        log_step "安裝 jq..."
        sudo apt-get update -qq
        sudo apt-get install -y jq
    fi
    
    # 確保 curl 已安裝
    if ! command -v curl &>/dev/null; then
        log_error "需要 curl，請先安裝: sudo apt-get install curl"
        return 1
    fi
    
    local release_url
    if [ "$tag" = "latest" ]; then
        release_url="https://api.github.com/repos/$repo/releases/latest"
    else
        release_url="https://api.github.com/repos/$repo/releases/tags/$tag"
    fi
    
    log_step "獲取 Release 資訊..."
    local release_info=$(curl -sL "$release_url")
    
    if [ $? -ne 0 ] || [ -z "$release_info" ]; then
        log_error "無法獲取 Release 資訊"
        return 1
    fi
    
    # 檢查是否找到 release
    local error_msg=$(echo "$release_info" | jq -r '.message // empty' 2>/dev/null)
    if [ "$error_msg" = "Not Found" ]; then
        log_error "找不到指定的 Release: $tag"
        return 1
    fi
    
    # 獲取 tarball URL
    local tarball_url=$(echo "$release_info" | jq -r '.tarball_url')
    local release_tag=$(echo "$release_info" | jq -r '.tag_name')
    local release_name=$(echo "$release_info" | jq -r '.name // .tag_name')
    
    log_info "Release: $release_name ($release_tag)"
    
    # 創建臨時目錄
    local temp_dir=$(mktemp -d)
    local tar_file="$temp_dir/release.tar.gz"
    
    # 下載 tarball
    log_step "下載 Release 包..."
    if ! curl -sL -o "$tar_file" "$tarball_url"; then
        log_error "下載失敗"
        rm -rf "$temp_dir"
        return 1
    fi
    
    # 解壓縮
    log_step "解壓縮到 $target_dir..."
    
    # 備份現有目錄（如果存在）
    if [ -d "$target_dir" ]; then
        local backup_name="$target_dir.backup.$(date +%Y%m%d_%H%M%S)"
        log_step "備份現有目錄到: $backup_name"
        mv "$target_dir" "$backup_name"
    fi
    
    # 創建目標目錄
    mkdir -p "$target_dir"
    
    # 解壓（GitHub tarball 會在頂層創建一個目錄）
    tar -xzf "$tar_file" -C "$temp_dir"
    
    # 移動內容到目標目錄
    local extracted_dir=$(find "$temp_dir" -mindepth 1 -maxdepth 1 -type d | head -1)
    if [ -z "$extracted_dir" ]; then
        log_error "解壓縮失敗"
        rm -rf "$temp_dir"
        return 1
    fi
    
    # 移動所有文件
    mv "$extracted_dir"/* "$target_dir/"
    mv "$extracted_dir"/.[!.]* "$target_dir/" 2>/dev/null || true
    
    # 清理
    rm -rf "$temp_dir"
    
    # 設置擁有者
    sudo chown -R $USER:$USER "$target_dir"
    
    # 儲存 release 資訊
    echo "$release_tag" > "$target_dir/.release_version"
    
    log_success "Release 下載完成: $release_name"
    log_info "安裝路徑: $target_dir"
    
    return 0
}

get_current_release_version() {
    local project_dir="${1:-$PROJECT_DIR}"
    
    if [ -f "$project_dir/.release_version" ]; then
        cat "$project_dir/.release_version"
    else
        echo "unknown"
    fi
}

check_for_updates() {
    local repo="${1}"
    local current_version="${2}"
    
    log_step "檢查更新..."
    
    local latest_info=$(curl -sL "https://api.github.com/repos/$repo/releases/latest")
    local latest_tag=$(echo "$latest_info" | jq -r '.tag_name')
    
    if [ "$current_version" = "$latest_tag" ]; then
        log_success "已是最新版本: $current_version"
        return 1
    else
        log_info "當前版本: $current_version"
        log_info "最新版本: $latest_tag"
        return 0
    fi
}

# ===== 部署源管理 =====
deploy_from_source() {
    local source="${1:-$DEPLOY_SOURCE}"
    local target_dir="${2:-$PROJECT_DIR}"
    
    case "$source" in
        git)
            log_header "使用 Git 部署"
            if [ -d "$target_dir/.git" ]; then
                git_pull "$target_dir"
            else
                git_clone "$GIT_REPO_URL" "$target_dir" "$GIT_BRANCH"
            fi
            ;;
        release)
            log_header "使用 GitHub Release 部署"
            local repo=$(echo "$GIT_REPO_URL" | sed -E 's|https://github.com/||' | sed -E 's|\.git$||')
            download_github_release "$repo" "$GITHUB_RELEASE_TAG" "$target_dir"
            ;;
        *)
            log_error "未知的部署源: $source"
            return 1
            ;;
    esac
}

log_info "Git 模組已載入"
