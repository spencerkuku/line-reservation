<template>
  <div class="min-h-screen bg-gray-50 pt-4 px-4 sm:px-6 lg:px-8 pb-6">
    <!-- 非管理員提示 -->
    <div v-if="!isAdmin && !isSystemAdmin" class="bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-xl p-6 mb-8 mt-2">
      <div class="flex items-center">
        <div class="flex-shrink-0">
          <svg class="h-8 w-8 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>
        <div class="ml-4">
          <h3 class="text-lg font-semibold text-blue-900">歡迎使用 LINE 預約管理系統</h3>
          <p class="text-blue-700 mt-1">您目前是一般用戶身份。如需使用完整管理功能，請聯絡系統管理員。</p>
        </div>
      </div>
    </div>

    <!-- 系統管理員儀表板 -->
    <div v-if="isSystemAdmin" class="space-y-6">
      <!-- 加載狀態 -->
      <div v-if="systemLoading" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        <div v-for="i in 4" :key="i" class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 animate-pulse">
          <div class="flex items-center justify-between">
            <div class="flex-1">
              <div class="h-4 bg-gray-200 rounded w-2/3 mb-3"></div>
              <div class="h-8 bg-gray-200 rounded w-1/2 mb-2"></div>
              <div class="h-3 bg-gray-200 rounded w-1/3"></div>
            </div>
            <div class="w-12 h-12 bg-gray-200 rounded-lg"></div>
          </div>
        </div>
      </div>

      <!-- 系統統計卡片 -->
      <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        <!-- 活躍租戶數 -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:shadow-md transition-all duration-200 group">
          <div class="flex items-center justify-between">
            <div class="flex-1">
              <div class="flex items-center">
                <p class="text-sm font-medium text-gray-600">活躍租戶</p>
                <div class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                  <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                  </svg>
                  在線
                </div>
              </div>
              <p class="text-3xl font-bold text-gray-900 mt-2">{{ systemStats.activeTenants || 0 }}</p>
              <p class="text-sm text-gray-500 mt-1 flex items-center">
                <svg class="w-4 h-4 mr-1 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                </svg>
                +{{ systemStats.newTenantsThisMonth || 0 }} 本月新增
              </p>
            </div>
            <div class="flex-shrink-0">
              <div class="w-12 h-12 bg-blue-50 rounded-lg flex items-center justify-center group-hover:bg-blue-100 transition-colors">
                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                </svg>
              </div>
            </div>
          </div>
        </div>

        <!-- 總用戶數 -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:shadow-md transition-all duration-200 group">
          <div class="flex items-center justify-between">
            <div class="flex-1">
              <p class="text-sm font-medium text-gray-600">總用戶數</p>
              <p class="text-3xl font-bold text-gray-900 mt-2">{{ formatNumber(systemStats.totalUsers || 0) }}</p>
              <p class="text-sm text-gray-500 mt-1 flex items-center">
                <svg class="w-4 h-4 mr-1 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                </svg>
                +{{ systemStats.newUsersThisWeek || 0 }} 本週新增
              </p>
            </div>
            <div class="flex-shrink-0">
              <div class="w-12 h-12 bg-green-50 rounded-lg flex items-center justify-center group-hover:bg-green-100 transition-colors">
                <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                </svg>
              </div>
            </div>
          </div>
        </div>

        <!-- 系統預約總數 -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:shadow-md transition-all duration-200 group">
          <div class="flex items-center justify-between">
            <div class="flex-1">
              <p class="text-sm font-medium text-gray-600">總預約數</p>
              <p class="text-3xl font-bold text-gray-900 mt-2">{{ formatNumber(systemStats.totalReservations || 0) }}</p>
              <p class="text-sm text-gray-500 mt-1 flex items-center">
                <svg class="w-4 h-4 mr-1 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                </svg>
                +{{ systemStats.todayReservations || 0 }} 今日新增
              </p>
            </div>
            <div class="flex-shrink-0">
              <div class="w-12 h-12 bg-purple-50 rounded-lg flex items-center justify-center group-hover:bg-purple-100 transition-colors">
                <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
              </div>
            </div>
          </div>
        </div>

        <!-- 系統運行時間 -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:shadow-md transition-all duration-200 group">
          <div class="flex items-center justify-between">
            <div class="flex-1">
              <div class="flex items-center">
                <p class="text-sm font-medium text-gray-600">系統運行時間</p>
                <div class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                  <div class="w-2 h-2 bg-green-400 rounded-full mr-1 animate-pulse"></div>
                  運行中
                </div>
              </div>
              <p class="text-2xl font-bold text-gray-900 mt-2">{{ formatUptime(systemStats.uptime) }}</p>
              <p class="text-sm text-gray-500 mt-1 flex items-center">
                <svg class="w-4 h-4 mr-1 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                {{ getUptimeDetail(systemStats.uptime) }}
              </p>
            </div>
            <div class="flex-shrink-0">
              <div class="w-12 h-12 bg-emerald-50 rounded-lg flex items-center justify-center group-hover:bg-emerald-100 transition-colors">
                <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- 主要功能區塊 -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- 租戶管理快速操作 -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200">
          <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex items-center justify-between">
              <div>
                <h3 class="text-lg font-semibold text-gray-900">租戶管理</h3>
                <p class="text-sm text-gray-600 mt-1">快速管理租戶和用戶</p>
              </div>
              <RouterLink 
                :to="{ name: 'Tenants' }"
                class="inline-flex items-center px-3 py-1.5 border border-gray-300 shadow-sm text-xs font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors"
              >
                查看全部
                <svg class="ml-1 -mr-0.5 w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
              </RouterLink>
            </div>
          </div>
          <div class="p-6 space-y-3">
            <RouterLink 
              :to="{ name: 'Tenants' }"
              class="flex items-center p-4 border border-gray-200 rounded-lg hover:border-blue-300 hover:bg-blue-50 transition-all duration-200 group"
            >
              <div class="flex items-center flex-1">
                <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center group-hover:bg-blue-200 transition-colors">
                  <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                  </svg>
                </div>
                <div class="ml-4 flex-1">
                  <p class="text-sm font-semibold text-gray-900 group-hover:text-blue-900">管理所有租戶</p>
                  <p class="text-xs text-gray-500 mt-0.5">查看、新增、編輯租戶資訊</p>
                </div>
              </div>
              <svg class="h-4 w-4 text-gray-400 group-hover:text-blue-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
              </svg>
            </RouterLink>

            <button class="w-full flex items-center p-4 border border-gray-200 rounded-lg hover:border-green-300 hover:bg-green-50 transition-all duration-200 group">
              <div class="flex items-center flex-1">
                <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center group-hover:bg-green-200 transition-colors">
                  <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                  </svg>
                </div>
                <div class="ml-4 flex-1 text-left">
                  <p class="text-sm font-semibold text-gray-900 group-hover:text-green-900">新增租戶</p>
                  <p class="text-xs text-gray-500 mt-0.5">快速建立新的租戶帳號</p>
                </div>
              </div>
              <svg class="h-4 w-4 text-gray-400 group-hover:text-green-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
              </svg>
            </button>

            <div class="pt-3 border-t border-gray-100">
              <div class="grid grid-cols-2 gap-4">
                <div class="text-center">
                  <p class="text-2xl font-bold text-gray-900">{{ systemStats.activeTenants || 0 }}</p>
                  <p class="text-xs text-gray-500 mt-1">活躍租戶</p>
                </div>
                <div class="text-center">
                  <p class="text-2xl font-bold text-gray-900">{{ formatNumber(systemStats.totalUsers || 0) }}</p>
                  <p class="text-xs text-gray-500 mt-1">總用戶數</p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- 系統監控 -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200">
          <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex items-center justify-between">
              <div>
                <h3 class="text-lg font-semibold text-gray-900">系統監控</h3>
                <p class="text-sm text-gray-600 mt-1">即時監控系統運行狀態</p>
              </div>
              <div class="flex items-center space-x-1">
                <div class="w-2 h-2 bg-green-400 rounded-full animate-pulse"></div>
                <span class="text-xs font-medium text-green-600">即時更新</span>
              </div>
            </div>
          </div>
          <div class="p-6 space-y-6">
            <!-- 系統負載 -->
            <div class="space-y-2">
              <div class="flex items-center justify-between">
                <div class="flex items-center">
                  <svg class="w-4 h-4 text-gray-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 00-2-2z"/>
                  </svg>
                  <span class="text-sm font-medium text-gray-700">CPU 使用率</span>
                </div>
                <div class="flex items-center space-x-3">
                  <div class="w-32 bg-gray-200 rounded-full h-2.5">
                    <div 
                      :class="getCpuLoadProgressClass(systemStats.systemLoad?.cpu || 0)"
                      class="h-2.5 rounded-full transition-all duration-300" 
                      :style="{ width: (systemStats.systemLoad?.cpu || 0) + '%' }"
                    ></div>
                  </div>
                  <span class="text-sm font-semibold text-gray-900 w-10 text-right">{{ (systemStats.systemLoad?.cpu || 0).toFixed(1) }}%</span>
                </div>
              </div>
              <p class="text-xs text-gray-500 ml-6">{{ getCpuStatusText(systemStats.systemLoad?.cpu || 0) }}</p>
            </div>
            
            <!-- 記憶體使用量 -->
            <div class="space-y-2">
              <div class="flex items-center justify-between">
                <div class="flex items-center">
                  <svg class="w-4 h-4 text-gray-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"/>
                  </svg>
                  <span class="text-sm font-medium text-gray-700">記憶體使用</span>
                </div>
                <div class="flex items-center space-x-3">
                  <div class="w-32 bg-gray-200 rounded-full h-2.5">
                    <div 
                      :class="getMemoryProgressClass(systemStats.systemLoad?.memory || 0)"
                      class="h-2.5 rounded-full transition-all duration-300" 
                      :style="{ width: (systemStats.systemLoad?.memory || 0) + '%' }"
                    ></div>
                  </div>
                  <span class="text-sm font-semibold text-gray-900 w-10 text-right">{{ (systemStats.systemLoad?.memory || 0).toFixed(1) }}%</span>
                </div>
              </div>
              <p class="text-xs text-gray-500 ml-6">{{ getMemoryUsageText() }}</p>
            </div>

            <!-- 資料庫連接 -->
            <div class="space-y-2">
              <div class="flex items-center justify-between">
                <div class="flex items-center">
                  <svg class="w-4 h-4 text-gray-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"/>
                  </svg>
                  <span class="text-sm font-medium text-gray-700">資料庫狀態</span>
                </div>
                <div class="flex items-center space-x-2">
                  <div :class="getDatabaseStatusClass(systemStats.database?.status)" class="flex items-center px-2 py-1 rounded-full">
                    <div :class="getDatabaseDotClass(systemStats.database?.status)" class="w-2 h-2 rounded-full mr-1.5"></div>
                    <span :class="getDatabaseTextClass(systemStats.database?.status)" class="text-xs font-medium">{{ getDatabaseStatusText(systemStats.database?.status) }}</span>
                  </div>
                </div>
              </div>
              <p class="text-xs text-gray-500 ml-6">
                連接池: {{ systemStats.database?.connections?.active || 0 }}/{{ systemStats.database?.connections?.max || 0 }} 活躍連接
              </p>
            </div>

            <!-- 儲存空間 -->
            <div class="space-y-2">
              <div class="flex items-center justify-between">
                <div class="flex items-center">
                  <svg class="w-4 h-4 text-gray-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 4V2a1 1 0 011-1h8a1 1 0 011 1v2m-9 4v10a2 2 0 002 2h6a2 2 0 002-2V8M7 8H5a2 2 0 00-2 2v8a2 2 0 002 2h2m9-12V6a2 2 0 00-2-2H9a2 2 0 00-2 2v2"/>
                  </svg>
                  <span class="text-sm font-medium text-gray-700">儲存空間</span>
                </div>
                <div class="flex items-center space-x-3">
                  <div class="w-32 bg-gray-200 rounded-full h-2.5">
                    <div 
                      :class="getStorageProgressClass(systemStats.storage?.percentage || 0)"
                      class="h-2.5 rounded-full transition-all duration-300" 
                      :style="{ width: (systemStats.storage?.percentage || 0) + '%' }"
                    ></div>
                  </div>
                  <span class="text-sm font-semibold text-gray-900 w-10 text-right">{{ (systemStats.storage?.percentage || 0).toFixed(1) }}%</span>
                </div>
              </div>
              <p class="text-xs text-gray-500 ml-6">
                已使用 {{ formatBytes(systemStats.storage?.used || 0) }} / {{ formatBytes(systemStats.storage?.total || 0) }} 總容量
              </p>
            </div>

            <!-- API 響應時間 -->
            <div class="space-y-2">
              <div class="flex items-center justify-between">
                <div class="flex items-center">
                  <svg class="w-4 h-4 text-gray-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                  </svg>
                  <span class="text-sm font-medium text-gray-700">API 響應時間</span>
                </div>
                <div class="flex items-center space-x-2">
                  <span class="text-sm font-semibold text-gray-900">{{ systemStats.performance?.apiResponseTime || 0 }}ms</span>
                  <div :class="getApiResponseClass(systemStats.performance?.apiResponseTime || 0)" class="flex items-center px-2 py-1 rounded-full">
                    <span class="text-xs font-medium">{{ getApiResponseText(systemStats.performance?.apiResponseTime || 0) }}</span>
                  </div>
                </div>
              </div>
              <p class="text-xs text-gray-500 ml-6">平均響應時間，過去24小時</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Phase 1: 系統警報、效能圖表、租戶活動 -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- 系統警報中心 -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200">
          <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex items-center justify-between">
              <div>
                <h3 class="text-lg font-semibold text-gray-900">系統警報</h3>
                <p class="text-sm text-gray-600 mt-1">即時系統警告</p>
              </div>
              <div v-if="systemAlerts.total > 0" class="flex items-center space-x-2">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800" v-if="systemAlerts.error_count > 0">
                  {{ systemAlerts.error_count }} 錯誤
                </span>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800" v-if="systemAlerts.warning_count > 0">
                  {{ systemAlerts.warning_count }} 警告
                </span>
              </div>
              <span v-else class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                正常
              </span>
            </div>
          </div>
          <div class="p-6">
            <div v-if="systemAlerts.total === 0" class="text-center py-8">
              <svg class="mx-auto h-12 w-12 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
              </svg>
              <p class="mt-2 text-sm text-gray-500">系統運行正常，無警報</p>
            </div>
            <div v-else class="space-y-3 max-h-80 overflow-y-auto">
              <div v-for="alert in systemAlerts.alerts" :key="alert.timestamp" 
                   :class="[
                     'p-3 rounded-lg border-l-4',
                     alert.type === 'error' ? 'bg-red-50 border-red-400' : 'bg-yellow-50 border-yellow-400'
                   ]">
                <div class="flex items-start">
                  <svg v-if="alert.type === 'error'" class="h-5 w-5 text-red-400 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                  </svg>
                  <svg v-else class="h-5 w-5 text-yellow-400 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                  </svg>
                  <div class="ml-3 flex-1">
                    <p :class="['text-sm font-medium', alert.type === 'error' ? 'text-red-800' : 'text-yellow-800']">
                      {{ alert.title }}
                    </p>
                    <p :class="['text-xs mt-1', alert.type === 'error' ? 'text-red-700' : 'text-yellow-700']">
                      {{ alert.message }}
                    </p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- 效能趨勢圖表 -->
        <div class="lg:col-span-2 bg-white rounded-lg shadow-sm border border-gray-200">
          <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex items-center justify-between">
              <div>
                <h3 class="text-lg font-semibold text-gray-900">API 效能趨勢</h3>
                <p class="text-sm text-gray-600 mt-1">過去 24 小時</p>
              </div>
              <div class="flex items-center space-x-4 text-xs">
                <div class="flex items-center">
                  <div class="w-3 h-3 bg-blue-500 rounded mr-1"></div>
                  <span class="text-gray-600">請求數</span>
                </div>
                <div class="flex items-center">
                  <div class="w-3 h-3 bg-green-500 rounded mr-1"></div>
                  <span class="text-gray-600">響應時間</span>
                </div>
              </div>
            </div>
          </div>
          <div class="p-6">
            <div v-if="performanceHistory.length === 0" class="text-center py-12">
              <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 00-2-2z"/>
              </svg>
              <p class="mt-2 text-sm text-gray-500">暫無效能數據</p>
            </div>
            <div v-else class="space-y-4">
              <!-- 簡易圖表 -->
              <div class="relative h-48">
                <div class="absolute inset-0 flex items-end justify-between space-x-1">
                  <div v-for="(point, index) in performanceHistory.slice(-12)" :key="index" 
                       class="flex-1 flex flex-col items-center space-y-1">
                    <!-- 請求數柱狀圖 -->
                    <div class="w-full bg-blue-100 rounded-t relative group cursor-pointer"
                         :style="{ height: getBarHeight(point.requests, 'requests') + '%' }">
                      <div class="absolute -top-8 left-1/2 transform -translate-x-1/2 hidden group-hover:block bg-gray-900 text-white text-xs rounded py-1 px-2 whitespace-nowrap">
                        {{ point.requests }} 請求
                      </div>
                    </div>
                    <!-- 時間標籤 -->
                    <span class="text-xs text-gray-500 transform rotate-45 origin-top-left">{{ point.time }}</span>
                  </div>
                </div>
              </div>
              
              <!-- 統計摘要 -->
              <div class="grid grid-cols-3 gap-4 pt-4 border-t">
                <div class="text-center">
                  <p class="text-sm text-gray-600">總請求數</p>
                  <p class="text-2xl font-bold text-gray-900 mt-1">
                    {{ formatNumber(performanceHistory.reduce((sum, p) => sum + p.requests, 0)) }}
                  </p>
                </div>
                <div class="text-center">
                  <p class="text-sm text-gray-600">平均響應時間</p>
                  <p class="text-2xl font-bold text-gray-900 mt-1">
                    {{ getAvgResponseTime() }}ms
                  </p>
                </div>
                <div class="text-center">
                  <p class="text-sm text-gray-600">峰值請求</p>
                  <p class="text-2xl font-bold text-gray-900 mt-1">
                    {{ Math.max(...performanceHistory.map(p => p.requests), 0) }}
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- 租戶活動監控 -->
      <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <div class="px-6 py-4 border-b border-gray-200">
          <div class="flex items-center justify-between">
            <div>
              <h3 class="text-lg font-semibold text-gray-900">租戶活動排行</h3>
              <p class="text-sm text-gray-600 mt-1">過去 7 天最活躍的租戶</p>
            </div>
            <RouterLink 
              :to="{ name: 'Tenants' }"
              class="text-sm text-blue-600 hover:text-blue-800 font-medium"
            >
              查看全部 →
            </RouterLink>
          </div>
        </div>
        <div class="p-6">
          <div v-if="tenantActivities.length === 0" class="text-center py-8">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
            </svg>
            <p class="mt-2 text-sm text-gray-500">暫無租戶活動數據</p>
          </div>
          <div v-else class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
              <thead>
                <tr>
                  <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">排名</th>
                  <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">租戶名稱</th>
                  <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">狀態</th>
                  <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">用戶數</th>
                  <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">本週預約</th>
                  <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">今日預約</th>
                  <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">活動分數</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <tr v-for="(tenant, index) in tenantActivities" :key="tenant.id" class="hover:bg-gray-50">
                  <td class="px-4 py-4 whitespace-nowrap">
                    <div class="flex items-center">
                      <span v-if="index === 0" class="text-2xl">🥇</span>
                      <span v-else-if="index === 1" class="text-2xl">🥈</span>
                      <span v-else-if="index === 2" class="text-2xl">🥉</span>
                      <span v-else class="text-sm font-medium text-gray-900">{{ index + 1 }}</span>
                    </div>
                  </td>
                  <td class="px-4 py-4 whitespace-nowrap">
                    <div class="text-sm font-medium text-gray-900">{{ tenant.name }}</div>
                  </td>
                  <td class="px-4 py-4 whitespace-nowrap">
                    <span :class="[
                      'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium',
                      tenant.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'
                    ]">
                      {{ tenant.status === 'active' ? '活躍' : '暫停' }}
                    </span>
                  </td>
                  <td class="px-4 py-4 whitespace-nowrap text-right text-sm text-gray-900">
                    {{ tenant.users_count }}
                  </td>
                  <td class="px-4 py-4 whitespace-nowrap text-right">
                    <span class="text-sm font-semibold text-blue-600">{{ tenant.week_reservations }}</span>
                  </td>
                  <td class="px-4 py-4 whitespace-nowrap text-right">
                    <span class="text-sm text-gray-900">{{ tenant.today_reservations }}</span>
                  </td>
                  <td class="px-4 py-4 whitespace-nowrap text-right">
                    <div class="flex items-center justify-end space-x-2">
                      <div class="w-16 bg-gray-200 rounded-full h-2">
                        <div class="bg-blue-600 h-2 rounded-full" :style="{ width: getActivityPercentage(tenant.activity_score) + '%' }"></div>
                      </div>
                      <span class="text-sm font-medium text-gray-900">{{ tenant.activity_score }}</span>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

    </div>

    <!-- 租戶管理員儀表板 -->
    <div v-if="isAdmin" class="space-y-8">
      <!-- 錯誤提示 -->
      <div v-if="error" class="bg-red-50 border border-red-200 rounded-xl p-4 mb-6">
        <div class="flex items-center">
          <svg class="h-5 w-5 text-red-500 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <p class="text-sm font-medium text-red-800">{{ error }}</p>
          <button @click="fetchDashboardData" class="ml-auto text-sm text-red-600 hover:text-red-800 font-medium">
            重試
          </button>
        </div>
      </div>

      <!-- 加載骨架屏 -->
      <div v-if="loading && !stats.total_reservations" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        <div v-for="i in 4" :key="i" class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 animate-pulse">
          <div class="h-4 bg-gray-200 rounded w-1/2 mb-4"></div>
          <div class="h-8 bg-gray-200 rounded w-3/4 mb-2"></div>
          <div class="h-3 bg-gray-200 rounded w-1/3"></div>
        </div>
      </div>

      <!-- 關鍵指標卡片 -->
      <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
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
                    <div class="text-sm font-medium text-gray-900">
                      {{ reservation.customer?.line_display_name || reservation.customer?.name || reservation.customer_name || '未知客戶' }}
                      <span v-if="reservation.customer_name && reservation.customer_name !== (reservation.customer?.line_display_name || reservation.customer?.name)" 
                            class="ml-2 text-xs text-gray-500">({{ reservation.customer_name }})</span>
                    </div>
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

