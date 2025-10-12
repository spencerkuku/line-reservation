# UI 設計規範指南

## 📐 整體設計原則

本專案使用 **Tailwind CSS** 作為主要 UI 框架，採用現代化的卡片式設計風格。

---

## 🎨 設計系統檢查清單

### ✅ 已統一的部分

1. **背景色**：`bg-gray-50`（頁面底色）
2. **卡片樣式**：`bg-white rounded-xl shadow-sm border border-gray-200`
3. **統計卡片**：
   - 4 列網格：`grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6`
   - Hover 效果：`hover:shadow-md transition-shadow`
   - Icon 容器：`w-12 h-12 bg-{color}-100 rounded-lg flex items-center justify-center`
4. **表格風格**：Tailwind 原生 `<table>` 樣式
5. **按鈕圓角**：統一使用 `rounded-lg` 或 `rounded-md`

### ⚠️ 需要注意的不一致之處

| 項目 | CheckIn.vue | Reservations.vue | Customers.vue | 建議統一為 |
|------|-------------|------------------|---------------|-----------|
| **頁面標題大小** | `text-3xl` | `text-3xl` | `text-3xl` | ✅ 一致 |
| **卡片內邊距** | `p-6` | `p-6` | `p-6` | ✅ 一致 |
| **間距** | `mb-8` | `mb-8` | `mb-8` | ✅ 一致 |
| **按鈕樣式** | 統一使用漸變 | 統一使用漸變 | 統一使用漸變 | ✅ 一致 |
| **表格 hover** | `hover:bg-gray-50` | `hover:bg-gray-50 transition-colors duration-200` | `hover:bg-gray-50` | ⚠️ 動畫時間不一致 |
| **狀態標籤** | 使用 `<span>` + 背景色 | 使用 `<span>` + 背景色 | 使用 `<span>` + 背景色 | ✅ 一致 |
| **分頁樣式** | ❌ 無分頁 | ✅ 自訂分頁 | ✅ 自訂分頁 | ⚠️ 報到管理無分頁 |

---

## 🧩 標準組件樣式

### 1. 頁面容器

```vue
<div class="min-h-screen bg-gray-50 p-6">
  <!-- 頁面內容 -->
</div>
```

### 2. 頁面標題

```vue
<div class="mb-8">
  <h1 class="text-3xl font-bold text-gray-900">頁面標題</h1>
  <p class="text-gray-600 mt-2">頁面副標題或描述</p>
</div>
```

### 3. 統計卡片

```vue
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
  <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow">
    <div class="flex items-center justify-between">
      <div>
        <p class="text-sm font-medium text-gray-600">標題</p>
        <p class="text-2xl font-bold text-gray-900 mt-2">數值</p>
        <p class="text-xs text-gray-500 mt-1">說明文字</p>
      </div>
      <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
        <!-- SVG Icon -->
      </div>
    </div>
  </div>
</div>
```

**顏色配置**：
- 藍色（一般資訊）：`bg-blue-100` + `text-blue-600`
- 綠色（成功/確認）：`bg-green-100` + `text-green-600`
- 黃色（警告/待處理）：`bg-yellow-100` + `text-yellow-600`
- 紅色（錯誤/取消）：`bg-red-100` + `text-red-600`
- 紫色（特殊）：`bg-purple-100` + `text-purple-600`

### 4. 搜尋與篩選卡片

```vue
<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
  <!-- 標題 -->
  <div class="flex items-center justify-between mb-4">
    <h2 class="text-lg font-semibold text-gray-900">篩選條件</h2>
  </div>
  
  <!-- 篩選欄位 -->
  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
    <div class="space-y-2">
      <label class="text-sm font-medium text-gray-700">欄位名稱</label>
      <input 
        type="text" 
        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
        placeholder="placeholder"
      />
    </div>
  </div>
</div>
```

### 5. 表格容器

```vue
<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
  <!-- 表格標題（可選） -->
  <div class="px-6 py-4 border-b border-gray-200">
    <h2 class="text-lg font-semibold text-gray-900">資料列表</h2>
  </div>
  
  <!-- 表格 -->
  <div class="overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-200">
      <thead class="bg-gray-50">
        <tr>
          <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
            欄位名稱
          </th>
        </tr>
      </thead>
      <tbody class="bg-white divide-y divide-gray-200">
        <tr class="hover:bg-gray-50 transition-colors duration-200">
          <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
            資料內容
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</div>
```

