# 多租戶 B2B 前端架構文件

## 目錄

- [前端概覽](#前端概覽)
- [多租戶設計](#多租戶設計)
- [技術棧](#技術棧)
- [專案結構](#專案結構)
- [路由系統](#路由系統)
- [組件架構](#組件架構)
- [狀態管理](#狀態管理)
- [API 整合](#api-整合)
- [UI 設計規範](#ui-設計規範)
- [最佳實踐](#最佳實踐)

## 前端概覽

多租戶 B2B LINE 預約系統前端採用 **Vue.js 3** 配合 **Composition API**，使用 **Tailwind CSS** 作為 UI 框架，透過 **Vite** 進行快速構建。支援多租戶獨立運營的企業級管理介面。

### 核心特性

- **多租戶支持**: 租戶隔離與狀態管理
- **現代化 UI**: 基於 Tailwind CSS 的響應式設計
- **快速開發**: Vite HMR 提供極速的開發體驗
- **安全認證**: Laravel Sanctum + 租戶驗證
- **響應式設計**: 支援桌面、平板、手機
- **類型安全**: 使用 Vue 3 Composition API
- **狀態管理**: Pinia 集中式狀態管理

## 多租戶設計

前端透過以下機制支援多租戶架構：

- **租戶識別**: URL slug 或 subdomain 識別
- **資料隔離**: API 請求自動帶入租戶資訊
- **狀態管理**: 租戶相關狀態在 Pinia store 中管理
- **權限控制**: 基於租戶的組件與功能顯示

## 技術棧

| 技術 | 版本 | 用途 |
|------|------|------|
| Vue.js | 3.5.17 | 前端框架 |
| Vite | 7.0.0 | 構建工具 |
| Vue Router | 4.5.1 | 路由管理 |
| Pinia | 3.0.3 | 狀態管理 |
| Axios | 1.10.0 | HTTP 客戶端 |
| Tailwind CSS | 3.4 | CSS 框架 |
| Heroicons | 2.2.0 | 圖示庫 |
| FullCalendar | 6.1.18 | 日曆組件 |
| DOMPurify | 3.1.7 | XSS 防護 |

## 專案結構

```
frontend/
├── public/                     # 靜態資源
│   └── favicon.ico
│
├── src/
│   ├── main.js                # 應用入口
│   ├── App.vue                # 根組件
│   ├── router.js              # 路由配置
│   ├── style.css              # 全局樣式
│   │
│   ├── pages/                 # 頁面組件
│   │   ├── Dashboard.vue      # 儀表板
│   │   ├── Login.vue          # 登入頁
│   │   ├── Customers.vue      # 客戶管理
│   │   ├── Reservations.vue   # 預約管理
│   │   ├── CheckIn.vue        # 報到管理
│   │   ├── Services.vue       # 服務管理
│   │   ├── AvailableTimes.vue # 時段管理
│   │   ├── Settings.vue       # 系統設定
│   │   ├── Profile.vue        # 個人資料
│   │   └── NotFound.vue       # 404 頁面
│   │
│   ├── components/            # 可重用組件
│   │   ├── DefaultLayout.vue  # 預設佈局
│   │   ├── Sidebar.vue        # 側邊欄
│   │   ├── TopBar.vue         # 頂部導航
│   │   ├── StatCard.vue       # 統計卡片
│   │   ├── Modal.vue          # 對話框
│   │   ├── Pagination.vue     # 分頁組件
│   │   └── ...
│   │
│   ├── composables/           # 組合式函數
│   │   ├── useAuth.js         # 認證邏輯
│   │   ├── useApi.js          # API 調用
│   │   ├── useNotification.js # 通知邏輯
│   │   └── ...
│   │
│   ├── utils/                 # 工具函數
│   │   ├── api.js             # API 配置
│   │   ├── constants.js       # 常數定義
│   │   ├── formatter.js       # 格式化工具
│   │   ├── validator.js       # 驗證工具
│   │   └── helpers.js         # 輔助函數
│   │
│   └── assets/                # 資源文件
│       ├── images/
│       └── icons/
│
├── index.html                 # HTML 模板
├── vite.config.js             # Vite 配置
├── tailwind.config.js         # Tailwind 配置
├── postcss.config.cjs         # PostCSS 配置
└── package.json               # 依賴管理
```

## 🗺 路由系統

### 路由配置

```javascript
const routes = [
    {
        path: "/",
        component: DefaultLayout,
        children: [
            { path: '/', name: 'Dashboard', component: Dashboard },
            { path: 'customers', name: 'Customers', component: Customers },
            { path: 'check-in', name: 'CheckIn', component: CheckIn },
            { path: 'services', name: 'Services', component: Services },
            { path: 'available-times', name: 'AvailableTimes', component: AvailableTimes },
            { path: 'reservations', name: 'Reservations', component: Reservations },
            { path: 'profile', name: 'Profile', component: Profile },
            { path: 'settings', name: 'Settings', component: Settings },
        ]
    },
    {
        path: "/login",
        name: "Login",
        component: Login
    },
    {
        path: "/:pathMatch(.*)*",
        name: "NotFound",
        component: NotFound
    }
];
```

### 路由守衛

```javascript
router.beforeEach(async (to, from, next) => {
    const token = localStorage.getItem('token')
    const user = JSON.parse(localStorage.getItem('user') || 'null')
    
    // 公開頁面
    const publicPages = ['Login', 'NotFound']
    const isPublicPage = publicPages.includes(to.name)
    
    if (isPublicPage) {
        next()
        return
    }
    
    // 檢查登入
    if (!token) {
        next({ name: 'Login' })
        return
    }
    
    // 驗證 Token
    const isValid = await validateToken()
    if (!isValid) {
        localStorage.clear()
        next({ name: 'Login' })
        return
    }
    
    // 檢查管理員權限
    const adminOnlyPages = ['Dashboard', 'Customers', 'CheckIn', 'Services', 
                           'AvailableTimes', 'Reservations', 'Settings']
    if (adminOnlyPages.includes(to.name) && user.role !== 'admin') {
        alert('權限不足，僅限管理員訪問')
        next({ name: 'Login' })
        return
    }
    
    next()
})
```

## 🧩 組件架構

### 頁面組件

#### 1. Dashboard.vue - 儀表板
**功能**: 顯示系統統計數據、近期預約、快速操作

**主要元素**:
- 統計卡片 (今日預約、待確認、總客戶、月收入)
- 近期預約列表
- 快速操作按鈕

#### 2. Customers.vue - 客戶管理
**功能**: 客戶資料的 CRUD 操作

**主要功能**:
- 客戶列表展示（分頁、搜尋、篩選）
- 新增/編輯客戶
- 客戶詳情查看
- 封鎖/解封客戶

#### 3. Reservations.vue - 預約管理
**功能**: 預約資料管理

**主要功能**:
- 預約列表（多狀態篩選）
- 新增預約
- 確認/取消預約
- 預約詳情查看

#### 4. CheckIn.vue - 報到管理
**功能**: 客戶報到與付款記錄

**主要功能**:
- 今日預約列表
- 報到操作
- 標記爽約
- 記錄付款

#### 5. Services.vue - 服務管理
**功能**: 服務項目管理

**主要功能**:
- 服務列表
- 新增/編輯/刪除服務
- 啟用/停用服務

#### 6. AvailableTimes.vue - 時段管理
**功能**: 可預約時段管理

**主要功能**:
- 時段列表（日曆視圖）
- 新增/編輯/刪除時段
- 查看預約狀況

### 可重用組件

#### DefaultLayout.vue
```vue
<template>
  <div class="min-h-screen bg-gray-100">
    <Sidebar />
    <div class="ml-64">
      <TopBar />
      <main class="p-6">
        <router-view />
      </main>
    </div>
  </div>
</template>
```

#### StatCard.vue
```vue
<template>
  <div class="bg-white rounded-xl shadow-sm p-6">
    <div class="flex items-center justify-between">
      <div>
        <p class="text-sm text-gray-600">{{ title }}</p>
        <p class="text-2xl font-bold text-gray-900 mt-2">{{ value }}</p>
        <p class="text-xs text-gray-500 mt-1">{{ subtitle }}</p>
      </div>
      <div :class="`w-12 h-12 ${bgColor} rounded-lg flex items-center justify-center`">
        <component :is="icon" :class="`w-6 h-6 ${iconColor}`" />
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  title: String,
  value: [String, Number],
  subtitle: String,
  icon: Object,
  bgColor: String,
  iconColor: String
})
</script>
```

## 📦 狀態管理

### Pinia Stores

#### authStore.js
```javascript
import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

export const useAuthStore = defineStore('auth', () => {
  const user = ref(null)
  const token = ref(null)
  
  const isAuthenticated = computed(() => !!token.value)
  const isAdmin = computed(() => user.value?.role === 'admin')
  
  function setUser(userData) {
    user.value = userData
    localStorage.setItem('user', JSON.stringify(userData))
  }
  
  function setToken(tokenValue) {
    token.value = tokenValue
    localStorage.setItem('token', tokenValue)
  }
  
  function logout() {
    user.value = null
    token.value = null
    localStorage.removeItem('user')
    localStorage.removeItem('token')
  }
  
  return { user, token, isAuthenticated, isAdmin, setUser, setToken, logout }
})
```

## API 整合

### API 配置 (utils/api.js)

```javascript
import axios from 'axios'

const api = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL,
  withCredentials: true,
  headers: {
    'Accept': 'application/json',
    'Content-Type': 'application/json'
  }
})

// 請求攔截器
api.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem('token')
    if (token) {
      config.headers.Authorization = `Bearer ${token}`
    }
    return config
  },
  (error) => Promise.reject(error)
)

// 響應攔截器
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      localStorage.clear()
      window.location.href = '/login'
    }
    return Promise.reject(error)
  }
)

export default api
```

### API 調用範例

```javascript
// 獲取客戶列表
export async function getCustomers(params) {
  const response = await api.get('/customers', { params })
  return response.data
}

// 創建預約
export async function createReservation(data) {
  const response = await api.post('/reservations', data)
  return response.data
}

// 更新客戶
export async function updateCustomer(id, data) {
  const response = await api.put(`/customers/${id}`, data)
  return response.data
}
```

## UI 設計規範

### 色彩系統

```javascript
colors: {
  primary: '#3B82F6',      // 藍色
  success: '#10B981',      // 綠色
  warning: '#F59E0B',      // 黃色
  danger: '#EF4444',       // 紅色
  gray: {
    50: '#F9FAFB',
    100: '#F3F4F6',
    500: '#6B7280',
    900: '#111827'
  }
}
```

### 標準組件樣式

**按鈕**:
```html
<!-- Primary Button -->
<button class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
  按鈕文字
</button>

<!-- Success Button -->
<button class="px-3 py-1.5 bg-green-100 text-green-700 rounded-md hover:bg-green-200">
  確認
</button>
```

**卡片**:
```html
<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
  <!-- 卡片內容 -->
</div>
```

**表格**:
```html
<table class="min-w-full divide-y divide-gray-200">
  <thead class="bg-gray-50">
    <tr>
      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
        標題
      </th>
    </tr>
  </thead>
  <tbody class="bg-white divide-y divide-gray-200">
    <tr class="hover:bg-gray-50 transition-colors">
      <td class="px-6 py-4 whitespace-nowrap">內容</td>
    </tr>
  </tbody>
</table>
```

詳細設計規範請參考: [UI_DESIGN_GUIDE.md](./frontend/UI_DESIGN_GUIDE.md)

## ✅ 最佳實踐

### 1. 組件命名
```
PascalCase for components: CustomerList.vue
camelCase for methods: getUserData()
kebab-case for events: @update-customer
```

### 2. Composition API 使用
```vue
<script setup>
import { ref, computed, onMounted } from 'vue'

// 響應式數據
const customers = ref([])
const loading = ref(false)

// 計算屬性
const activeCustomers = computed(() => 
  customers.value.filter(c => c.status === 'active')
)

// 方法
async function fetchCustomers() {
  loading.value = true
  try {
    const data = await getCustomers()
    customers.value = data
  } finally {
    loading.value = false
  }
}

// 生命週期
onMounted(() => {
  fetchCustomers()
})
</script>
```

### 3. 錯誤處理
```javascript
try {
  await api.post('/reservations', data)
  showNotification('預約創建成功', 'success')
} catch (error) {
  const message = error.response?.data?.message || '操作失敗'
  showNotification(message, 'error')
}
```

### 4. 載入狀態
```vue
<template>
  <div v-if="loading" class="text-center py-8">
    <svg class="animate-spin h-8 w-8 mx-auto text-blue-600">...</svg>
  </div>
  <div v-else>
    <!-- 內容 -->
  </div>
</template>
```

### 5. 條件渲染
```vue
<template>
  <!-- 使用 v-if 進行條件渲染 -->
  <div v-if="customers.length > 0">
    <!-- 客戶列表 -->
  </div>
  <div v-else class="text-center py-8 text-gray-500">
    暫無資料
  </div>
</template>
```

## 開發指南

### 啟動開發伺服器
```bash
npm run dev
```

### 構建生產版本
```bash
npm run build
```

### 預覽生產版本
```bash
npm run preview
```

### 代碼檢查
```bash
npm run lint
npm run format
```

## 📚 參考資源

- [Vue 3 文件](https://vuejs.org/)
- [Vite 文件](https://vitejs.dev/)
- [Tailwind CSS 文件](https://tailwindcss.com/)
- [Pinia 文件](https://pinia.vuejs.org/)
- [Vue Router 文件](https://router.vuejs.org/)

---

**文件版本**: v1.0.0  
**最後更新**: 2025-10-23  
**維護者**: 傅盛祥 (Spencer Kuku)