// 系統管理員專用數據
const systemStats = ref({})
const systemLoading = ref(false)
const systemError = ref('')

// Phase 1: 新增數據
const systemAlerts = ref({ alerts: [], total: 0, error_count: 0, warning_count: 0 })
const performanceHistory = ref([])
const tenantActivities = ref([])

// 獲取當前用戶信息
const currentUser = computed(() => {
  const userStr = localStorage.getItem('user')
  return userStr ? JSON.parse(userStr) : null
})

// 檢查是否為管理員
const isAdmin = computed(() => {
  return currentUser.value?.role === 'admin'
})

// 檢查是否為系統管理員
const isSystemAdmin = computed(() => {
  return currentUser.value?.role === 'system_admin'
})

// 格式化數字顯示
const formatNumber = (num) => {
  return new Intl.NumberFormat('zh-TW').format(num)
}





// 格式化貨幣
const formatCurrency = (amount) => {
  return new Intl.NumberFormat('zh-TW').format(amount)
}

// 系統監控輔助函數
const formatBytes = (bytes) => {
  if (bytes === 0) return '0 Bytes'
  const k = 1024
  const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i]
}

const getCpuLoadProgressClass = (percentage) => {
  if (percentage <= 50) return 'bg-green-500'
  if (percentage <= 80) return 'bg-yellow-500'
  return 'bg-red-500'
}

