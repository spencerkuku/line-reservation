<template>
  <div class="min-h-screen bg-gray-50 p-6">
    <!-- 頁面標題 -->
    <div class="mb-8">
      <h1 class="text-3xl font-bold text-gray-900">儀表板</h1>
      <p class="text-gray-600 mt-2">歡迎回來，{{ currentUser?.name }}！這是您的系統概覽。</p>
    </div>

    <!-- 非管理員提示 -->
    <div v-if="!isAdmin" class="bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-xl p-6 mb-8">
      <div class="flex items-center">
        <div class="flex-shrink-0">
          <svg class="h-8 w-8 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>
        <div class="ml-4">
          <h3 class="text-lg font-semibold text-blue-900">歡迎使用 LINE 預約系統</h3>
          <p class="text-blue-700 mt-1">您目前是一般用戶身份。如需使用完整管理功能，請聯絡系統管理員。</p>
        </div>
      </div>
    </div>

    <!-- 管理員儀表板 -->
    <div v-if="isAdmin" class="space-y-8">
      <!-- 關鍵指標卡片 -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        <!-- 今日預約 -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-600">今日預約</p>
              <p class="text-3xl font-bold text-gray-900 mt-2">{{ stats.today_reservations || 0 }}</p>
              <p class="text-sm text-green-600 mt-1">
                <span class="inline-flex items-center">
                  <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M5.293 9.707a1 1 0 010-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 01-1.414 1.414L11 7.414V15a1 1 0 11-2 0V7.414L6.707 9.707a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                  </svg>
                  活躍中
                </span>
              </p>
            </div>
            <div class="flex-shrink-0">
              <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
              </div>
            </div>
          </div>
        </div>

        <!-- 總客戶數 -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-600">總客戶數</p>
              <p class="text-3xl font-bold text-gray-900 mt-2">{{ stats.total_customers || 0 }}</p>
              <p class="text-sm text-blue-600 mt-1">
                VIP 客戶: {{ stats.vip_customers || 0 }}
              </p>
            </div>
            <div class="flex-shrink-0">
              <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z" />
                </svg>
              </div>
            </div>
          </div>
        </div>

        <!-- 本月營收 -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-600">本月營收</p>
              <p class="text-3xl font-bold text-gray-900 mt-2">NT$ {{ formatCurrency(stats.this_month_revenue || 0) }}</p>
              <p class="text-sm text-purple-600 mt-1">
                預約數: {{ stats.this_month_reservations || 0 }}
                <span v-if="stats.avg_reservation_value" class="ml-2">
                  (平均: NT$ {{ formatCurrency(stats.avg_reservation_value) }})
                </span>
              </p>
            </div>
            <div class="flex-shrink-0">
              <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1" />
                </svg>
              </div>
            </div>
          </div>
        </div>

        <!-- 系統狀態 -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-600">系統狀態</p>
              <p class="text-2xl font-bold text-green-600 mt-2">正常運行</p>
              <p class="text-sm text-gray-500 mt-1">
                服務數: {{ stats.active_services || 0 }}
              </p>
            </div>
            <div class="flex-shrink-0">
              <div class="w-12 h-12 bg-emerald-100 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- 圖表和表格區域 -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- 預約趨勢圖表 -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
          <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-semibold text-gray-900">預約趨勢</h3>
            <span class="text-sm text-gray-500">最近 7 天</span>
          </div>
          
          <!-- 簡化的圖表展示 -->
          <div class="space-y-4">
            <div v-for="(data, index) in chartData" :key="index" class="flex items-center justify-between">
              <div class="flex items-center space-x-3">
                <div class="w-3 h-3 rounded-full" :class="getChartColor(index)"></div>
                <span class="text-sm font-medium text-gray-700">{{ data.date }}</span>
              </div>
              <div class="flex items-center space-x-4">
                <div class="flex-1 bg-gray-200 rounded-full h-2 w-24">
                  <div 
                    class="h-2 rounded-full transition-all duration-300"
                    :class="getChartColor(index)"
                    :style="{ width: (data.count / Math.max(...chartData.map(d => d.count)) * 100) + '%' }"
                  ></div>
                </div>
                <span class="text-sm font-bold text-gray-900 w-8 text-right">{{ data.count }}</span>
              </div>
            </div>
          </div>
          
          <!-- 數據刷新按鈕 -->
          <div class="flex justify-end mt-4">
            <button
              @click="fetchDashboardData"
              :disabled="loading"
              class="inline-flex items-center px-3 py-1 bg-blue-50 text-blue-600 text-xs font-medium rounded-lg hover:bg-blue-100 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 transition-colors"
            >
              <svg class="w-3 h-3 mr-1" :class="{'animate-spin': loading}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
              </svg>
              刷新數據
            </button>
          </div>
        </div>

        <!-- 熱門服務 -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
          <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-semibold text-gray-900">熱門服務</h3>
            <span class="text-sm text-gray-500">本月排行</span>
          </div>
          
          <div class="space-y-4">
            <div v-for="(service, index) in topServicesData" :key="service.id" class="flex items-center justify-between p-3 rounded-lg hover:bg-gray-50 transition-colors">
              <div class="flex items-center space-x-4">
                <div class="flex-shrink-0">
                  <div class="w-8 h-8 rounded-full bg-gradient-to-r from-blue-500 to-purple-600 flex items-center justify-center text-white text-sm font-bold">
                    {{ index + 1 }}
                  </div>
                </div>
                <div>
                  <p class="text-sm font-medium text-gray-900">{{ service.name }}</p>
                  <p class="text-xs text-gray-500">
                    {{ service.price ? `NT$ ${Number(service.price).toLocaleString()}` : '免費服務' }}
                    <span v-if="service.month_revenue" class="ml-2 text-purple-600">
                      (本月營收: NT$ {{ Number(service.month_revenue).toLocaleString() }})
                    </span>
                  </p>
                </div>
              </div>
              <div class="text-right">
                <p class="text-sm font-bold text-gray-900">{{ service.count || 0 }} 次</p>
                <p class="text-xs text-gray-500">預約</p>
              </div>
            </div>
            
            <div v-if="topServicesData.length === 0" class="text-center py-8 text-gray-500">
              <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2M4 13h2m13-8l-4 4m0 0l-4-4m4 4V3" />
              </svg>
              <p class="mt-2 text-sm">暫無熱門服務數據</p>
            </div>
          </div>
        </div>
      </div>

      <!-- 最近預約和系統通知 -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- 最近預約 -->
        <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-gray-200 p-6">
          <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-semibold text-gray-900">最近預約</h3>
            <router-link to="/reservations" class="text-sm text-blue-600 hover:text-blue-800 font-medium">
              查看全部 →
            </router-link>
          </div>
          
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">客戶</th>
                  <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">服務</th>
                  <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">時間</th>
                  <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">狀態</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <tr v-for="reservation in recentReservations" :key="reservation.id" class="hover:bg-gray-50">
                  <td class="px-4 py-3 whitespace-nowrap">
                    <div class="text-sm font-medium text-gray-900">{{ reservation.customer?.name || '未知客戶' }}</div>
                  </td>
                  <td class="px-4 py-3 whitespace-nowrap">
                    <div class="text-sm text-gray-900">{{ reservation.service?.name || '未知服務' }}</div>
                  </td>
                  <td class="px-4 py-3 whitespace-nowrap">
                    <div class="text-sm text-gray-900">{{ formatDate(reservation.reservation_date) }}</div>
                  </td>
                  <td class="px-4 py-3 whitespace-nowrap">
                    <span :class="getStatusClass(reservation.status)" class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full">
                      {{ getStatusText(reservation.status) }}
                    </span>
                  </td>
                </tr>
                <tr v-if="recentReservations.length === 0">
                  <td colspan="4" class="px-4 py-8 text-center text-gray-500">暫無最近預約記錄</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- 系統通知 -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
          <h3 class="text-lg font-semibold text-gray-900 mb-6">系統通知</h3>
          
          <div class="space-y-4">
            <div v-for="notice in notices" :key="notice.id" class="p-4 rounded-lg border-l-4" :class="getNoticeClass(notice.type)">
              <div class="flex">
                <div class="flex-shrink-0">
                  <svg class="h-5 w-5" :class="getNoticeIconClass(notice.type)" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                  </svg>
                </div>
                <div class="ml-3">
                  <p class="text-sm font-medium" :class="getNoticeTextClass(notice.type)">{{ notice.title }}</p>
                  <p class="text-sm mt-1" :class="getNoticeTextClass(notice.type, true)">{{ notice.message }}</p>
                  <p class="text-xs mt-2 opacity-75" :class="getNoticeTextClass(notice.type, true)">{{ formatDate(notice.created_at) }}</p>
                </div>
              </div>
            </div>
            
            <div v-if="notices.length === 0" class="text-center py-8 text-gray-500">
              <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-5 5v-5zM4 19h6v-2H4v2zM4 15h8v-2H4v2zM4 11h10V9H4v2z" />
              </svg>
              <p class="mt-2 text-sm">暫無系統通知</p>
            </div>
          </div>
        </div>
      </div>

      <!-- 快速操作 -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-6">快速操作</h3>
        
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
          <router-link to="/customers" class="flex items-center justify-center p-4 border-2 border-dashed border-gray-300 rounded-lg hover:border-blue-500 hover:bg-blue-50 transition-colors group">
            <div class="text-center">
              <svg class="mx-auto h-8 w-8 text-gray-400 group-hover:text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z" />
              </svg>
              <span class="mt-2 block text-sm font-medium text-gray-900 group-hover:text-blue-600">管理客戶</span>
            </div>
          </router-link>
          
          <router-link to="/services" class="flex items-center justify-center p-4 border-2 border-dashed border-gray-300 rounded-lg hover:border-green-500 hover:bg-green-50 transition-colors group">
            <div class="text-center">
              <svg class="mx-auto h-8 w-8 text-gray-400 group-hover:text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
              </svg>
              <span class="mt-2 block text-sm font-medium text-gray-900 group-hover:text-green-600">管理服務</span>
            </div>
          </router-link>
          
          <router-link to="/available-times" class="flex items-center justify-center p-4 border-2 border-dashed border-gray-300 rounded-lg hover:border-purple-500 hover:bg-purple-50 transition-colors group">
            <div class="text-center">
              <svg class="mx-auto h-8 w-8 text-gray-400 group-hover:text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <span class="mt-2 block text-sm font-medium text-gray-900 group-hover:text-purple-600">時段管理</span>
            </div>
          </router-link>
          
          <router-link to="/settings" class="flex items-center justify-center p-4 border-2 border-dashed border-gray-300 rounded-lg hover:border-yellow-500 hover:bg-yellow-50 transition-colors group">
            <div class="text-center">
              <svg class="mx-auto h-8 w-8 text-gray-400 group-hover:text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
              </svg>
              <span class="mt-2 block text-sm font-medium text-gray-900 group-hover:text-yellow-600">系統設定</span>
            </div>
          </router-link>
        </div>
      </div>
    </div>
  </div>