### 6. 按鈕樣式

#### 主要按鈕（Primary）
```vue
<button class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors">
  按鈕文字
</button>
```

#### 漸變按鈕
```vue
<button class="px-4 py-2 bg-gradient-to-r from-blue-600 to-blue-700 text-white text-sm font-medium rounded-lg hover:from-blue-700 hover:to-blue-800 transition-all duration-200">
  按鈕文字
</button>
```

#### 成功按鈕（Success）
```vue
<button class="px-3 py-1.5 text-green-700 bg-green-100 hover:bg-green-200 text-xs font-medium rounded-md transition-colors">
  確認
</button>
```

#### 危險按鈕（Danger）
```vue
<button class="px-3 py-1.5 text-red-700 bg-red-100 hover:bg-red-200 text-xs font-medium rounded-md transition-colors">
  刪除
</button>
```

#### 次要按鈕（Secondary）
```vue
<button class="px-4 py-2 bg-gray-100 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-200 border border-gray-300 transition-colors">
  取消
</button>
```

### 7. 狀態標籤

```vue
<!-- 待確認/警告 -->
<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 border border-yellow-200">
  <span class="w-1.5 h-1.5 bg-yellow-400 rounded-full mr-1.5"></span>
  待確認
</span>

<!-- 已確認/成功 -->
<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 border border-green-200">
  <span class="w-1.5 h-1.5 bg-green-400 rounded-full mr-1.5"></span>
  已確認
</span>

<!-- 已完成/資訊 -->
<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 border border-blue-200">
  <span class="w-1.5 h-1.5 bg-blue-400 rounded-full mr-1.5"></span>
  已完成
</span>

<!-- 已取消/錯誤 -->
<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 border border-red-200">
  <span class="w-1.5 h-1.5 bg-red-400 rounded-full mr-1.5"></span>
  已取消
</span>
```

### 8. Modal/對話框

```vue
<!-- 背景遮罩 -->
<div class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center p-4 z-50" @click.self="closeModal">
  <!-- Modal 內容 -->
  <div class="bg-white rounded-xl shadow-xl max-w-md w-full">
    <!-- Header -->
    <div class="px-6 py-4 border-b border-gray-200">
      <div class="flex items-center justify-between">
        <h3 class="text-lg font-semibold text-gray-900">Modal 標題</h3>
        <button @click="closeModal" class="text-gray-400 hover:text-gray-500">
          <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>
    </div>
    
    <!-- Body -->
    <div class="px-6 py-4">
      <!-- 內容 -->
    </div>
    
    <!-- Footer -->
    <div class="px-6 py-4 border-t border-gray-200 flex justify-end gap-3">
      <button class="px-4 py-2 bg-gray-100 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-200">
        取消
      </button>
      <button class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700">
        確認
      </button>
    </div>
  </div>
</div>
```

### 9. 分頁組件

```vue
<div class="bg-white px-6 py-4 border-t border-gray-200">
  <div class="flex items-center justify-between">
    <div class="text-sm text-gray-700">
      顯示第 {{ start }} 到 {{ end }} 筆，共 {{ total }} 筆
    </div>
    <div class="flex items-center space-x-2">
      <button
        @click="prevPage"
        :disabled="currentPage === 1"
        class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
      >
        上一頁
      </button>
      <span class="px-3 py-2 text-sm font-medium text-gray-700">
        第 {{ currentPage }} 頁，共 {{ totalPages }} 頁
      </span>
      <button
        @click="nextPage"
        :disabled="currentPage === totalPages"
        class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
      >
        下一頁
      </button>
    </div>
  </div>
</div>
```

### 10. Tab 篩選組件（推薦用於列表頁面）

