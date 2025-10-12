# 日誌系統使用說明

本系統實現了兩個層級的日誌記錄：
1. **檔案日誌** - 記錄到 Laravel log 檔案
2. **資料庫日誌** - 記錄後台操作到資料庫

---

## 一、檔案日誌系統

### 1. 日誌頻道

系統已配置以下日誌頻道（位於 `config/logging.php`）：

- **daily** - 一般應用程式日誌（保留 14 天）
- **api** - API 請求/回應日誌（保留 30 天）
- **error** - 錯誤日誌（保留 90 天）
- **activity** - 系統操作日誌（保留 90 天）
- **linebot** - LINE Bot 專用日誌（保留 30 天）
- **reservations** - 預約系統日誌（保留 30 天）
- **security** - 安全事件日誌（保留 90 天）

### 2. 日誌格式

所有日誌都使用統一格式：
```
[2025-10-12 10:30:45] [INFO] [api] 訊息內容 {"context":"資料"} {"ip":"127.0.0.1","user_id":"1","url":"...","method":"GET"}
```

### 3. 使用方式

```php
use Illuminate\Support\Facades\Log;

// 記錄到特定頻道
Log::channel('api')->info('API 請求', ['method' => 'GET', 'url' => '/api/users']);
Log::channel('error')->error('發生錯誤', ['exception' => $e->getMessage()]);
Log::channel('activity')->info('用戶登入', ['user_id' => 1]);
```

### 4. API 日誌中介層

所有 API 請求會自動記錄：
- 請求資訊（方法、URL、IP、User Agent）
- 請求 Body（敏感資料已過濾）
- 回應狀態碼
- 執行時間

### 5. 異常處理

所有異常會自動記錄到 `error` 頻道，包含：
- 異常類型
- 錯誤訊息
- 檔案和行號
- Stack Trace
- 請求資訊

---

## 二、資料庫日誌系統（後台操作記錄）

### 1. 資料表結構

`admin_activity_logs` 表包含以下欄位：
- 操作者資訊（user_id, user_name, user_email）
- 操作資訊（action, module, description）
- 操作對象（subject_type, subject_id, subject_data）
- 變更內容（old_values, new_values）
- 請求資訊（ip_address, user_agent, method, url）
- 狀態（status, error_message）

### 2. ActivityLogger Service

統一的日誌記錄服務：

#### 基本使用

```php
use App\Services\ActivityLogger;

// 自動記錄建立操作
ActivityLogger::created($model, 'module_name');

// 自動記錄更新操作
ActivityLogger::updated($model, 'module_name', $oldValues);

// 自動記錄刪除操作
ActivityLogger::deleted($model, 'module_name');

// 記錄登入
ActivityLogger::login($user);

// 記錄登出
ActivityLogger::logout();

// 記錄批次操作
ActivityLogger::bulkAction('bulk_delete', 'services', [1, 2, 3], '批次刪除 3 個服務');

// 記錄失敗操作
ActivityLogger::failed('create', 'services', '建立服務失敗', $exception);

// 記錄自訂操作
ActivityLogger::custom('export', 'reports', '匯出報表', ['format' => 'xlsx']);
```

#### 在 Controller 中使用

```php
public function store(Request $request)
{
    try {
        $service = Service::create($request->validated());
        
        // 記錄建立操作
        ActivityLogger::created($service, 'services');
        
        return response()->json(['success' => true, 'data' => $service]);
    } catch (\Exception $e) {
        // 記錄失敗操作
        ActivityLogger::failed('create', 'services', '建立服務失敗', $e);
        throw $e;
    }
}

public function update(Request $request, Service $service)
{
    try {
        $oldValues = $service->getOriginal();
        
        $service->update($request->validated());
        
        // 記錄更新操作
        ActivityLogger::updated($service, 'services', $oldValues);
        
        return response()->json(['success' => true, 'data' => $service]);
    } catch (\Exception $e) {
        ActivityLogger::failed('update', 'services', "更新服務失敗: {$service->name}", $e);
        throw $e;
    }
}

public function destroy(Service $service)
{
    try {
        // 記錄刪除操作（在刪除前）
        ActivityLogger::deleted($service, 'services');
        
        $service->delete();
        
        return response()->json(['success' => true]);
    } catch (\Exception $e) {
        ActivityLogger::failed('delete', 'services', "刪除服務失敗: {$service->name}", $e);
        throw $e;
    }
}
```

### 3. 模組和操作類型