</template>


<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue'
import { apiGet } from '../utils/api.js'

// 響應式數據
const stats = ref({})
const chartData = ref([])
const topServicesData = ref([])
const recentReservations = ref([])
const notices = ref([])
const loading = ref(false)
const error = ref('')

// 獲取當前用戶信息
const currentUser = computed(() => {
  const userStr = localStorage.getItem('user')
  return userStr ? JSON.parse(userStr) : null
})

// 檢查是否為管理員
const isAdmin = computed(() => {
  return currentUser.value?.role === 'admin'
})

// 格式化貨幣
const formatCurrency = (amount) => {
  return new Intl.NumberFormat('zh-TW').format(amount)
}

// 格式化日期
const formatDate = (dateString) => {
  if (!dateString) return ''
  return new Date(dateString).toLocaleDateString('zh-TW', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit'
  })
}

// 獲取圖表顏色
const getChartColor = (index) => {
  const colors = [
    'bg-blue-500',
    'bg-green-500',
    'bg-purple-500',
    'bg-yellow-500',
    'bg-red-500',
    'bg-indigo-500',
    'bg-pink-500'
  ]
  return colors[index % colors.length]
}

// 獲取狀態樣式
const getStatusClass = (status) => {
  switch (status) {
    case 'confirmed':
      return 'bg-green-100 text-green-800'
    case 'pending':
      return 'bg-yellow-100 text-yellow-800'
    case 'cancelled':
      return 'bg-red-100 text-red-800'
    default:
      return 'bg-gray-100 text-gray-800'
  }
}