```vue
<!-- 雙層 Tab 設計 -->
<div class="bg-white rounded-xl shadow-sm border border-gray-200 mb-6">
  <!-- 第一層：狀態篩選 -->
  <div class="px-6 py-4 border-b border-gray-200">
    <div class="flex flex-wrap gap-2">
      <button
        v-for="status in statusTabs"
        :key="status.value"
        @click="activeStatus = status.value"
        :class="[
          'px-4 py-2 text-sm font-medium rounded-lg transition-all duration-200 flex items-center',
          activeStatus === status.value
            ? 'bg-blue-600 text-white shadow-sm'
            : 'text-gray-600 hover:bg-gray-100'
        ]"
      >
        {{ status.label }}
        <span
          v-if="status.count !== undefined"
          :class="[
            'ml-2 px-2 py-0.5 text-xs rounded-full font-semibold',
            activeStatus === status.value
              ? 'bg-blue-500 text-white'
              : 'bg-gray-200 text-gray-700'
          ]"
        >
          {{ status.count }}
        </span>
      </button>
    </div>
  </div>

  <!-- 第二層：時間篩選 -->
  <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
    <div class="flex flex-wrap gap-2">
      <button
        v-for="period in timePeriods"
        :key="period.value"
        @click="activePeriod = period.value"
        :class="[
          'px-4 py-2 text-sm font-medium rounded-lg transition-all duration-200',
          activePeriod === period.value
            ? 'bg-gray-900 text-white'
            : 'text-gray-600 hover:bg-gray-200'
        ]"
      >
        {{ period.label }}
        <span
          v-if="period.date"
          class="ml-2 text-xs opacity-75"
        >
          ({{ period.date }})
        </span>
      </button>
    </div>
  </div>

  <!-- 可選：進階搜尋（摺疊式）-->
  <div v-if="showAdvancedSearch" class="px-6 py-4 border-b border-gray-200 bg-gray-50">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <input
        v-model="searchForm.field1"
        type="text"
        placeholder="搜尋條件 1"
        class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
      />
      <!-- 更多搜尋欄位 -->
    </div>
  </div>

  <!-- 工具列 -->
  <div class="px-6 py-3 bg-white">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <button
        @click="showAdvancedSearch = !showAdvancedSearch"
        class="text-sm text-gray-600 hover:text-gray-900 font-medium"
      >
        {{ showAdvancedSearch ? '隱藏' : '顯示' }}進階搜尋
      </button>
      
      <div class="text-sm text-gray-600">
        顯示 {{ filteredCount }} 筆
      </div>
    </div>
  </div>
</div>
```

**Tab 設計最佳實踐**：
- 第一層（主要篩選）：使用藍色高亮（`bg-blue-600`）
- 第二層（次要篩選）：使用深灰色高亮（`bg-gray-900`）
- 顯示數量徽章：活躍時白底藍字，非活躍時灰底灰字
- 支援響應式：手機版可改用下拉選單
- 進階搜尋摺疊：不干擾主要操作流程

---

## 🎯 狀態配色標準

### 預約狀態（Reservation Status）
```javascript
const reservationStatusConfig = {
  pending: {
    bg: 'bg-yellow-100',
    text: 'text-yellow-800',
    border: 'border-yellow-200',
    dot: 'bg-yellow-400',
    label: '待確認'
  },
  confirmed: {
    bg: 'bg-blue-100',
    text: 'text-blue-800',
    border: 'border-blue-200',
    dot: 'bg-blue-400',
    label: '已確認'
  },
  completed: {
    bg: 'bg-green-100',
    text: 'text-green-800',
    border: 'border-green-200',
    dot: 'bg-green-400',
    label: '已完成'
  },
  cancelled: {
    bg: 'bg-red-100',
    text: 'text-red-800',
    border: 'border-red-200',
    dot: 'bg-red-400',
    label: '已取消'
  }
}
```

### 報到狀態（Check-in Status）
```javascript
const checkinStatusConfig = {
  pending: {
    bg: 'bg-yellow-100',
    text: 'text-yellow-800',
    label: '待報到'
  },
  checked_in: {
    bg: 'bg-green-100',
    text: 'text-green-800',
    label: '已報到'
  },
  late: {
    bg: 'bg-orange-100',
    text: 'text-orange-800',
    label: '已報到(遲到)'
  },
  no_show: {
    bg: 'bg-red-100',
    text: 'text-red-800',
    label: '爽約'
  }
}
```

