<template>
  <div class="min-h-screen bg-gray-50 p-6">
    <!-- 頁面標題區域 -->
    <div class="mb-8">
      <h1 class="text-3xl font-bold text-gray-900">活動日誌</h1>
      <p class="text-gray-600 mt-2">系統管理員操作記錄與審計追蹤</p>
    </div>

    <!-- 統計卡片 -->
    <div v-if="stats" class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
      <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm text-gray-600 font-medium">總活動數</p>
            <p class="text-3xl font-bold text-gray-900 mt-2">{{ stats.total_activities?.toLocaleString() || 0 }}</p>
          </div>
          <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm text-gray-600 font-medium">失敗操作</p>
            <p class="text-3xl font-bold text-red-600 mt-2">{{ stats.failed_operations?.toLocaleString() || 0 }}</p>
          </div>
          <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center">
            <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm text-gray-600 font-medium">活躍模組</p>
            <p class="text-3xl font-bold text-green-600 mt-2">{{ stats.by_module?.length || 0 }}</p>
          </div>
          <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
            </svg>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm text-gray-600 font-medium">活躍用戶</p>
            <p class="text-3xl font-bold text-purple-600 mt-2">{{ stats.by_user?.length || 0 }}</p>
          </div>
          <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
            <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
            </svg>
          </div>
        </div>
      </div>
    </div>

    <!-- 篩選和搜尋 -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
      <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
        <!-- 搜尋 -->
        <div class="md:col-span-2">
          <label class="block text-sm font-medium text-gray-700 mb-2">搜尋</label>
          <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
              <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
              </svg>
            </div>
            <input
              v-model="filters.search"
              type="text"
              placeholder="搜尋操作描述、使用者..."
              class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              @input="debouncedFetch"
            />
          </div>
        </div>

        <!-- 模組篩選 -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">模組</label>
          <select
            v-model="filters.module"
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            @change="fetchLogs"
          >
            <option value="">所有模組</option>
            <option value="users">使用者</option>
            <option value="customers">客戶</option>
            <option value="services">服務</option>
            <option value="reservations">預約</option>
            <option value="available_times">可預約時段</option>
            <option value="settings">設定</option>
            <option value="tenants">租戶</option>
            <option value="check_in">報到</option>
          </select>
        </div>

        <!-- 操作類型篩選 -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">操作</label>
          <select
            v-model="filters.action"
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            @change="fetchLogs"
          >
            <option value="">所有操作</option>
            <option value="created">新增</option>
            <option value="updated">更新</option>
            <option value="deleted">刪除</option>
            <option value="login">登入</option>
            <option value="logout">登出</option>
            <option value="viewed">查看</option>
            <option value="exported">匯出</option>
          </select>
        </div>

        <!-- 狀態篩選 -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">狀態</label>
          <select
            v-model="filters.status"
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            @change="fetchLogs"
          >
            <option value="">所有狀態</option>
            <option value="success">成功</option>
            <option value="failed">失敗</option>
          </select>
        </div>
      </div>

      <!-- 日期範圍 -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">開始日期</label>
          <input
            v-model="filters.date_from"
            type="date"
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            @change="fetchLogs"
          />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">結束日期</label>
          <input
            v-model="filters.date_to"
            type="date"
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            @change="fetchLogs"
          />
        </div>
      </div>

      <!-- 清除篩選 -->
      <div class="mt-4 flex justify-end">
        <button
          @click="clearFilters"
          class="px-4 py-2 text-sm text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors"
        >
          清除篩選
        </button>
      </div>
    </div>

    <!-- 日誌表格 -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
      <!-- 載入狀態 -->
      <div v-if="loading" class="p-12 text-center">
        <div class="inline-flex items-center justify-center w-16 h-16 bg-blue-100 rounded-full mb-4">
          <svg class="animate-spin w-8 h-8 text-blue-600" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
        </div>
        <p class="text-gray-600 font-medium">載入中...</p>
      </div>

      <!-- 空狀態 -->
      <div v-else-if="!logs || logs.length === 0" class="p-12 text-center">
        <div class="inline-flex items-center justify-center w-16 h-16 bg-gray-100 rounded-full mb-4">
          <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
          </svg>
        </div>
        <p class="text-gray-600 font-medium">查無活動日誌</p>
      </div>

      <!-- 表格內容 -->
      <div v-else class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase">時間</th>
              <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase">使用者</th>
              <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase">模組</th>
              <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase">操作</th>
              <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase">描述</th>
              <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase">狀態</th>
              <th class="px-6 py-4 text-center text-xs font-semibold text-gray-500 uppercase">詳情</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr v-for="log in logs" :key="log.id" class="hover:bg-gray-50 transition-colors">
              <!-- 時間 -->
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                {{ formatDateTime(log.created_at) }}
              </td>

              <!-- 使用者 -->
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm font-medium text-gray-900">{{ log.user_name || '系統' }}</div>
                <div class="text-sm text-gray-500">{{ log.user_email }}</div>
              </td>

              <!-- 模組 -->
              <td class="px-6 py-4 whitespace-nowrap">
                <span class="px-3 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-800">
                  {{ getModuleLabel(log.module) }}
                </span>
              </td>

              <!-- 操作 -->
              <td class="px-6 py-4 whitespace-nowrap">
                <span :class="getActionClass(log.action)">
                  {{ getActionLabel(log.action) }}
                </span>
              </td>

              <!-- 描述 -->
              <td class="px-6 py-4 text-sm text-gray-900">
                <div class="max-w-md truncate">{{ log.description }}</div>
              </td>

              <!-- 狀態 -->
              <td class="px-6 py-4 whitespace-nowrap">
                <span :class="log.status === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'" 
                      class="px-3 py-1 text-xs font-medium rounded-full">
                  {{ log.status === 'success' ? '成功' : '失敗' }}
                </span>
              </td>

              <!-- 詳情按鈕 -->
              <td class="px-6 py-4 text-center whitespace-nowrap">
                <button
                  @click="showLogDetail(log)"
                  class="text-blue-600 hover:text-blue-800 font-medium text-sm"
                >
                  查看
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- 分頁 -->
      <div v-if="pagination && pagination.last_page > 1" class="px-6 py-4 border-t border-gray-200">
        <div class="flex items-center justify-between">
          <div class="text-sm text-gray-700">
            顯示 {{ pagination.from }} 到 {{ pagination.to }} 筆，共 {{ pagination.total }} 筆
          </div>
          <div class="flex space-x-2">
            <button
              @click="goToPage(pagination.current_page - 1)"
              :disabled="pagination.current_page === 1"
              class="px-3 py-1 border border-gray-300 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50"
            >
              上一頁
            </button>
            <button
              v-for="page in visiblePages"
              :key="page"
              @click="goToPage(page)"
              :class="page === pagination.current_page ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'"
              class="px-3 py-1 border border-gray-300 rounded-lg"
            >
              {{ page }}
            </button>
            <button
              @click="goToPage(pagination.current_page + 1)"
              :disabled="pagination.current_page === pagination.last_page"
              class="px-3 py-1 border border-gray-300 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50"
            >
              下一頁
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- 詳情 Modal -->
    <div v-if="selectedLog" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50" @click.self="selectedLog = null">
      <div class="bg-white rounded-xl shadow-xl max-w-3xl w-full max-h-[90vh] overflow-y-auto">
        <div class="p-6 border-b border-gray-200">
          <div class="flex items-center justify-between">
            <h3 class="text-xl font-bold text-gray-900">活動日誌詳情</h3>
            <button @click="selectedLog = null" class="text-gray-400 hover:text-gray-600">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
        </div>

        <div class="p-6 space-y-4">
          <!-- 基本資訊 -->
          <div class="grid grid-cols-2 gap-4">
            <div>
              <p class="text-sm font-medium text-gray-500">時間</p>
              <p class="mt-1 text-sm text-gray-900">{{ formatDateTime(selectedLog.created_at) }}</p>
            </div>
            <div>
              <p class="text-sm font-medium text-gray-500">使用者</p>
              <p class="mt-1 text-sm text-gray-900">{{ selectedLog.user_name }}</p>
              <p class="text-sm text-gray-500">{{ selectedLog.user_email }}</p>
            </div>
            <div>
              <p class="text-sm font-medium text-gray-500">模組</p>
              <p class="mt-1 text-sm text-gray-900">{{ getModuleLabel(selectedLog.module) }}</p>
            </div>
            <div>
              <p class="text-sm font-medium text-gray-500">操作</p>
              <p class="mt-1 text-sm text-gray-900">{{ getActionLabel(selectedLog.action) }}</p>
            </div>
            <div>
              <p class="text-sm font-medium text-gray-500">IP 位址</p>
              <p class="mt-1 text-sm text-gray-900">{{ selectedLog.ip_address || '-' }}</p>
            </div>
            <div>
              <p class="text-sm font-medium text-gray-500">狀態</p>
              <p class="mt-1">
                <span :class="selectedLog.status === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'" 
                      class="px-3 py-1 text-xs font-medium rounded-full">
                  {{ selectedLog.status === 'success' ? '成功' : '失敗' }}
                </span>
              </p>
            </div>
          </div>

          <!-- 描述 -->
          <div>
            <p class="text-sm font-medium text-gray-500">操作描述</p>
            <p class="mt-1 text-sm text-gray-900">{{ selectedLog.description }}</p>
          </div>

          <!-- 變更內容 -->
          <div v-if="selectedLog.old_values || selectedLog.new_values">
            <p class="text-sm font-medium text-gray-500 mb-2">變更內容</p>
            <div class="bg-gray-50 rounded-lg p-4 space-y-2">
              <div v-if="selectedLog.old_values">
                <p class="text-xs font-medium text-gray-600">變更前:</p>
                <pre class="mt-1 text-xs text-gray-900 overflow-x-auto">{{ JSON.stringify(selectedLog.old_values, null, 2) }}</pre>
              </div>
              <div v-if="selectedLog.new_values">
                <p class="text-xs font-medium text-gray-600">變更後:</p>
                <pre class="mt-1 text-xs text-gray-900 overflow-x-auto">{{ JSON.stringify(selectedLog.new_values, null, 2) }}</pre>
              </div>
            </div>
          </div>

          <!-- 錯誤訊息 -->
          <div v-if="selectedLog.error_message">
            <p class="text-sm font-medium text-gray-500">錯誤訊息</p>
            <div class="mt-1 bg-red-50 border border-red-200 rounded-lg p-3">
              <p class="text-sm text-red-800">{{ selectedLog.error_message }}</p>
            </div>
          </div>

          <!-- User Agent -->
          <div v-if="selectedLog.user_agent">
            <p class="text-sm font-medium text-gray-500">瀏覽器資訊</p>
            <p class="mt-1 text-xs text-gray-600">{{ selectedLog.user_agent }}</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { apiGet } from '../utils/api.js'