const getDatabaseStatusClass = (status) => {
  switch (status?.toLowerCase()) {
    case 'connected':
    case 'online':
    case 'healthy':
      return 'bg-green-100 text-green-700'
    case 'warning':
    case 'slow':
      return 'bg-yellow-100 text-yellow-700'
    case 'error':
    case 'offline':
    case 'disconnected':
      return 'bg-red-100 text-red-700'
    default:
      return 'bg-gray-100 text-gray-700'
  }
}

const getDatabaseStatusText = (status) => {
  switch (status?.toLowerCase()) {
    case 'connected':
    case 'online':
    case 'healthy':
      return '正常'
    case 'warning':
    case 'slow':
      return '警告'
    case 'error':
    case 'offline':
    case 'disconnected':
      return '錯誤'
    default:
      return '未知'
  }
}

const getStorageProgressClass = (percentage) => {
  if (percentage <= 60) return 'bg-green-500'
  if (percentage <= 85) return 'bg-yellow-500'
  return 'bg-red-500'
}

const getMemoryProgressClass = (percentage) => {
  if (percentage <= 60) return 'bg-green-500'
  if (percentage <= 85) return 'bg-yellow-500'
  return 'bg-red-500'
}

const getApiResponseClass = (responseTime) => {
  if (responseTime <= 200) return 'bg-green-100 text-green-700'
  if (responseTime <= 500) return 'bg-yellow-100 text-yellow-700'
  return 'bg-red-100 text-red-700'
}