### 付款狀態（Payment Status）
```javascript
const paymentStatusConfig = {
  unpaid: {
    bg: 'bg-red-100',
    text: 'text-red-800',
    label: '未付款'
  },
  partial: {
    bg: 'bg-yellow-100',
    text: 'text-yellow-800',
    label: '部分付款'
  },
  paid: {
    bg: 'bg-green-100',
    text: 'text-green-800',
    label: '已付款'
  },
  refunded: {
    bg: 'bg-gray-100',
    text: 'text-gray-800',
    label: '已退款'
  }
}
```

---

## 📱 響應式設計

### 斷點標準
```javascript
const breakpoints = {
  sm: '640px',   // 小型裝置（手機橫向）
  md: '768px',   // 平板
  lg: '1024px',  // 小型桌面
  xl: '1280px',  // 一般桌面
  '2xl': '1536px' // 大型桌面
}
```

### 常用響應式類別
```vue
<!-- 網格響應式 -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">

<!-- 間距響應式 -->
<div class="p-4 sm:p-6 lg:p-8">

<!-- 文字大小響應式 -->
<h1 class="text-2xl sm:text-3xl lg:text-4xl font-bold">

<!-- Flexbox 響應式 -->
<div class="flex flex-col lg:flex-row items-start lg:items-center gap-4">
```

---

## ⚡ 動畫與過渡效果

### 標準過渡時間
```css
/* 快速過渡（hover效果） */
transition-colors duration-200

/* 一般過渡（元素進出） */
transition-all duration-300

/* 慢速過渡（複雜動畫） */
transition-all duration-500
```

### 常用動畫
```vue
<!-- Hover 效果 -->
<div class="hover:shadow-md transition-shadow">
<button class="hover:bg-blue-700 transition-colors">
<div class="hover:scale-105 transition-transform">

<!-- 載入動畫 -->
<svg class="animate-spin w-5 h-5">
<div class="animate-pulse">
```

---

## ✅ 最佳實踐檢查清單

開發新頁面時，請確保：

- [ ] 使用 `min-h-screen bg-gray-50 p-6` 作為頁面容器
- [ ] 頁面標題使用 `text-3xl font-bold text-gray-900`
- [ ] 統計卡片使用 4 列響應式網格
- [ ] 卡片使用 `bg-white rounded-xl shadow-sm border border-gray-200`
- [ ] 表格 hover 使用 `hover:bg-gray-50 transition-colors duration-200`
- [ ] 按鈕使用標準樣式（primary/success/danger）
- [ ] 狀態標籤使用標準配色方案
- [ ] Modal 使用標準結構（header/body/footer）
- [ ] 輸入框使用 `focus:ring-2 focus:ring-blue-500`
- [ ] 所有互動元素都有 `transition` 效果
- [ ] 使用語義化的 SVG icon
- [ ] 確保響應式設計（sm/md/lg/xl）

---

## 📝 命名規範

### CSS Class 命名
- 使用 Tailwind 工具類別，避免自訂 class
- 如需自訂，使用 BEM 命名法：`block__element--modifier`

### 變數命名
```javascript
// 使用 camelCase
const currentPage = ref(1)
const showDetailModal = ref(false)

// 布林值使用 is/has/should 前綴
const isLoading = ref(false)
const hasError = ref(false)

// 陣列使用複數
const customers = ref([])
const reservations = ref([])
```

---

## 🔍 常見問題

### Q: 何時使用 `rounded-lg` vs `rounded-md`？
A: 
- `rounded-lg`：大型元素（卡片、Modal、大按鈕）
- `rounded-md`：中型元素（表單輸入框、小按鈕）
- `rounded-full`：標籤、圓形頭像

### Q: 何時使用 `shadow-sm` vs `shadow-md`？
A: 
- `shadow-sm`：預設狀態（卡片、輸入框）
- `shadow-md`：hover 狀態
- `shadow-xl`：Modal、彈出層

### Q: 何時使用漸變按鈕？
A: 主要的 CTA（Call-to-Action）按鈕，如「新增」、「搜尋」、「確認」等重要操作。

---

## 📚 參考資源

- [Tailwind CSS 官方文檔](https://tailwindcss.com/docs)
- [Heroicons](https://heroicons.com/)（SVG icon 來源）
- [Headless UI](https://headlessui.com/)（無樣式組件）

---

**最後更新：2025-10-12**
