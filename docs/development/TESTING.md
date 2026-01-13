# LINE 預約系統 - 測試指南

> 確保多租戶系統的品質與穩定性

## 目錄

- [測試概覽](#測試概覽)
- [後端測試 (PHPUnit)](#後端測試-phpunit)
- [租戶隔離測試](#租戶隔離測試)
- [LINE Bot 測試](#line-bot-測試)
- [前端測試](#前端測試)
- [API 測試](#api-測試)
- [測試最佳實踐](#測試最佳實踐)

---

## 🧪 測試概覽

### 測試策略

```
測試金字塔:
        ▲
       ╱ ╲ 
      ╱E2E╲        少量（10%）- 端到端測試
     ╱─────╲
    ╱  整合  ╲      中量（30%）- 整合測試
   ╱─────────╲
  ╱   單元測試  ╲    大量（60%）- 單元測試
 ╱─────────────╲
```

### 測試工具

| 類型 | 工具 | 用途 |
|------|------|------|
| **後端單元測試** | PHPUnit | PHP 單元測試 |
| **前端單元測試** | Vitest | Vue 組件測試 |
| **E2E 測試** | Cypress / Playwright | 端到端測試 |
| **API 測試** | Postman / Insomnia | API 功能測試 |
| **載入測試** | Apache JMeter | 效能測試 |

## 🔬 單元測試

### 後端單元測試 (PHPUnit)

#### 設定

```bash
# 安裝 PHPUnit（已包含在 Laravel）
composer require --dev phpunit/phpunit

# 執行所有測試
php artisan test

# 執行特定測試
php artisan test --filter CustomerTest

# 查看測試覆蓋率
php artisan test --coverage
```

#### 測試範例

**tests/Unit/CustomerTest.php**:
```php
<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CustomerTest extends TestCase
{
    use RefreshDatabase;
    
    public function test_customer_can_be_created()
    {
        $customer = Customer::create([
            'name' => 'Test Customer',
            'phone' => '0912345678',
            'email' => 'test@example.com',
            'status' => 'active'
        ]);
        
        $this->assertDatabaseHas('customers', [
            'name' => 'Test Customer',
            'phone' => '0912345678'
        ]);
    }
    
    public function test_customer_can_be_blocked()
    {
        $customer = Customer::factory()->create();
        
        $customer->update(['status' => 'blocked']);
        
        $this->assertEquals('blocked', $customer->status);
    }
    
    public function test_customer_total_spent_calculation()
    {
        $customer = Customer::factory()->create();
        
        // 創建預約並付款
        $reservation = $customer->reservations()->create([
            'service_id' => 1,
            'reservation_date' => now(),
            'reservation_time' => '10:00:00',
            'payment_amount' => 500,
            'payment_status' => 'paid'
        ]);
        
        $this->assertEquals(500, $customer->total_spent);
    }
}
```

**tests/Unit/ReservationTest.php**:
```php
<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Reservation;
use App\Models\Customer;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ReservationTest extends TestCase
{
    use RefreshDatabase;
    
    public function test_reservation_can_be_confirmed()
    {
        $reservation = Reservation::factory()->create(['status' => 'pending']);
        
        $reservation->confirm();
        
        $this->assertEquals('confirmed', $reservation->status);
        $this->assertNotNull($reservation->confirmed_at);
    }
    
    public function test_reservation_can_be_checked_in()
    {
        $reservation = Reservation::factory()->create();
        
        $reservation->checkIn();
        
        $this->assertEquals('checked_in', $reservation->check_in_status);
        $this->assertNotNull($reservation->check_in_time);
    }
    
    public function test_late_check_in_is_detected()
    {
        // 創建過期的預約
        $reservation = Reservation::factory()->create([
            'reservation_date' => now()->subDay(),
            'reservation_time' => '10:00:00'
        ]);
        
        $reservation->checkIn();
        
        $this->assertEquals('late', $reservation->check_in_status);
    }
}
```

### 前端單元測試 (Vitest)

#### 設定

```bash
# 安裝 Vitest
npm install -D vitest @vue/test-utils

# 執行測試
npm run test

# 監視模式
npm run test:watch

# 測試覆蓋率
npm run test:coverage
```

#### 測試範例

**src/components/__tests__/StatCard.test.js**:
```javascript
import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import StatCard from '../StatCard.vue'

describe('StatCard', () => {
  it('renders title correctly', () => {
    const wrapper = mount(StatCard, {
      props: {
        title: 'Total Customers',
        value: '150',
        subtitle: '+10 this month'
      }
    })
    
    expect(wrapper.text()).toContain('Total Customers')
    expect(wrapper.text()).toContain('150')
  })
  
  it('applies correct color classes', () => {
    const wrapper = mount(StatCard, {
      props: {
        bgColor: 'bg-blue-100',
        iconColor: 'text-blue-600'
      }
    })
    
    expect(wrapper.html()).toContain('bg-blue-100')
    expect(wrapper.html()).toContain('text-blue-600')
  })
})
```

##  整合測試

### 後端整合測試

**tests/Feature/ReservationApiTest.php**:
```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Customer;
use App\Models\Service;
use Laravel\Sanctum\Sanctum;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ReservationApiTest extends TestCase
{
    use RefreshDatabase;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // 創建管理員用戶並認證
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);
    }
    
    public function test_can_create_reservation()
    {
        $customer = Customer::factory()->create();
        $service = Service::factory()->create();
        
        $response = $this->postJson('/api/reservations', [
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'reservation_date' => now()->addDay()->format('Y-m-d'),
            'reservation_time' => '10:00:00',
            'reservation_name' => 'Test Customer',
            'reservation_phone' => '0912345678'
        ]);
        
        $response->assertStatus(201)
                 ->assertJsonStructure(['success', 'data', 'message']);
        
        $this->assertDatabaseHas('reservations', [
            'customer_id' => $customer->id,
            'service_id' => $service->id
        ]);
    }
    
    public function test_can_confirm_reservation()
    {
        $reservation = Reservation::factory()->create(['status' => 'pending']);
        
        $response = $this->putJson("/api/reservations/{$reservation->id}/confirm");
        
        $response->assertStatus(200);
        
        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'status' => 'confirmed'
        ]);
    }
    
    public function test_unauthorized_user_cannot_access_admin_routes()
    {
        // 使用一般用戶
        $user = User::factory()->create(['role' => 'user']);
        Sanctum::actingAs($user);
        
        $response = $this->getJson('/api/customers');
        
        $response->assertStatus(403);
    }
}
```

## 🖱 手動測試

### 手動測試檢查清單

#### 1. 認證流程
- [ ] 登入功能正常
- [ ] 登出功能正常
- [ ] 無效憑證顯示錯誤訊息
- [ ] Token 過期自動登出
- [ ] 未認證用戶無法訪問受保護頁面

#### 2. 客戶管理
- [ ] 新增客戶成功
- [ ] 編輯客戶資料成功
- [ ] 刪除客戶成功
- [ ] 搜尋功能正常
- [ ] 狀態篩選正常
- [ ] 分頁功能正常
- [ ] 封鎖/解封客戶功能正常

#### 3. 預約管理
- [ ] 創建預約成功
- [ ] 確認預約成功
- [ ] 取消預約成功
- [ ] 預約列表篩選正常
- [ ] 預約詳情顯示正確
- [ ] 日期範圍篩選正常

#### 4. 報到管理
- [ ] 報到功能正常
- [ ] 標記爽約功能正常
- [ ] 記錄付款功能正常
- [ ] 今日報到列表正確

#### 5. LINE Bot
- [ ] Webhook 接收訊息正常
- [ ] 機器人回覆正確
- [ ] 預約流程完整
- [ ] 錯誤處理正確

#### 6. 響應式設計
- [ ] 桌面版顯示正常
- [ ] 平板版顯示正常
- [ ] 手機版顯示正常
- [ ] 所有按鈕可點擊
- [ ] 表單可正常操作

### 手動測試腳本

#### 測試案例 1: 完整預約流程

```
前置條件:
- 系統已部署
- 已有測試帳號
- 已設定 LINE Bot

步驟:
1. 使用 LINE 加入 Bot 好友
2. 發送「預約」訊息
3. 選擇服務項目
4. 選擇日期和時間
5. 填寫聯絡資訊
6. 確認預約

預期結果:
- 收到預約確認訊息
- 後台顯示新預約
- 預約狀態為「待確認」

實際結果:
[ ] 通過 [ ] 失敗

備註:
```

#### 測試案例 2: 管理員操作

```
前置條件:
- 以管理員身份登入
- 系統已有測試資料

步驟:
1. 進入客戶管理頁面
2. 新增客戶
3. 編輯客戶資料
4. 創建預約
5. 確認預約
6. 客戶報到
7. 記錄付款

預期結果:
- 所有操作成功
- 資料正確儲存
- UI 正確更新

實際結果:
[ ] 通過 [ ] 失敗

備註:
```

## E2E 測試

### Cypress 測試範例

**cypress/e2e/reservation-flow.cy.js**:
```javascript
describe('Reservation Flow', () => {
  beforeEach(() => {
    // 登入
    cy.visit('/login')
    cy.get('input[name="email"]').type('admin@example.com')
    cy.get('input[name="password"]').type('password')
    cy.get('button[type="submit"]').click()
    cy.url().should('include', '/')
  })
  
  it('can create a reservation', () => {
    cy.visit('/reservations')
    cy.contains('新增預約').click()
    
    // 填寫表單
    cy.get('select[name="customer_id"]').select('1')
    cy.get('select[name="service_id"]').select('1')
    cy.get('input[name="reservation_date"]').type('2025-10-25')
    cy.get('input[name="reservation_time"]').type('10:00')
    
    // 提交
    cy.contains('確認').click()
    
    // 驗證成功訊息
    cy.contains('預約創建成功').should('be.visible')
    
    // 驗證預約出現在列表中
    cy.contains('2025-10-25').should('exist')
  })
  
  it('can confirm a reservation', () => {
    cy.visit('/reservations')
    
    // 找到第一筆待確認的預約
    cy.contains('待確認').parents('tr').within(() => {
      cy.contains('確認').click()
    })
    
    // 驗證狀態變更
    cy.contains('已確認').should('be.visible')
  })
})
```

## 📡 API 測試

### Postman/Insomnia 測試

#### 測試集合結構

```
LINE Reservation API Tests/
├── Authentication/
│   ├── Login - Success
│   ├── Login - Invalid Credentials
│   └── Logout
│
├── Customers/
│   ├── Get Customers List
│   ├── Create Customer
│   ├── Get Customer Details
│   ├── Update Customer
│   └── Delete Customer
│
├── Reservations/
│   ├── Get Reservations List
│   ├── Create Reservation
│   ├── Confirm Reservation
│   └── Cancel Reservation
│
└── Services/
    ├── Get Services List
    ├── Create Service
    └── Update Service
```

#### cURL 測試範例

```bash
# 1. 登入
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"password"}'

# 儲存返回的 token
TOKEN="your_token_here"

# 2. 獲取客戶列表
curl -X GET http://localhost:8000/api/customers \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json"

# 3. 創建預約
curl -X POST http://localhost:8000/api/reservations \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "customer_id": 1,
    "service_id": 1,
    "reservation_date": "2025-10-25",
    "reservation_time": "10:00:00",
    "reservation_name": "Test Customer",
    "reservation_phone": "0912345678"
  }'
```

## ✅ 測試最佳實踐

### 1. 測試命名規範

```php
// Good ✅
public function test_customer_can_be_created()
public function test_reservation_requires_valid_date()
public function test_unauthorized_user_cannot_access_admin_routes()

// Bad ❌
public function testCustomer()
public function test1()
```

### 2. 測試獨立性

每個測試應該獨立運行，不依賴其他測試的執行結果：

```php
use RefreshDatabase; // 每次測試重置資料庫

public function test_example()
{
    // 創建測試所需的資料
    $customer = Customer::factory()->create();
    
    // 執行測試
    // ...
    
    // 清理不需要，RefreshDatabase 會自動處理
}
```

### 3. 使用 Factory

```php
// Good ✅
$customers = Customer::factory()->count(10)->create();

// Bad ❌
for ($i = 0; $i < 10; $i++) {
    Customer::create([
        'name' => 'Customer ' . $i,
        'phone' => '091234567' . $i,
        // ...
    ]);
}
```

### 4. 斷言清晰

```php
// Good ✅
$this->assertEquals('confirmed', $reservation->status);
$this->assertDatabaseHas('reservations', ['status' => 'confirmed']);

// Bad ❌
$this->assertTrue($reservation->status == 'confirmed');
```

##  持續整合 (CI)

### GitHub Actions 配置範例

**.github/workflows/tests.yml**:
```yaml
name: Tests

on:
  push:
    branches: [ main, develop ]
  pull_request:
    branches: [ main, develop ]

jobs:
  test:
    runs-on: ubuntu-latest
    
    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_DATABASE: test_db
          MYSQL_ROOT_PASSWORD: password
        ports:
          - 3306:3306
    
    steps:
    - uses: actions/checkout@v2
    
    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: '8.2'
    
    - name: Install Dependencies
      run: composer install
      working-directory: ./backend
    
    - name: Run Tests
      run: php artisan test
      working-directory: ./backend
      env:
        DB_CONNECTION: mysql
        DB_HOST: 127.0.0.1
        DB_DATABASE: test_db
        DB_USERNAME: root
        DB_PASSWORD: password
```

---

**文件版本**: v1.0.0  
**最後更新**: 2025-10-23  
**維護者**: 傅盛祥 (Spencer Kuku)