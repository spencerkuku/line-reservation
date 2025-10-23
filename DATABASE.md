# 資料庫文件 (Database Documentation)

## 📋 目錄

- [資料庫概覽](#資料庫概覽)
- [ERD 關係圖](#erd-關係圖)
- [資料表結構](#資料表結構)
- [資料關聯](#資料關聯)
- [索引策略](#索引策略)
- [資料字典](#資料字典)
- [遷移管理](#遷移管理)

## 🗄️ 資料庫概覽

### 基本資訊

- **資料庫引擎**: MySQL 8.0+
- **字元編碼**: utf8mb4
- **排序規則**: utf8mb4_unicode_ci
- **時區**: Asia/Taipei
- **資料庫名稱**: line_reservation

### 資料表清單

| 資料表名稱 | 說明 | 記錄數量(約) |
|-----------|------|-------------|
| `users` | 系統用戶（管理員） | 1-10 |
| `customers` | LINE 客戶資料 | 100-10000 |
| `services` | 服務項目 | 5-50 |
| `available_times` | 可預約時段 | 50-500 |
| `reservations` | 預約記錄 | 1000-100000 |
| `settings` | 系統設定 | 10-50 |
| `line_message_logs` | LINE 訊息日誌 | 10000+ |
| `admin_activity_logs` | 管理員操作日誌 | 1000+ |
| `cache` | Laravel 快取 | 變動 |
| `jobs` | 佇列任務 | 變動 |
| `personal_access_tokens` | API Tokens | 10-100 |

## 📊 ERD 關係圖

```
┌──────────────┐         ┌──────────────┐
│    users     │         │  customers   │
│──────────────│         │──────────────│
│ id (PK)      │         │ id (PK)      │
│ name         │         │ line_user_id │
│ email        │         │ name         │
│ password     │         │ phone        │
│ role         │         │ email        │
│ status       │         │ gender       │
└──────┬───────┘         │ birthday     │
       │                 │ status       │
       │                 │ ...          │
       │                 └──────┬───────┘
       │                        │
       │                        │ 1:N
       │                        │
       │                 ┌──────▼────────┐
       │                 │ reservations  │
       │         ┌───────│───────────────│
       │         │       │ id (PK)       │
       │  1:N    │       │ customer_id(FK)
       │         │       │ service_id(FK)│
       └─────────┼───────│ check_in_by(FK)
                 │       │ available_time_id(FK)
                 │       │ reservation_date
                 │       │ reservation_time
                 │       │ status        │
                 │       │ check_in_status
                 │       │ payment_status│
                 │       │ ...           │
                 │       └──────┬────┬───┘
                 │              │    │
        ┌────────┴─────┐   1:N  │    │ N:1
        │   services   │────────┘    │
        │──────────────│              │
        │ id (PK)      │         ┌────▼──────────┐
        │ name         │         │available_times│
        │ description  │         │───────────────│
        │ duration     │         │ id (PK)       │
        │ price        │         │ title         │
        │ is_active    │         │ start_time    │
        └──────────────┘         │ end_time      │
                                 │ max_capacity  │
                                 │ current_bookings
                                 └───────────────┘

┌──────────────────┐      ┌────────────────────┐
│    settings      │      │ line_message_logs  │
│──────────────────│      │────────────────────│
│ id (PK)          │      │ id (PK)            │
│ key              │      │ line_user_id       │
│ value            │      │ message_type       │
│ type             │      │ message_content    │
└──────────────────┘      │ bot_response       │
                          │ direction          │
                          └────────────────────┘

┌──────────────────────┐
│ admin_activity_logs  │
│──────────────────────│
│ id (PK)              │
│ user_id (FK)         │
│ module               │
│ action               │
│ description          │
│ old_values           │
│ new_values           │
│ ip_address           │
└──────────────────────┘
```

## 📑 資料表結構

### 1. users - 系統用戶表

管理員和系統用戶資料。

```sql
CREATE TABLE `users` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) NOT NULL UNIQUE,
  `email_verified_at` TIMESTAMP NULL,
  `password` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(255) NULL,
  `role` ENUM('admin', 'user') DEFAULT 'user',
  `status` ENUM('Active', 'Inactive', 'Banned') DEFAULT 'Active',
  `line_user_id` VARCHAR(255) NULL UNIQUE,
  `avatar` VARCHAR(255) NULL,
  `remember_token` VARCHAR(100) NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  
  INDEX `users_role_index` (`role`),
  INDEX `users_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**欄位說明**:
- `id`: 主鍵
- `name`: 用戶姓名
- `email`: 電子郵件（登入帳號）
- `password`: 加密後的密碼
- `role`: 角色 (admin=管理員, user=一般用戶)
- `status`: 狀態 (Active=啟用, Inactive=停用, Banned=封禁)
- `line_user_id`: LINE 用戶 ID（可選）
- `avatar`: 頭像 URL

### 2. customers - 客戶資料表

LINE Bot 用戶的客戶資料。

```sql
CREATE TABLE `customers` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `line_user_id` VARCHAR(255) NULL UNIQUE,
  `name` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(255) NULL,
  `email` VARCHAR(255) NULL,
  `gender` ENUM('male', 'female', 'other') NULL,
  `birthday` DATE NULL,
  `address` TEXT NULL,
  `notes` TEXT NULL,
  `status` ENUM('active', 'inactive', 'blocked') DEFAULT 'active',
  `preferences` JSON NULL,
  `last_interaction_at` TIMESTAMP NULL,
  `referral_source` VARCHAR(255) NULL,
  `total_reservations` INT DEFAULT 0,
  `total_spent` DECIMAL(10, 2) DEFAULT 0,
  `line_display_name` VARCHAR(255) NULL,
  `line_picture_url` VARCHAR(500) NULL,
  `blocked_at` TIMESTAMP NULL,
  `blocked_reason` TEXT NULL,
  `unblocked_at` TIMESTAMP NULL,
  `deleted_at` TIMESTAMP NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  
  INDEX `customers_status_created_at_index` (`status`, `created_at`),
  INDEX `customers_phone_index` (`phone`),
  INDEX `customers_email_index` (`email`),
  INDEX `customers_last_interaction_at_index` (`last_interaction_at`),
  INDEX `customers_deleted_at_index` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**欄位說明**:
- `id`: 主鍵
- `line_user_id`: LINE 平台的用戶 ID
- `name`: 客戶姓名
- `phone`: 聯絡電話
- `email`: 電子郵件
- `gender`: 性別 (male=男, female=女, other=其他)
- `status`: 狀態 (active=啟用, inactive=停用, blocked=封鎖)
- `preferences`: 偏好設定（JSON 格式）
- `total_reservations`: 累計預約次數
- `total_spent`: 累計消費金額
- `blocked_at`: 封鎖時間
- `deleted_at`: 軟刪除時間

### 3. services - 服務項目表

提供的服務項目列表。

```sql
CREATE TABLE `services` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `description` TEXT NOT NULL,
  `duration` INT NOT NULL COMMENT '服務時長（分鐘）',
  `price` DECIMAL(8, 2) NULL,
  `image_url` VARCHAR(255) NULL,
  `is_active` BOOLEAN DEFAULT TRUE,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  
  INDEX `services_is_active_index` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**欄位說明**:
- `id`: 主鍵
- `name`: 服務名稱
- `description`: 服務說明
- `duration`: 服務時長（分鐘）
- `price`: 服務價格
- `is_active`: 是否啟用

### 4. available_times - 可預約時段表

系統提供的可預約時段。

```sql
CREATE TABLE `available_times` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NULL,
  `description` TEXT NULL,
  `start_time` DATETIME NOT NULL,
  `end_time` DATETIME NOT NULL,
  `max_capacity` INT DEFAULT 1 COMMENT '最大容量',
  `current_bookings` INT DEFAULT 0 COMMENT '當前預約數',
  `is_active` BOOLEAN DEFAULT TRUE,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  
  INDEX `available_times_start_time_index` (`start_time`),
  INDEX `available_times_end_time_index` (`end_time`),
  INDEX `available_times_is_active_index` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**欄位說明**:
- `id`: 主鍵
- `title`: 時段標題
- `start_time`: 開始時間
- `end_time`: 結束時間
- `max_capacity`: 最大容量
- `current_bookings`: 當前預約數
- `is_active`: 是否啟用

### 5. reservations - 預約記錄表

**最核心的資料表，記錄所有預約資訊。**

```sql
CREATE TABLE `reservations` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `customer_id` BIGINT UNSIGNED NULL,
  `service_id` BIGINT UNSIGNED NOT NULL,
  `available_time_id` BIGINT UNSIGNED NULL,
  
  -- 預約基本資訊
  `reservation_date` DATE NOT NULL,
  `reservation_time` TIME NOT NULL,
  `status` ENUM('pending', 'confirmed', 'completed', 'cancelled') DEFAULT 'pending',
  `notes` TEXT NULL COMMENT '管理員備註',
  `confirmed_at` TIMESTAMP NULL,
  `cancelled_at` TIMESTAMP NULL,
  
  -- 預約時的客戶資訊快照
  `reservation_name` VARCHAR(255) NULL,
  `reservation_phone` VARCHAR(255) NULL,
  `reservation_notes` TEXT NULL COMMENT '客戶備註',
  
  -- 報到相關
  `check_in_status` ENUM('pending', 'checked_in', 'no_show', 'late') DEFAULT 'pending',
  `check_in_time` TIMESTAMP NULL,
  `check_in_by` BIGINT UNSIGNED NULL COMMENT '報到操作人員',
  `no_show` BOOLEAN DEFAULT FALSE,
  
  -- 付款相關
  `payment_status` ENUM('unpaid', 'partial', 'paid', 'refunded') DEFAULT 'unpaid',
  `payment_method` ENUM('cash', 'credit_card', 'debit_card', 'transfer', 'line_pay', 'other') NULL,
  `payment_amount` DECIMAL(10, 2) DEFAULT 0,
  `payment_time` TIMESTAMP NULL,
  `payment_note` TEXT NULL,
  
  `deleted_at` TIMESTAMP NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  
  -- 外鍵約束
  FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`available_time_id`) REFERENCES `available_times` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`check_in_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  
  -- 索引
  INDEX `reservations_customer_id_index` (`customer_id`),
  INDEX `reservations_service_id_index` (`service_id`),
  INDEX `reservations_available_time_id_index` (`available_time_id`),
  INDEX `reservations_reservation_date_index` (`reservation_date`),
  INDEX `reservations_status_index` (`status`),
  INDEX `reservations_check_in_status_index` (`check_in_status`),
  INDEX `reservations_payment_status_index` (`payment_status`),
  INDEX `reservations_deleted_at_index` (`deleted_at`),
  
  -- 複合索引（效能優化）
  INDEX `reservations_date_status_index` (`reservation_date`, `status`),
  INDEX `reservations_customer_status_index` (`customer_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**重要欄位說明**:

**預約狀態** (`status`):
- `pending`: 待確認
- `confirmed`: 已確認
- `completed`: 已完成
- `cancelled`: 已取消

**報到狀態** (`check_in_status`):
- `pending`: 待報到
- `checked_in`: 已報到
- `late`: 遲到（超過預約時間15分鐘）
- `no_show`: 爽約

**付款狀態** (`payment_status`):
- `unpaid`: 未付款
- `partial`: 部分付款
- `paid`: 已付款
- `refunded`: 已退款

**付款方式** (`payment_method`):
- `cash`: 現金
- `credit_card`: 信用卡
- `debit_card`: 金融卡
- `transfer`: 轉帳
- `line_pay`: LINE Pay
- `other`: 其他

### 6. settings - 系統設定表

系統配置參數。

```sql
CREATE TABLE `settings` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `key` VARCHAR(255) NOT NULL UNIQUE,
  `value` TEXT NULL,
  `type` VARCHAR(255) DEFAULT 'string' COMMENT 'string, json, boolean, integer',
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**常用設定項**:
- `line_channel_access_token`: LINE Channel Access Token
- `line_channel_secret`: LINE Channel Secret
- `business_hours`: 營業時間（JSON）
- `booking_advance_days`: 可預約天數
- `auto_confirm_booking`: 自動確認預約

### 7. line_message_logs - LINE 訊息日誌

記錄所有 LINE Bot 的訊息互動。

```sql
CREATE TABLE `line_message_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `line_user_id` VARCHAR(255) NOT NULL,
  `message_type` VARCHAR(255) NOT NULL COMMENT 'text, image, location, etc.',
  `message_content` TEXT NOT NULL,
  `bot_response` TEXT NULL,
  `direction` ENUM('incoming', 'outgoing') NOT NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  
  INDEX `line_message_logs_line_user_id_index` (`line_user_id`),
  INDEX `line_message_logs_created_at_index` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 8. admin_activity_logs - 管理員操作日誌

記錄所有管理員的操作行為。

```sql
CREATE TABLE `admin_activity_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `user_id` BIGINT UNSIGNED NULL,
  `module` VARCHAR(255) NOT NULL COMMENT '模組名稱: customer, reservation, service等',
  `action` VARCHAR(255) NOT NULL COMMENT '操作: create, update, delete等',
  `description` TEXT NOT NULL,
  `old_values` JSON NULL COMMENT '變更前的值',
  `new_values` JSON NULL COMMENT '變更後的值',
  `ip_address` VARCHAR(45) NULL,
  `user_agent` TEXT NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  
  INDEX `admin_activity_logs_user_id_index` (`user_id`),
  INDEX `admin_activity_logs_module_index` (`module`),
  INDEX `admin_activity_logs_action_index` (`action`),
  INDEX `admin_activity_logs_created_at_index` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## 🔗 資料關聯

### 關聯類型說明

```
users (1) ----< (N) reservations
  - 一個用戶可以操作多筆預約的報到

customers (1) ----< (N) reservations
  - 一個客戶可以有多筆預約

services (1) ----< (N) reservations
  - 一個服務可以被多筆預約使用

available_times (1) ----< (N) reservations
  - 一個時段可以對應多筆預約

users (1) ----< (N) admin_activity_logs
  - 一個用戶可以產生多筆操作日誌
```

### Eloquent 關聯定義

#### Reservation Model

```php
class Reservation extends Model
{
    // 預約所屬的客戶
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
    
    // 預約的服務
    public function service()
    {
        return $this->belongsTo(Service::class);
    }
    
    // 預約的時段
    public function availableTime()
    {
        return $this->belongsTo(AvailableTime::class);
    }
    
    // 報到操作人員
    public function checkInUser()
    {
        return $this->belongsTo(User::class, 'check_in_by');
    }
}
```

#### Customer Model

```php
class Customer extends Model
{
    // 客戶的所有預約
    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }
}
```

## 📈 索引策略

### 主要索引

| 資料表 | 索引類型 | 欄位 | 用途 |
|--------|---------|------|------|
| `users` | UNIQUE | `email` | 登入查詢 |
| `customers` | UNIQUE | `line_user_id` | LINE 用戶查詢 |
| `customers` | INDEX | `phone` | 電話搜尋 |
| `customers` | INDEX | `status`, `created_at` | 狀態篩選與排序 |
| `reservations` | INDEX | `customer_id` | 客戶預約查詢 |
| `reservations` | INDEX | `reservation_date` | 日期查詢 |
| `reservations` | INDEX | `reservation_date`, `status` | 日期與狀態查詢 |
| `reservations` | INDEX | `check_in_status` | 報到狀態查詢 |
| `available_times` | INDEX | `start_time`, `end_time` | 時段查詢 |

### 效能優化建議

```sql
-- 1. 分析慢查詢
SHOW PROCESSLIST;
SHOW FULL PROCESSLIST;

-- 2. 查看索引使用情況
EXPLAIN SELECT * FROM reservations 
WHERE reservation_date = '2025-10-25' AND status = 'confirmed';

-- 3. 優化建議
-- 為常用查詢添加複合索引
CREATE INDEX idx_reservation_date_status 
ON reservations (reservation_date, status);

-- 為全文搜尋添加索引
CREATE FULLTEXT INDEX idx_customers_search 
ON customers (name, phone, email);
```

## 📚 資料字典

### Enum 值定義

#### 用戶角色 (users.role)
```php
'admin'  => '管理員'
'user'   => '一般用戶'
```

#### 用戶狀態 (users.status)
```php
'Active'   => '啟用'
'Inactive' => '停用'
'Banned'   => '封禁'
```

#### 客戶狀態 (customers.status)
```php
'active'   => '啟用'
'inactive' => '停用'
'blocked'  => '封鎖'
```

#### 性別 (customers.gender)
```php
'male'   => '男性'
'female' => '女性'
'other'  => '其他'
```

### 預設值

- 預約狀態預設: `pending`
- 報到狀態預設: `pending`
- 付款狀態預設: `unpaid`
- 客戶狀態預設: `active`
- 服務啟用預設: `true`

## 🔄 遷移管理

### 執行遷移

```bash
# 查看遷移狀態
php artisan migrate:status

# 執行所有待執行的遷移
php artisan migrate

# 執行特定遷移
php artisan migrate --path=/database/migrations/2025_10_23_000000_create_example_table.php

# 回滾最後一批遷移
php artisan migrate:rollback

# 回滾所有遷移
php artisan migrate:reset

# 重置並重新執行所有遷移
php artisan migrate:fresh

# 重置並執行 Seeder
php artisan migrate:fresh --seed
```

### 創建新遷移

```bash
# 創建資料表遷移
php artisan make:migration create_table_name_table

# 修改資料表遷移
php artisan make:migration add_column_to_table_name_table

# 刪除資料表遷移
php artisan make:migration drop_table_name_table
```

### 遷移順序

遷移檔案按時間戳排序執行：

```
1. 0001_01_01_000000_create_users_table
2. 2025_07_05_120000_create_reservation_system_tables
3. 2025_07_06_032102_create_customers_table
4. 2025_07_06_032412_add_customer_id_to_reservations_table
5. 2025_10_03_000001_add_check_in_and_payment_to_reservations
...
```

## 🔒 資料庫安全

### 備份策略

```bash
# 備份整個資料庫
mysqldump -u username -p line_reservation > backup_$(date +%Y%m%d).sql

# 備份特定資料表
mysqldump -u username -p line_reservation reservations customers > backup_main_$(date +%Y%m%d).sql

# 還原備份
mysql -u username -p line_reservation < backup_20251023.sql
```

### 權限設定

```sql
-- 創建專用資料庫用戶
CREATE USER 'line_app'@'localhost' IDENTIFIED BY 'strong_password';

-- 授予必要權限
GRANT SELECT, INSERT, UPDATE, DELETE ON line_reservation.* TO 'line_app'@'localhost';

-- 不授予 DROP, ALTER 等危險權限
-- 這些權限僅保留給 root 或 DBA

FLUSH PRIVILEGES;
```

### 資料清理

```sql
-- 清理 90 天前的 LINE 訊息日誌
DELETE FROM line_message_logs 
WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);

-- 清理 180 天前的活動日誌
DELETE FROM admin_activity_logs 
WHERE created_at < DATE_SUB(NOW(), INTERVAL 180 DAY);

-- 軟刪除的資料永久刪除（30天後）
DELETE FROM reservations 
WHERE deleted_at IS NOT NULL 
AND deleted_at < DATE_SUB(NOW(), INTERVAL 30 DAY);
```

## 📊 查詢範例

### 常用查詢

```sql
-- 1. 今日預約列表
SELECT r.*, c.name as customer_name, s.name as service_name
FROM reservations r
LEFT JOIN customers c ON r.customer_id = c.id
LEFT JOIN services s ON r.service_id = s.id
WHERE r.reservation_date = CURDATE()
ORDER BY r.reservation_time;

-- 2. 客戶預約歷史
SELECT * FROM reservations
WHERE customer_id = 1
ORDER BY reservation_date DESC, reservation_time DESC;

-- 3. 服務熱門度統計
SELECT s.name, COUNT(r.id) as booking_count
FROM services s
LEFT JOIN reservations r ON s.id = r.service_id
WHERE r.status != 'cancelled'
GROUP BY s.id
ORDER BY booking_count DESC;

-- 4. 月收入統計
SELECT 
  DATE_FORMAT(payment_time, '%Y-%m') as month,
  SUM(payment_amount) as total_revenue
FROM reservations
WHERE payment_status = 'paid'
GROUP BY month
ORDER BY month DESC;

-- 5. 爽約率統計
SELECT 
  COUNT(*) as total_reservations,
  SUM(CASE WHEN no_show = 1 THEN 1 ELSE 0 END) as no_shows,
  ROUND(SUM(CASE WHEN no_show = 1 THEN 1 ELSE 0 END) / COUNT(*) * 100, 2) as no_show_rate
FROM reservations
WHERE reservation_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY);
```

---

**文件版本**: v1.0.0  
**最後更新**: 2025-10-23  
**維護者**: 傅盛祥 (Spencer Kuku)