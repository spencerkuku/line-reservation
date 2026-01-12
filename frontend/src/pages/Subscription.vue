<template>
  <div class="min-h-screen bg-gray-50 pt-4 px-4 sm:px-6 lg:px-8 pb-6">
    <!-- 載入狀態 -->
    <div v-if="loading" class="flex items-center justify-center py-12">
      <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
    </div>

    <!-- 錯誤狀態 -->
    <div v-else-if="error" class="bg-red-50 border border-red-200 rounded-lg p-4">
      <div class="flex items-center">
        <svg class="h-5 w-5 text-red-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
        </svg>
        <span class="text-red-800">{{ error }}</span>
      </div>
    </div>

    <div v-else class="space-y-6">
      <!-- 頁面標題 -->
      <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200">
        <div class="flex items-center justify-between">
          <div>
            <h1 class="text-2xl font-bold text-gray-900">訂閱資訊</h1>
            <p class="mt-1 text-sm text-gray-600">查看您的訂閱狀態和使用統計</p>
          </div>
          <div v-if="subscription" class="flex items-center space-x-2">
            <span :class="[
              'px-3 py-1 rounded-full text-sm font-medium',
              statusColors[subscription.status] || 'bg-gray-100 text-gray-800'
            ]">
              {{ statusLabels[subscription.status] || subscription.status }}
            </span>
          </div>
        </div>
      </div>

      <!-- 租戶基本資訊 -->
      <div v-if="tenant" class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">租戶資訊</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <div>
            <div class="text-sm font-medium text-gray-600 mb-1">租戶名稱</div>
            <div class="text-base text-gray-900">{{ tenant.name }}</div>
          </div>
          <div>
            <div class="text-sm font-medium text-gray-600 mb-1">Email</div>
            <div class="text-base text-gray-900">{{ tenant.email || '-' }}</div>
          </div>
          <div>
            <div class="text-sm font-medium text-gray-600 mb-1">電話</div>
            <div class="text-base text-gray-900">{{ tenant.phone || '-' }}</div>
          </div>
          <div>
            <div class="text-sm font-medium text-gray-600 mb-1">租戶 ID</div>
            <div class="text-base text-gray-900 font-mono">{{ tenant.id }}</div>
          </div>
          <div>
            <div class="text-sm font-medium text-gray-600 mb-1">當前方案</div>
            <div class="text-base text-gray-900">{{ subscription.plan || '-' }}</div>
          </div>
        </div>
      </div>

      <!-- 訂閱狀態 -->
      <div v-if="subscription" class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">訂閱狀態</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div v-if="subscription.trial_ends_at" class="flex items-start space-x-3">
            <div class="flex-shrink-0 mt-1">
              <svg class="h-5 w-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
            <div>
              <div class="text-sm font-medium text-gray-900">試用期結束</div>
              <div class="text-sm text-gray-500 mt-0.5">{{ formatDate(subscription.trial_ends_at) }}</div>
            </div>
          </div>
          <div v-if="subscription.subscription_ends_at" class="flex items-start space-x-3">
            <div class="flex-shrink-0 mt-1">
              <svg class="h-5 w-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
              </svg>
            </div>
            <div>
              <div class="text-sm font-medium text-gray-900">訂閱到期日</div>
              <div class="text-sm text-gray-500 mt-0.5">{{ formatDate(subscription.subscription_ends_at) }}</div>
            </div>
          </div>
        </div>
      </div>

      <!-- 使用統計 -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">本月使用統計</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
          <!-- 預約數量 -->
          <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-lg p-4">
            <div class="flex items-center justify-between">
              <div>
                <div class="text-sm font-medium text-blue-700">預約數量</div>
                <div class="text-2xl font-bold text-blue-900 mt-1">{{ usage.reservations }}</div>
              </div>
              <div class="bg-blue-200 rounded-full p-3">
                <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
              </div>
            </div>
          </div>

          <!-- 客戶數量 -->
          <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-lg p-4">
            <div class="flex items-center justify-between">
              <div>
                <div class="text-sm font-medium text-green-700">客戶數量</div>
                <div class="text-2xl font-bold text-green-900 mt-1">{{ usage.customers }}</div>
              </div>
              <div class="bg-green-200 rounded-full p-3">
                <svg class="h-6 w-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
              </div>
            </div>
          </div>

          <!-- 服務項目 -->
          <div class="bg-gradient-to-br from-purple-50 to-purple-100 rounded-lg p-4">
            <div class="flex items-center justify-between">
              <div>
                <div class="text-sm font-medium text-purple-700">服務項目</div>
                <div class="text-2xl font-bold text-purple-900 mt-1">{{ usage.services }}</div>
              </div>
              <div class="bg-purple-200 rounded-full p-3">
                <svg class="h-6 w-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                </svg>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { getSubscription, getSubscriptionUsage } from '../utils/api.js'

// 狀態管理
const loading = ref(true)
const error = ref(null)

// 訂閱信息
const tenant = ref(null)
const subscription = ref(null)
const usage = ref({
  reservations: 0,
  customers: 0,
  services: 0
})

// 格式化日期
const formatDate = (dateStr) => {
  if (!dateStr) return '-'
  const date = new Date(dateStr)
  return date.toLocaleDateString('zh-TW', { 
    year: 'numeric', 
    month: 'long', 
    day: 'numeric' 
  })
}

// 獲取狀態標籤
const statusLabels = {
  active: '使用中',
  trial: '試用中',
  inactive: '已停用',
  suspended: '已暫停'
}

const statusColors = {
  active: 'bg-green-100 text-green-800',
  trial: 'bg-blue-100 text-blue-800',
  inactive: 'bg-gray-100 text-gray-800',
  suspended: 'bg-red-100 text-red-800'
}

// 獲取訂閱數據
async function fetchSubscriptionData() {
  try {
    loading.value = true
    error.value = null
    
    // 獲取訂閱信息
    const subResponse = await getSubscription()
    if (subResponse.success) {
      tenant.value = subResponse.data.tenant
      subscription.value = subResponse.data.subscription
    }
    
    // 獲取使用量
    const usageResponse = await getSubscriptionUsage()
    if (usageResponse.success) {
      usage.value = usageResponse.data.usage
    }
    
  } catch (err) {
    error.value = err.message || '獲取訂閱信息失敗'
    console.error('獲取訂閱信息錯誤:', err)
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  fetchSubscriptionData()
})
</script>

<style scoped>
/* 可選的額外樣式 */
</style>
