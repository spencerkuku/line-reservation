<template>
  <div class="min-h-screen bg-gray-50 p-6">
    <!-- 頁面標題區域 -->
    <div class="mb-8">
      <h1 class="text-3xl font-bold text-gray-900">LINE 訊息日誌</h1>
      <p class="text-gray-600 mt-2">LINE Bot 訊息互動記錄與分析</p>
    </div>

    <!-- 統計卡片 -->
    <div v-if="stats" class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
      <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm text-gray-600 font-medium">總訊息數</p>
            <p class="text-3xl font-bold text-gray-900 mt-2">{{ stats.total_messages?.toLocaleString() || 0 }}</p>
          </div>
          <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
            </svg>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm text-gray-600 font-medium">接收訊息</p>
            <p class="text-3xl font-bold text-blue-600 mt-2">{{ getDirectionCount('incoming') }}</p>
          </div>
          <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
            </svg>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm text-gray-600 font-medium">發送訊息</p>
            <p class="text-3xl font-bold text-purple-600 mt-2">{{ getDirectionCount('outgoing') }}</p>
          </div>
          <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
            <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-4-4H9a1.994 1.994 0 01-1.414-.586m0 0L11 14h4a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2v4l.586-.586z" />
            </svg>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm text-gray-600 font-medium">活躍用戶</p>
            <p class="text-3xl font-bold text-orange-600 mt-2">{{ stats.top_users?.length || 0 }}</p>
          </div>
          <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center">
            <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
            </svg>
          </div>
        </div>
      </div>
    </div>

    <!-- 篩選和搜尋 -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
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
              placeholder="搜尋 LINE ID、訊息內容..."
              class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              @input="debouncedFetch"
            />
          </div>
        </div>

        <!-- 訊息方向篩選 -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">方向</label>
          <select
            v-model="filters.direction"
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            @change="fetchLogs"
          >
            <option value="">所有方向</option>
            <option value="incoming">接收</option>
            <option value="outgoing">發送</option>
          </select>
        </div>

        <!-- 訊息類型篩選 -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">類型</label>
          <select
            v-model="filters.message_type"
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            @change="fetchLogs"
          >
            <option value="">所有類型</option>
            <option value="text">文字</option>
            <option value="image">圖片</option>
            <option value="sticker">貼圖</option>
            <option value="location">位置</option>
            <option value="postback">Postback</option>
            <option value="follow">關注</option>
            <option value="unfollow">取消關注</option>
          </select>
        </div>
      </div>

      <!-- 租戶和日期範圍 -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
        <!-- 租戶篩選（僅系統管理員） -->
        <div v-if="isSystemAdmin">
          <label class="block text-sm font-medium text-gray-700 mb-2">租戶</label>
          <select
            v-model="filters.tenant_id"
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            @change="fetchLogs"
          >
            <option value="">所有租戶</option>
            <option v-for="tenant in tenants" :key="tenant.id" :value="tenant.id">
              {{ tenant.name }}
            </option>
          </select>
        </div>

        <div :class="isSystemAdmin ? '' : 'md:col-span-1'">
          <label class="block text-sm font-medium text-gray-700 mb-2">開始日期</label>
          <input
            v-model="filters.date_from"
            type="date"
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            @change="fetchLogs"
          />
        </div>
        <div :class="isSystemAdmin ? '' : 'md:col-span-1'">
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

    <!-- 訊息表格 -->
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
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
          </svg>
        </div>
        <p class="text-gray-600 font-medium">查無 LINE 訊息記錄</p>
      </div>

      <!-- 表格內容 -->
      <div v-else class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase">時間</th>
              <th v-if="isSystemAdmin" class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase">租戶</th>
              <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase">方向</th>
              <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase">類型</th>
              <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase">訊息內容</th>
              <th class="px-6 py-4 text-center text-xs font-semibold text-gray-500 uppercase">詳情</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr v-for="log in logs" :key="log.id" class="hover:bg-gray-50 transition-colors">
              <!-- 時間 -->
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                {{ formatDateTime(log.created_at) }}
              </td>

              <!-- 租戶（僅系統管理員可見） -->
              <td v-if="isSystemAdmin" class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm font-medium text-gray-900">{{ log.tenant?.name || '-' }}</div>
                <div class="text-sm text-gray-500">{{ log.tenant?.slug || '-' }}</div>
              </td>

              <!-- 方向 -->
              <td class="px-6 py-4 whitespace-nowrap">
                <span :class="log.direction === 'incoming' ? 'bg-blue-100 text-blue-800' : 'bg-purple-100 text-purple-800'" 
                      class="px-3 py-1 text-xs font-medium rounded-full">
                  {{ log.direction === 'incoming' ? '接收' : '發送' }}
                </span>
              </td>

              <!-- 類型 -->
              <td class="px-6 py-4 whitespace-nowrap">
                <span class="px-3 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-800">
                  {{ getMessageTypeLabel(log.message_type) }}
                </span>
              </td>

              <!-- 訊息內容預覽 -->
              <td class="px-6 py-4 text-sm text-gray-900">
                <div class="max-w-md truncate">{{ getMessagePreview(log) }}</div>
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
            <h3 class="text-xl font-bold text-gray-900">訊息詳情</h3>
            <button @click="selectedLog = null" class="text-gray-400 hover:text-gray-600">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
        </div>

        <div class="p-6 space-y-4">
          <!-- 租戶資訊（僅系統管理員） -->
          <div v-if="isSystemAdmin && selectedLog.tenant" class="bg-purple-50 border border-purple-200 rounded-lg p-4">
            <h4 class="text-sm font-semibold text-purple-900 mb-3">租戶資訊</h4>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <p class="text-xs font-medium text-purple-700">租戶名稱</p>
                <p class="mt-1 text-sm text-purple-900">{{ selectedLog.tenant.name }}</p>
              </div>
              <div>
                <p class="text-xs font-medium text-purple-700">識別碼</p>
                <p class="mt-1 text-sm text-purple-900">{{ selectedLog.tenant.slug }}</p>
              </div>
            </div>
          </div>

          <!-- 基本資訊 -->
          <div class="grid grid-cols-2 gap-4">
            <div>
              <p class="text-sm font-medium text-gray-500">時間</p>
              <p class="mt-1 text-sm text-gray-900">{{ formatDateTime(selectedLog.created_at) }}</p>
            </div>
            <div>
              <p class="text-sm font-medium text-gray-500">方向</p>
              <p class="mt-1">
                <span :class="selectedLog.direction === 'incoming' ? 'bg-blue-100 text-blue-800' : 'bg-purple-100 text-purple-800'" 
                      class="px-3 py-1 text-xs font-medium rounded-full">
                  {{ selectedLog.direction === 'incoming' ? '接收' : '發送' }}
                </span>
              </p>
            </div>
            <div>
              <p class="text-sm font-medium text-gray-500">訊息類型</p>
              <p class="mt-1 text-sm text-gray-900">{{ getMessageTypeLabel(selectedLog.message_type) }}</p>
            </div>
          </div>

          <!-- 訊息內容 -->
          <div>
            <p class="text-sm font-medium text-gray-500 mb-2">訊息內容</p>
            <div class="bg-gray-50 rounded-lg p-4">
              <pre class="text-xs text-gray-900 overflow-x-auto whitespace-pre-wrap">{{ JSON.stringify(selectedLog.message_content, null, 2) }}</pre>
            </div>
          </div>

          <!-- Bot 回應 -->
          <div v-if="selectedLog.bot_response">
            <p class="text-sm font-medium text-gray-500 mb-2">Bot 回應</p>
            <div class="bg-blue-50 rounded-lg p-4">
              <pre class="text-xs text-gray-900 overflow-x-auto whitespace-pre-wrap">{{ JSON.stringify(selectedLog.bot_response, null, 2) }}</pre>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { apiGet } from '../utils/api.js'