#### 建議的模組 (module)
- `auth` - 認證相關
- `users` - 用戶管理
- `services` - 服務管理
- `available_times` - 時段管理
- `reservations` - 預約管理
- `customers` - 客戶管理
- `settings` - 系統設定
- `line` - LINE 整合
- `reports` - 報表

#### 建議的操作類型 (action)
- `created` - 建立
- `updated` - 更新
- `deleted` - 刪除
- `login` - 登入
- `logout` - 登出
- `confirmed` - 確認
- `cancelled` - 取消
- `bulk_delete` - 批次刪除
- `bulk_update` - 批次更新
- `export` - 匯出
- `import` - 匯入
- `status_changed` - 狀態變更

### 4. 查詢活動日誌 API

#### 取得活動日誌列表
```
GET /api/admin/activity-logs

參數：
- module: 篩選模組
- action: 篩選操作
- user_id: 篩選使用者
- date_from: 開始日期
- date_to: 結束日期
- status: 篩選狀態 (success/failed)
- search: 搜尋關鍵字
- per_page: 每頁筆數（預設 20）
```

#### 取得單一日誌詳情
```
GET /api/admin/activity-logs/{id}
```

#### 取得統計資訊
```
GET /api/admin/activity-logs/stats

參數：
- date_from: 開始日期（預設 30 天前）
- date_to: 結束日期（預設今天）

回應：
- total_activities: 總活動數
- by_module: 按模組統計
- by_action: 按操作統計
- by_user: 按用戶統計
- failed_operations: 失敗操作數
- recent_activities: 最近活動
```

#### 取得每日趨勢
```
GET /api/admin/activity-logs/trends

參數：
- days: 天數（預設 30）
```

#### 取得可用模組列表
```
GET /api/admin/activity-logs/modules
```

#### 取得可用操作列表
```
GET /api/admin/activity-logs/actions
```

---

## 三、已實現的日誌記錄

### 1. 認證相關
- ✅ 用戶登入
- ✅ 用戶登出
- ✅ 登入失敗（透過 SecurityLoggingService）

### 2. 用戶管理
- ✅ 建立用戶
- ✅ 更新用戶
- ✅ 刪除用戶
- ✅ 更新用戶狀態

### 3. 服務管理
- ✅ 建立服務
- ✅ 更新服務
- ✅ 刪除服務

### 4. 預約管理
- ✅ 建立預約
- ✅ 確認預約
- ✅ 取消預約

---

## 四、日誌檔案位置

所有日誌檔案位於 `storage/logs/` 目錄：

```
storage/logs/
├── laravel.log         # 一般應用程式日誌
├── api.log            # API 日誌
├── error.log          # 錯誤日誌
├── activity.log       # 操作日誌
├── linebot.log        # LINE Bot 日誌
├── reservations.log   # 預約系統日誌
└── security.log       # 安全事件日誌
```

---

## 五、最佳實踐

1. **敏感資料過濾**
   - 密碼、Token、API Key 等敏感資料會自動過濾
   - 不要在日誌中記錄完整的信用卡號、身分證號等

2. **日誌等級**
   - `debug` - 開發除錯訊息
   - `info` - 一般資訊（正常操作）
   - `warning` - 警告（可能的問題）
   - `error` - 錯誤（需要注意）
   - `critical` - 嚴重錯誤（需要立即處理）

3. **效能考量**
   - 資料庫日誌記錄失敗不會影響主要業務邏輯
   - 日誌記錄包含 try-catch，確保不會中斷程式執行

4. **定期清理**
   - 檔案日誌會根據配置的天數自動清理
   - 資料庫日誌建議定期清理或歸檔

---

## 六、監控建議

1. **錯誤監控**
   - 定期檢查 `error.log`
   - 監控資料庫中 `status = 'failed'` 的操作記錄

2. **安全監控**
   - 監控異常登入行為
   - 監控大量失敗操作
   - 監控權限變更

3. **效能監控**
   - 監控 API 執行時間（從 `api.log`）
   - 監控慢查詢

---

## 七、故障排除

### 日誌未寫入檔案
1. 檢查 `storage/logs` 目錄權限
2. 確認 `.env` 中 `LOG_CHANNEL` 設定正確
3. 檢查磁碟空間

### 資料庫日誌未記錄
1. 確認已執行 migration
2. 檢查資料庫連線
3. 查看 `error.log` 是否有錯誤訊息

### 日誌檔案過大
1. 調整 `config/logging.php` 中的 `days` 設定
2. 考慮使用日誌輪轉工具（如 logrotate）
3. 設定適當的日誌等級（生產環境避免使用 `debug`）