const getApiResponseText = (responseTime) => {
  if (responseTime <= 200) return '良好'
  if (responseTime <= 500) return '一般'
  return '緩慢'
}

const getMemoryUsageText = () => {
  const memoryPercentage = systemStats.value?.systemLoad?.memory || 0
  if (memoryPercentage <= 60) {
    return '系統記憶體使用正常'
  } else if (memoryPercentage <= 85) {
    return '記憶體使用率偏高，建議關注'
  } else {
    return '記憶體使用率過高，需要處理'
  }
}

const getCpuStatusText = (percentage) => {
  if (percentage <= 50) {
    return 'CPU 使用率正常'
  } else if (percentage <= 80) {
    return 'CPU 使用率偏高，建議關注'
  } else {
    return 'CPU 使用率過高，需要處理'
  }
}

// 格式化運行時間
const formatUptime = (uptimeData) => {
  if (!uptimeData) return 'N/A'
  
  // 如果是字串格式，直接返回
  if (typeof uptimeData === 'string') return uptimeData
  
  // 如果有 formatted 欄位，直接使用
  if (uptimeData.formatted) return uptimeData.formatted
  
  // 如果有 days 欄位，格式化顯示
  if (uptimeData.days !== undefined) {
    const parts = []
    if (uptimeData.days > 0) parts.push(`${uptimeData.days} 天`)
    if (uptimeData.hours > 0) parts.push(`${uptimeData.hours} 小時`)
    if (uptimeData.minutes > 0 || parts.length === 0) parts.push(`${uptimeData.minutes} 分鐘`)
    return parts.join(' ')
  }
  
  return 'N/A'
}

