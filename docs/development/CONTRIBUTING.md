# 多租戶 B2B 系統貢獻指南

## 目錄

- [開發流程](#開發流程)
- [多租戶開發注意事項](#多租戶開發注意事項)
- [分支策略](#分支策略)
- [程式碼規範](#程式碼規範)
- [Commit 訊息格式](#commit-訊息格式)
- [Pull Request 流程](#pull-request-流程)
- [程式碼審查](#程式碼審查)
- [測試要求](#測試要求)

## 開發流程

本專案為多租戶 B2B 預約系統，開發時需特別注意租戶隔離與資料安全性。

### 1. 設定開發環境

```bash
# Fork 專案到你的 GitHub 帳號

# Clone 你的 fork
git clone https://github.com/YOUR_USERNAME/line-reservation.git
cd line-reservation

# 加入上游倉庫
git remote add upstream https://github.com/ORIGINAL_OWNER/line-reservation.git

# 驗證遠端倉庫
git remote -v
```

### 2. 保持同步

```bash
# 定期從上游拉取最新變更
git fetch upstream
git checkout main
git merge upstream/main

# 推送到你的 fork
git push origin main
```

### 3. 開發新功能

```bash
# 建立功能分支
git checkout -b feature/your-feature-name

# 進行開發...

# 提交變更
git add .
git commit -m "feat: add your feature description"

# 推送到你的 fork
git push origin feature/your-feature-name

# 在 GitHub 上建立 Pull Request
```

## 🌿 分支策略

我們使用 **Git Flow** 工作流程：

```
main (生產環境)
  ↑
develop (開發環境)
  ↑
feature/* (功能開發)
hotfix/* (緊急修復)
release/* (版本發布)
```

### 分支說明

| 分支類型 | 命名規則 | 說明 | 範例 |
|---------|---------|------|------|
| `main` | `main` | 生產環境程式碼 | `main` |
| `develop` | `develop` | 開發環境程式碼 | `develop` |
| `feature` | `feature/功能名稱` | 新功能開發 | `feature/customer-import` |
| `bugfix` | `bugfix/錯誤描述` | Bug 修復 | `bugfix/reservation-validation` |
| `hotfix` | `hotfix/緊急修復` | 生產環境緊急修復 | `hotfix/security-patch` |
| `release` | `release/版本號` | 版本發布準備 | `release/v1.2.0` |

### 分支操作範例

#### 新功能開發

```bash
# 從 develop 建立功能分支
git checkout develop
git pull upstream develop
git checkout -b feature/export-reservations

# 開發完成後合併回 develop
git checkout develop
git merge --no-ff feature/export-reservations
git push upstream develop

# 刪除功能分支
git branch -d feature/export-reservations
```

#### 緊急修復

```bash
# 從 main 建立 hotfix 分支
git checkout main
git pull upstream main
git checkout -b hotfix/security-vulnerability

# 修復完成後合併回 main 和 develop
git checkout main
git merge --no-ff hotfix/security-vulnerability
git tag -a v1.1.1 -m "Hotfix: security vulnerability"
git push upstream main --tags

git checkout develop
git merge --no-ff hotfix/security-vulnerability
git push upstream develop

# 刪除 hotfix 分支
git branch -d hotfix/security-vulnerability
```

## 程式碼規範

### Backend (PHP/Laravel)

遵循 **PSR-12** 標準和 **Laravel 最佳實踐**。

#### 命名規範

```php
// 類別名稱：PascalCase
class CustomerController extends Controller {}

// 方法名稱：camelCase
public function createReservation() {}

// 變數名稱：camelCase
$reservationDate = '2025-10-25';

// 常數名稱：UPPER_SNAKE_CASE
const MAX_RESERVATIONS_PER_DAY = 10;

// 資料表名稱：snake_case (複數)
Schema::create('customer_reservations', function (Blueprint $table) {});

// Model 名稱：PascalCase (單數)
class CustomerReservation extends Model {}
```

#### 程式碼風格

```php
<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ReservationController extends Controller
{
    /**
     * 顯示預約列表
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        // 使用 query builder 進行資料查詢
        $reservations = Reservation::query()
            ->with(['customer', 'service'])
            ->when($request->status, function ($query, $status) {
                return $query->where('status', $status);
            })
            ->latest()
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $reservations,
        ]);
    }

    /**
     * 建立新預約
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        // 驗證請求資料
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'service_id' => 'required|exists:services,id',
            'reservation_date' => 'required|date|after:today',
            'time_slot' => 'required|string',
        ]);

        try {
            // 建立預約
            $reservation = Reservation::create($validated);

            return response()->json([
                'success' => true,
                'data' => $reservation,
                'message' => '預約建立成功',
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '預約建立失敗',
            ], 500);
        }
    }
}
```

#### Laravel 最佳實踐

```php
// ✅ 好的做法：使用 Eloquent 關聯
$user->reservations()->with('service')->get();

// ❌ 不好的做法：N+1 查詢問題
$reservations = Reservation::all();
foreach ($reservations as $reservation) {
    $service = $reservation->service; // N+1 查詢
}

// ✅ 好的做法：使用 Service 層
class ReservationService
{
    public function createReservation(array $data): Reservation
    {
        // 商業邏輯
        return Reservation::create($data);
    }
}

// ✅ 好的做法：使用 Form Request 驗證
class StoreReservationRequest extends FormRequest
{
    public function rules()
    {
        return [
            'customer_id' => 'required|exists:customers,id',
            'reservation_date' => 'required|date|after:today',
        ];
    }
}
```

### Frontend (Vue.js)

遵循 **Vue.js Style Guide** 和 **Composition API** 最佳實踐。

#### 命名規範

```javascript
// 元件名稱：PascalCase
// ReservationList.vue

// Composable：use + PascalCase
// useReservations.js

// 變數和函式：camelCase
const reservationList = ref([]);
const fetchReservations = async () => {};

// 常數：UPPER_SNAKE_CASE
const MAX_RETRY_COUNT = 3;

// Props 和 Emits：camelCase
const props = defineProps({
  reservationId: Number,
  showDetails: Boolean
});

const emit = defineEmits(['update:modelValue', 'delete']);
```

#### 元件結構

```vue
<script setup>
// 1. 導入
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useReservationStore } from '@/stores/reservation';

// 2. Props 和 Emits
const props = defineProps({
  reservationId: {
    type: Number,
    required: true
  }
});

const emit = defineEmits(['updated', 'deleted']);

// 3. Composables
const router = useRouter();
const reservationStore = useReservationStore();

// 4. Reactive 狀態
const isLoading = ref(false);
const reservation = ref(null);

// 5. Computed 屬性
const displayDate = computed(() => {
  return reservation.value?.reservation_date 
    ? new Date(reservation.value.reservation_date).toLocaleDateString('zh-TW')
    : '';
});

// 6. 方法
const fetchReservation = async () => {
  isLoading.value = true;
  try {
    reservation.value = await reservationStore.fetchById(props.reservationId);
  } catch (error) {
    console.error('Failed to fetch reservation:', error);
  } finally {
    isLoading.value = false;
  }
};

const deleteReservation = async () => {
  if (!confirm('確定要刪除此預約嗎？')) return;
  
  try {
    await reservationStore.delete(props.reservationId);
    emit('deleted', props.reservationId);
    router.push('/reservations');
  } catch (error) {
    console.error('Failed to delete reservation:', error);
  }
};

// 7. 生命週期鉤子
onMounted(() => {
  fetchReservation();
});
</script>

<template>
  <div class="reservation-detail">
    <!-- 模板內容 -->
    <div v-if="isLoading" class="loading">載入中...</div>
    <div v-else-if="reservation">
      <h2>預約詳情</h2>
      <p>日期：{{ displayDate }}</p>
      <button @click="deleteReservation">刪除</button>
    </div>
  </div>
</template>

<style scoped>
.reservation-detail {
  padding: 1rem;
}

.loading {
  text-align: center;
  color: #64748b;
}
</style>
```

## 💬 Commit 訊息格式

使用 **Conventional Commits** 規範。

### 格式

```
<type>(<scope>): <subject>

<body>

<footer>
```

### Type 類型

| Type | 說明 | 範例 |
|------|------|------|
| `feat` | 新功能 | `feat(reservation): add export to excel` |
| `fix` | Bug 修復 | `fix(auth): resolve token expiration issue` |
| `docs` | 文件變更 | `docs(readme): update installation steps` |
| `style` | 程式碼格式（不影響功能） | `style(button): improve spacing` |
| `refactor` | 重構（不新增功能或修復 bug） | `refactor(customer): simplify validation logic` |
| `perf` | 效能優化 | `perf(database): add index to reservations` |
| `test` | 測試相關 | `test(reservation): add unit tests` |
| `chore` | 建置流程或工具變更 | `chore(deps): update Laravel to 12.0` |
| `ci` | CI/CD 相關 | `ci(github): add automated tests` |
| `revert` | 回復先前的 commit | `revert: feat(customer): add import feature` |

### 範例

#### 簡短訊息

```bash
git commit -m "feat(reservation): add cancel reservation feature"
git commit -m "fix(line-bot): resolve webhook signature validation"
git commit -m "docs(api): update reservation endpoints"
```

#### 完整訊息

```bash
git commit -m "feat(customer): add bulk import from CSV

- Add CSV upload endpoint
- Implement CSV parser service
- Add validation for customer data
- Update documentation

Closes #123"
```

#### Breaking Changes

```bash
git commit -m "feat(api)!: change reservation response format

BREAKING CHANGE: Reservation API now returns ISO 8601 date format 
instead of YYYY-MM-DD. Update your frontend code accordingly.

Before: '2025-10-25'
After: '2025-10-25T00:00:00.000000Z'"
```

## Pull Request 流程

### 1. 建立 Pull Request

**PR 標題格式**：與 Commit 訊息相同

```
feat(reservation): add export to Excel feature
fix(auth): resolve token refresh issue
docs(setup): improve installation guide
```

**PR 描述範本**：

```markdown
## 變更說明
簡要描述這個 PR 的目的和變更內容。

## 變更類型
- [ ] 新功能 (feat)
- [ ] Bug 修復 (fix)
- [ ] 文件更新 (docs)
- [ ] 程式碼重構 (refactor)
- [ ] 效能優化 (perf)
- [ ] 測試 (test)
- [ ] 其他 (請說明)

## 測試
描述如何測試這些變更：
- [ ] 單元測試已通過
- [ ] 整合測試已通過
- [ ] 手動測試已完成

## 截圖（如適用）
如果有 UI 變更，請附上截圖。

## 相關 Issue
Closes #123
Fixes #456

## Checklist
- [ ] 我已閱讀 **貢獻指南**
- [ ] 程式碼遵循專案的程式碼規範
- [ ] 我已添加必要的文件說明
- [ ] 我的變更不會產生新的警告
- [ ] 我已添加相關測試
- [ ] 所有測試都已通過
- [ ] 我已更新相關文件
```

### 2. 審查前檢查

```bash
# 執行程式碼格式化
# Backend
cd backend
./vendor/bin/pint

# Frontend
cd frontend
npm run lint:fix

# 執行測試
# Backend
php artisan test

# Frontend
npm run test

# 檢查 TypeScript/ESLint
npm run type-check
npm run lint
```

### 3. 回應審查意見

```bash
# 根據審查意見進行修改
git add .
git commit -m "refactor: address PR review comments"
git push origin feature/your-feature-name

# 如需修改 commit 訊息（避免過多小 commit）
git rebase -i HEAD~3
# 將多個 commit 合併為一個
```

## 👀 程式碼審查

### 審查者指南

審查時注意以下方面：

#### 1. 功能性
- [ ] 程式碼是否實現了預期功能？
- [ ] 是否有邊界情況未處理？
- [ ] 錯誤處理是否完善？

#### 2. 程式碼品質
- [ ] 程式碼是否易讀易懂？
- [ ] 是否遵循專案的程式碼規範？
- [ ] 是否有重複的程式碼？
- [ ] 命名是否清晰有意義？

#### 3. 效能
- [ ] 是否有效能問題？
- [ ] 資料庫查詢是否優化（避免 N+1）？
- [ ] 是否有不必要的計算？

#### 4. 安全性
- [ ] 是否有 SQL 注入風險？
- [ ] 是否有 XSS 漏洞？
- [ ] 敏感資料是否妥善處理？
- [ ] 權限檢查是否完整？

#### 5. 測試
- [ ] 是否有足夠的測試覆蓋？
- [ ] 測試案例是否合理？
- [ ] 測試是否能正確通過？

### 審查評論範例

```markdown
## 建議改進

**performance.php:45**
```php
// 建議使用 eager loading 避免 N+1 查詢
$reservations = Reservation::with(['customer', 'service'])->get();
```

**ReservationForm.vue:78**
```javascript
// 建議將魔術數字提取為常數
const MAX_PARTICIPANTS = 10;
if (participants > MAX_PARTICIPANTS) { ... }
```

## 優點
- 程式碼結構清晰
- 錯誤處理完善
- 測試覆蓋率高

## 總結
整體來說這是個好的 PR，只需要處理上述的小問題即可合併。
```

## ✅ 測試要求

### Backend 測試

```bash
# 執行所有測試
php artisan test

# 執行特定測試檔案
php artisan test tests/Feature/ReservationTest.php

# 執行測試並產生覆蓋率報告
php artisan test --coverage
```

**測試覆蓋率目標**：
- 新功能：≥ 80%
- 核心業務邏輯：≥ 90%

### Frontend 測試

```bash
# 執行單元測試
npm run test:unit

# 執行 E2E 測試
npm run test:e2e

# 執行測試並產生覆蓋率
npm run test:coverage
```

### 必須測試的情況

- [ ] 正常流程（Happy Path）
- [ ] 錯誤處理（Error Cases）
- [ ] 邊界條件（Edge Cases）
- [ ] 權限檢查（Authorization）
- [ ] 資料驗證（Validation）

---

## 📞 聯絡資訊

如有任何問題，歡迎：
- 開 Issue 討論
- 在 PR 中留言
- 聯絡專案維護者

**感謝你的貢獻！** 🎉

---

**文件版本**: v1.0.0  
**最後更新**: 2025-10-23  
**維護者**: 傅盛祥 (Spencer Kuku)