import { useAuth } from '../composables/useAuth'

const { user } = useAuth()
const isSystemAdmin = computed(() => user.value?.role === 'system_admin')

const loading = ref(false)
const logs = ref([])
const stats = ref(null)
const selectedLog = ref(null)
const pagination = ref(null)
const tenants = ref([])

const filters = ref({
  search: '',
  tenant_id: '',
  message_type: '',
  direction: '',
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
    const url = queryString ? `/line-message-logs?${queryString}` : '/line-message-logs'
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
    const response = await apiGet('/line-message-logs/stats')
    stats.value = response.data
  } catch (error) {
    console.error('獲取統計失敗:', error)
  }
}

const fetchTenants = async () => {
  if (!isSystemAdmin.value) return
  
  try {
    const response = await apiGet('/line-message-logs/tenants')
    tenants.value = response.data.data
  } catch (error) {
    console.error('獲取租戶列表失敗:', error)
  }
}

const clearFilters = () => {
  filters.value = {
    search: '',
    tenant_id: '',
    message_type: '',
    direction: '',
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

const getDirectionCount = (direction) => {
  if (!stats.value || !stats.value.by_direction) return 0
  const item = stats.value.by_direction.find(d => d.direction === direction)
  return item ? item.count : 0
}

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
    minute: '2-digit',
    second: '2-digit'
  })
}

const getMessageTypeLabel = (type) => {
  const labels = {
    text: '文字',
    image: '圖片',
    video: '影片',
    audio: '音訊',
    file: '檔案',
    location: '位置',
    sticker: '貼圖',
    postback: 'Postback',
    follow: '關注',
    unfollow: '取消關注',
    join: '加入群組',
    leave: '離開群組'
  }
  return labels[type] || type
}

const getMessagePreview = (log) => {
  if (!log.message_content) return '-'
  
  if (typeof log.message_content === 'string') {
    return log.message_content
  }
  
  if (log.message_content.text) {
    return log.message_content.text
  }
  
  if (log.message_content.type) {
    return `[${getMessageTypeLabel(log.message_content.type)}]`
  }
  
  return JSON.stringify(log.message_content).substring(0, 50) + '...'
}

onMounted(() => {
  fetchLogs()
  fetchStats()
  fetchTenants()
})
</script>