// 獲取狀態文字
const getStatusText = (status) => {
  switch (status) {
    case 'confirmed':
      return '已確認'
    case 'pending':
      return '待確認'
    case 'cancelled':
      return '已取消'
    default:
      return '未知'
  }
}

// 獲取通知樣式
const getNoticeClass = (type) => {
  switch (type) {
    case 'success':
      return 'border-green-400 bg-green-50'
    case 'warning':
      return 'border-yellow-400 bg-yellow-50'
    case 'error':
      return 'border-red-400 bg-red-50'
    case 'info':
    default:
      return 'border-blue-400 bg-blue-50'
  }
}

const getNoticeIconClass = (type) => {
  switch (type) {
    case 'success':
      return 'text-green-400'
    case 'warning':
      return 'text-yellow-400'
    case 'error':
      return 'text-red-400'
    case 'info':
    default:
      return 'text-blue-400'
  }
}

const getNoticeTextClass = (type, isSecondary = false) => {
  const baseClass = isSecondary ? 'text-opacity-80' : ''
  switch (type) {
    case 'success':
      return `text-green-700 ${baseClass}`
    case 'warning':
      return `text-yellow-700 ${baseClass}`
    case 'error':
      return `text-red-700 ${baseClass}`
    case 'info':
    default:
      return `text-blue-700 ${baseClass}`
  }
}