// 獲取運行時間詳細資訊
const getUptimeDetail = (uptimeData) => {
  if (!uptimeData) return '系統運行中'
  
  if (uptimeData.days !== undefined) {
    if (uptimeData.days >= 30) {
      return '長期穩定運行'
    } else if (uptimeData.days >= 7) {
      return '運行穩定'
    } else if (uptimeData.days >= 1) {
      return '系統運行中'
    } else {
      return '最近啟動'
    }
  }
  
  return '系統運行中'
}

// Phase 1: 新增輔助函數
// 計算柱狀圖高度
const getBarHeight = (value, type) => {
  if (type === 'requests') {
    const maxRequests = Math.max(...performanceHistory.value.map(p => p.requests), 1)
    return (value / maxRequests) * 100
  }
  return 0
}

// 計算平均響應時間
const getAvgResponseTime = () => {
  if (performanceHistory.value.length === 0) return 0
  const total = performanceHistory.value.reduce((sum, p) => sum + p.avgResponseTime, 0)
  return Math.round(total / performanceHistory.value.length)
}

// 計算活動分數百分比
const getActivityPercentage = (score) => {
  if (tenantActivities.value.length === 0) return 0
  const maxScore = Math.max(...tenantActivities.value.map(t => t.activity_score), 1)
  return Math.min((score / maxScore) * 100, 100)
}

