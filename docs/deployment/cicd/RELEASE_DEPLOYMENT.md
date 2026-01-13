# Release-Based 部署指南

## 概述

LINE Reservation 部署系統現在支援兩種代碼部署方式：

1. **GitHub Release** (推薦用於生產環境)
2. **Git Repository** (適合開發/測試環境)

## 部署方式對比

| 特性 | GitHub Release | Git Repository |
|------|----------------|----------------|
| **穩定性** | ✅ 高 - 使用經過測試的發行版本 | ⚠️ 中 - 可能包含開發中的代碼 |
| **速度** | ✅ 快 - 直接下載打包文件 | ⚠️ 慢 - 需要克隆完整 Git 歷史 |
| **所需工具** | curl, jq | Git |
| **更新方式** | 下載新版本 release | git pull |
| **回滾** | ✅ 容易 - 下載特定版本 | ⚠️ 需要 git 操作 |
| **適用場景** | 生產環境 | 開發/測試環境 |

## 使用方式

### 方式一：互動式部署

運行部署腳本：

```bash
cd docs/deployment/cicd
./deploy.sh
```

部署流程：

1. **選擇部署模式**
   - 統一部署 (前後端同伺服器)
   - 純 API 模式 (Headless)

2. **選擇部署來源**
   - 📦 GitHub Release (推薦)
   - 🔧 Git Repository

3. **配置部署參數**
   - Release 版本 (如: v1.0.0 或 latest)
   - 安裝目錄 (默認: ~/line-reservation)
   - 域名/IP
   - SSL 設置

### 方式二：命令行部署

#### 使用 GitHub Release 部署

```bash
# 部署最新版本到默認目錄
./deploy.sh --unified --domain=example.com --ssl \
  --source=release

# 部署特定版本
./deploy.sh --unified --domain=example.com --ssl \
  --source=release --tag=v1.0.0

# 指定安裝目錄
./deploy.sh --unified --domain=example.com --ssl \
  --source=release --tag=latest \
  --target=/home/user/my-app
```

#### 使用 Git Repository 部署

```bash
# 從 Git 倉庫部署
./deploy.sh --unified --domain=example.com --ssl \
  --source=git
```

#### API 模式部署

```bash
# API 模式 + Release
./deploy.sh --api-only \
  --domain=api.example.com \
  --cloudflare=app.pages.dev \
  --ssl \
  --source=release --tag=v1.0.0
```

## 安裝位置

### GitHub Release 部署

- **默認位置**: `~/line-reservation`
- **優點**: 
  - 安裝在用戶目錄，無需 sudo 權限
  - 每個用戶可以有自己的獨立實例
  - 便於管理和備份

### Git Repository 部署

- **位置**: 由 `config.sh` 中的 `PROJECT_DIR` 決定
- **默認**: `~/line-reservation`

## Release 版本管理

### 查看當前版本

項目目錄下的 `.release_version` 文件記錄了當前安裝的 release 版本：

```bash
cat ~/line-reservation/.release_version
```

### 更新到新版本

重新運行部署腳本並選擇新版本：

```bash
./deploy.sh --unified --domain=example.com \
  --source=release --tag=v2.0.0
```

系統會自動：
1. 備份當前版本到 `line-reservation.backup.YYYYMMDD_HHMMSS`
2. 下載並安裝新版本
3. 更新 `.release_version` 文件

### 回滾到舊版本

```bash
# 回滾到特定版本
./deploy.sh --unified --domain=example.com \
  --source=release --tag=v1.0.0

# 或手動恢復備份
mv ~/line-reservation ~/line-reservation.current
mv ~/line-reservation.backup.20260113_120000 ~/line-reservation
```

## 配置持久化

部署配置會保存在 `~/.line-reservation-config`：

```bash
# 查看當前配置
cat ~/.line-reservation-config
```

配置項包括：
- `DEPLOY_SOURCE`: 部署來源 (git 或 release)
- `GITHUB_RELEASE_TAG`: Release 版本標籤
- `DEPLOY_TARGET_DIR`: 安裝目錄
- 其他部署參數

## 常見問題

### Q: 如何查看可用的 Release 版本？

訪問 GitHub Release 頁面：
```
https://github.com/spencerkuku/line-reservation/releases
```

或使用 API：
```bash
curl -s https://api.github.com/repos/spencerkuku/line-reservation/releases | jq -r '.[].tag_name'
```

### Q: Release 部署需要 Git 嗎？

不需要。Release 部署只需要 `curl` 和 `jq`，系統會自動安裝。

### Q: 可以同時使用兩種部署方式嗎？

可以，但要安裝到不同目錄：

```bash
# Git 版本用於開發
./deploy.sh --source=git --target=~/line-reservation-dev

# Release 版本用於生產
./deploy.sh --source=release --target=~/line-reservation-prod
```

### Q: Release 部署如何處理數據庫？

與 Git 部署相同：
1. 首次部署會創建數據庫
2. 更新時會自動運行 migration
3. 數據庫數據不受影響

### Q: 備份在哪裡？

- **自動備份**: `<原目錄>.backup.YYYYMMDD_HHMMSS`
- **手動備份**: 使用 `manage.sh` 的備份功能

## 最佳實踐

### 生產環境

1. ✅ 使用 GitHub Release
2. ✅ 指定具體版本號 (避免使用 latest)
3. ✅ 部署前先在測試環境驗證
4. ✅ 定期備份數據庫
5. ✅ 保留至少一個舊版本備份

```bash
./deploy.sh --unified --domain=prod.example.com --ssl \
  --source=release --tag=v1.2.3
```

### 開發/測試環境

1. ✅ 可使用 Git Repository
2. ✅ 使用獨立的域名和數據庫
3. ✅ 可以頻繁更新

```bash
./deploy.sh --unified --domain=dev.example.com \
  --source=git
```

## 命令參考

### 完整命令行參數

```bash
./deploy.sh [選項]

部署模式:
  --unified              統一部署 (前後端同伺服器)
  --api-only             純 API 模式

域名配置:
  --domain=<域名>        後端域名
  --cloudflare=<域名>    Cloudflare Pages 域名 (API 模式需要)

SSL 配置:
  --ssl                  啟用 SSL
  --no-ssl               禁用 SSL

部署來源:
  --source=<git|release> 部署來源 (默認: release)
  --tag=<版本>           Release 版本 (默認: latest)
  --target=<目錄>        安裝目錄 (默認: ~/line-reservation)

其他:
  --help, -h             顯示幫助
```

## 技術細節

### Release 下載流程

1. 使用 GitHub API 獲取 Release 信息
2. 下載 tarball (壓縮包)
3. 解壓到臨時目錄
4. 移動文件到目標目錄
5. 設置權限和保存版本信息

### 文件結構

```
~/line-reservation/
├── .release_version        # 當前版本標記
├── backend/                # Laravel 後端
├── frontend/               # Vue.js 前端
└── docs/                   # 文檔
```

## 更多資源

- [主要部署文檔](README.md)
- [維護指南](../../maintenance/MAINTENANCE.md)
- [故障排除](README.md#故障排除)