const loading = ref(false)
const logs = ref([])
const stats = ref(null)
const selectedLog = ref(null)
const pagination = ref(null)

const filters = ref({
  search: '',
  module: '',
  action: '',
  status: '',
  date_from: '',
  date_to: '',
  page: 1
})

// 防抖搜尋
let debounceTimer = null
const debouncedFetch = () => {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => {
    filters.value.page = 1
    fetchLogs()
  }, 500)
}

const fetchLogs = async () => {
  loading.value = true
  try {
    const params = { ...filters.value }
    Object.keys(params).forEach(key => !params[key] && delete params[key])
    
    const queryString = new URLSearchParams(params).toString()
    const url = queryString ? `/admin/activity-logs?${queryString}` : '/admin/activity-logs'
    const response = await apiGet(url)
    logs.value = response.data.data
    pagination.value = {
      current_page: response.data.current_page,
      last_page: response.data.last_page,
      from: response.data.from,
      to: response.data.to,
      total: response.data.total
    }
  } catch (error) {
    console.error('獲取日誌失敗:', error)
  } finally {
    loading.value = false
  }
}

const fetchStats = async () => {
  try {
    const response = await apiGet('/admin/activity-logs/stats')
    stats.value = response.data
  } catch (error) {
    console.error('獲取統計失敗:', error)
  }
}