const getDatabaseDotClass = (status) => {
  switch (status?.toLowerCase()) {
    case 'connected':
    case 'online':
    case 'healthy':
      return 'bg-green-500'
    case 'warning':
    case 'slow':
      return 'bg-yellow-500'
    case 'error':
    case 'offline':
    case 'disconnected':
      return 'bg-red-500'
    default:
      return 'bg-gray-500'
  }
}

const getDatabaseTextClass = (status) => {
  switch (status?.toLowerCase()) {
    case 'connected':
    case 'online':
    case 'healthy':
      return 'text-green-700'
    case 'warning':
    case 'slow':
      return 'text-yellow-700'
    case 'error':
    case 'offline':
    case 'disconnected':
      return 'text-red-700'
    default:
      return 'text-gray-700'
  }
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
    case 'completed':
      return 'bg-blue-100 text-blue-800'
    case 'pending':
      return 'bg-yellow-100 text-yellow-800'
    case 'cancelled':
      return 'bg-red-100 text-red-800'
    case 'no_show':
      return 'bg-gray-100 text-gray-800'
    default:
      return 'bg-gray-100 text-gray-800'
  }
}

// 獲取狀態文字
const getStatusText = (status) => {
  switch (status) {
    case 'confirmed':
      return '已確認'
    case 'completed':
      return '已完成'
    case 'pending':
      return '待確認'
    case 'cancelled':
      return '已取消'
    case 'no_show':
      return '爽約'
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
  if (!isAdmin.value && !isSystemAdmin.value) return
  
  loading.value = true
  error.value = ''
  
  try {
    // 系統管理員使用不同的 API 端點
    if (isSystemAdmin.value) {
      systemLoading.value = true
      systemError.value = ''
      
      try {
        // 獲取系統級統計數據
        const systemStatsRes = await apiGet('/system/stats')
        
        if (systemStatsRes?.success) {
          systemStats.value = systemStatsRes.data || {}
        }
        
        // 獲取系統監控數據
        try {
          const monitoringRes = await apiGet('/system/monitoring')
          if (monitoringRes?.success) {
            systemStats.value = {
              ...systemStats.value,
              ...monitoringRes.data
            }
          }
        } catch (err) {
          console.warn('獲取系統監控數據失敗:', err.message)
        }

        // Phase 1: 獲取系統警報
        try {
          const alertsRes = await apiGet('/system/alerts')
          if (alertsRes?.success) {
            systemAlerts.value = alertsRes.data
          }
        } catch (err) {
          console.warn('獲取系統警報失敗:', err.message)
        }

        // Phase 1: 獲取效能歷史
        try {
          const perfRes = await apiGet('/system/performance-history')
          if (perfRes?.success) {
            performanceHistory.value = perfRes.data.history || []
          }
        } catch (err) {
          console.warn('獲取效能歷史失敗:', err.message)
        }

        // Phase 1: 獲取租戶活動
        try {
          const activityRes = await apiGet('/system/tenant-activity')
          if (activityRes?.success) {
            tenantActivities.value = activityRes.data.tenants || []
          }
        } catch (err) {
          console.warn('獲取租戶活動失敗:', err.message)
        }
        

      } catch (err) {
        systemError.value = err.message || '獲取系統數據失敗'
        console.error('系統數據獲取錯誤:', err)
      } finally {
        systemLoading.value = false
      }
      return
    }

    // 租戶管理員並行獲取所有數據
    const [statsRes, reservationsRes, servicesRes, noticesRes] = await Promise.all([
      apiGet('/dashboard/stats'),
      apiGet('/dashboard/reservations'),
      apiGet('/dashboard/popular-services'),
      apiGet('/dashboard/notices')
    ])

    if (statsRes?.success) {
      stats.value = statsRes.data || {}
    } else {
      throw new Error(statsRes?.message || '獲取統計資料失敗')
    }

    if (reservationsRes?.success) {
      recentReservations.value = (reservationsRes.data || []).slice(0, 10)
      
      // 生成圖表數據（最近7天的預約趨勢）
      // 使用本地計算以確保完整的 7 天數據
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
    } else {
      console.warn('獲取預約資料失敗:', reservationsRes?.message)
      // 不拋出錯誤，使用空數據
      recentReservations.value = []
      chartData.value = []
    }

    if (servicesRes?.success) {
      topServicesData.value = (servicesRes.data || []).slice(0, 5)
    } else {
      console.warn('獲取熱門服務失敗:', servicesRes?.message)
      topServicesData.value = []
    }

    if (noticesRes?.success) {
      notices.value = (noticesRes.data || []).slice(0, 5)
    } else {
      console.warn('獲取通知失敗:', noticesRes?.message)
      notices.value = []
    }

  } catch (err) {
    error.value = err.message || '獲取儀表板數據失敗'
    console.error('獲取儀表板數據失敗:', err)
    
    // 顯示錯誤提示（可選：使用 toast 通知）
    if (import.meta.env.DEV) {
      console.error('Dashboard error details:', {
        message: err.message,
        stack: err.stack
      })
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
    if ((isAdmin.value || isSystemAdmin.value) && !loading.value) {
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