// 獲取儀表板數據
const fetchDashboardData = async () => {
  if (!isAdmin.value) return
  
  loading.value = true
  error.value = ''
  
  try {
    // 並行獲取所有數據
    const [statsRes, reservationsRes, servicesRes, noticesRes] = await Promise.all([
      apiGet('/dashboard/stats').catch(() => ({ success: false })),
      apiGet('/dashboard/reservations').catch(() => ({ success: false })),
      apiGet('/dashboard/popular-services').catch(() => ({ success: false })),
      apiGet('/dashboard/notices').catch(() => ({ success: false }))
    ])

    if (statsRes.success) {
      stats.value = statsRes.data || {}
    }

    if (reservationsRes.success) {
      recentReservations.value = (reservationsRes.data || []).slice(0, 10)
      
      // 生成圖表數據（最近7天的預約趨勢）
      const chartMap = new Map()
      const today = new Date()
      
      // 初始化最近7天的數據
      for (let i = 6; i >= 0; i--) {
        const date = new Date(today)
        date.setDate(date.getDate() - i)
        const dateKey = date.toLocaleDateString('zh-TW', { month: '2-digit', day: '2-digit' })
        chartMap.set(dateKey, { date: dateKey, count: 0 })
      }
      
      // 統計每天的預約數量
      recentReservations.value.forEach(reservation => {
        if (reservation.reservation_date) {
          const date = new Date(reservation.reservation_date)
          const dateKey = date.toLocaleDateString('zh-TW', { month: '2-digit', day: '2-digit' })
          if (chartMap.has(dateKey)) {
            chartMap.get(dateKey).count++
          }
        }
      })
      
      chartData.value = Array.from(chartMap.values())
    }

    if (servicesRes.success) {
      topServicesData.value = (servicesRes.data || []).slice(0, 5)
    }

    if (noticesRes.success) {
      notices.value = (noticesRes.data || []).slice(0, 5)
    }

  } catch (err) {
    error.value = err.message || '獲取儀表板數據失敗'
    if (import.meta.env.DEV) {
      console.error('獲取儀表板數據失敗:', err)
    }
  } finally {
    loading.value = false
  }
}

// 組件掛載時獲取數據
onMounted(() => {
  fetchDashboardData()
  
  // 設定自動刷新（每5分鐘刷新一次）
  const refreshInterval = setInterval(() => {
    if (isAdmin.value && !loading.value) {
      fetchDashboardData()
    }
  }, 5 * 60 * 1000) // 5分鐘

  // 組件卸載時清除定時器
  onUnmounted(() => {
    clearInterval(refreshInterval)
  })
})
</script>

<style scoped>
</style>