const clearFilters = () => {
  filters.value = {
    search: '',
    module: '',
    action: '',
    status: '',
    date_from: '',
    date_to: '',
    page: 1
  }
  fetchLogs()
}

const goToPage = (page) => {
  filters.value.page = page
  fetchLogs()
}

const visiblePages = computed(() => {
  if (!pagination.value) return []
  const current = pagination.value.current_page
  const last = pagination.value.last_page
  const pages = []
  
  for (let i = Math.max(1, current - 2); i <= Math.min(last, current + 2); i++) {
    pages.push(i)
  }
  
  return pages
})

const showLogDetail = (log) => {
  selectedLog.value = log
}

const formatDateTime = (datetime) => {
  if (!datetime) return '-'
  return new Date(datetime).toLocaleString('zh-TW', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit'
  })
}

const getModuleLabel = (module) => {
  const labels = {
    users: '使用者',
    customers: '客戶',
    services: '服務',
    reservations: '預約',
    available_times: '可預約時段',
    settings: '設定',
    tenants: '租戶',
    check_in: '報到',
    auth: '認證'
  }
  return labels[module] || module
}

const getActionLabel = (action) => {
  const labels = {
    created: '新增',
    updated: '更新',
    deleted: '刪除',
    login: '登入',
    logout: '登出',
    viewed: '查看',
    exported: '匯出',
    confirmed: '確認',
    cancelled: '取消',
    checked_in: '報到',
    marked_no_show: '標記爽約'
  }
  return labels[action] || action
}

const getActionClass = (action) => {
  const classes = {
    created: 'px-3 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800',
    updated: 'px-3 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-800',
    deleted: 'px-3 py-1 text-xs font-medium rounded-full bg-red-100 text-red-800',
    login: 'px-3 py-1 text-xs font-medium rounded-full bg-purple-100 text-purple-800',
    logout: 'px-3 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-800'
  }
  return classes[action] || 'px-3 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-800'
}

onMounted(() => {
  fetchLogs()
  fetchStats()
})
</script>
