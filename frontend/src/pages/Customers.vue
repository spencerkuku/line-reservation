<template>
  <div class="bg-gray-50 p-6">
    <!-- 頁面標題 -->
    <div class="mb-8">
      <h1 class="text-3xl font-bold text-gray-900">客戶管理</h1>
      <p class="text-gray-600 mt-2">管理您的客戶資料、查看客戶統計與互動記錄</p>
    </div>
    
    <!-- 統計儀表板 -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
      <!-- 總客戶數 -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-gray-600">總客戶數</p>
            <p class="text-3xl font-bold text-gray-900 mt-2">{{ statistics.total_customers || 0 }}</p>
            <p class="text-sm text-blue-600 mt-1">
              <span class="inline-flex items-center">
                <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                  <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                註冊會員
              </span>
            </p>
          </div>
          <div class="flex-shrink-0">
            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
              <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
              </svg>
            </div>
          </div>
        </div>
      </div>

      <!-- 活躍客戶 -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-gray-600">活躍客戶</p>
            <p class="text-3xl font-bold text-gray-900 mt-2">{{ statistics.active_customers || 0 }}</p>
            <p class="text-sm text-green-600 mt-1">
              <span class="inline-flex items-center">
                <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                </svg>
                近期活躍
              </span>
            </p>
          </div>
          <div class="flex-shrink-0">
            <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
              <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
          </div>
        </div>
      </div>

      <!-- VIP客戶 -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-gray-600">VIP 客戶</p>
            <p class="text-3xl font-bold text-gray-900 mt-2">{{ statistics.vip_customers || 0 }}</p>
            <p class="text-sm text-yellow-600 mt-1">
              <span class="inline-flex items-center">
                <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                  <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                </svg>
                高價值客戶
              </span>
            </p>
          </div>
          <div class="flex-shrink-0">
            <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
              <svg class="w-6 h-6 text-yellow-600" fill="currentColor" viewBox="0 0 20 20">
                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
              </svg>
            </div>
          </div>
        </div>
      </div>

      <!-- 本月新客戶 -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-gray-600">本月新客戶</p>
            <p class="text-3xl font-bold text-gray-900 mt-2">{{ statistics.new_customers_this_month || 0 }}</p>
            <p class="text-sm text-purple-600 mt-1">
              <span class="inline-flex items-center">
                <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"></path>
                </svg>
                持續成長
              </span>
            </p>
          </div>
          <div class="flex-shrink-0">
            <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
              <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
              </svg>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- 搜尋和篩選控制台 -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-8">
      <div class="mb-4">
        <h2 class="text-lg font-semibold text-gray-900">客戶搜尋與篩選</h2>
        <p class="text-sm text-gray-600">快速找到您需要的客戶資料</p>
      </div>
        <!-- 新增客戶按鈕已暫時停用 -->
        <!-- <button
          @click="showAddModal = true"
          class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-blue-600 to-blue-700 text-white text-sm font-medium rounded-lg hover:from-blue-700 hover:to-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-all duration-200 shadow-sm"
        >
          <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
          </svg>
          新增客戶
        </button> -->

      </div>
      
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="space-y-2">
          <label class="text-sm font-medium text-gray-700">搜尋客戶</label>
          <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
              <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
              </svg>
            </div>
            <input
              v-model="searchForm.search"
              type="text"
              placeholder="姓名、電話或 Email"
              class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
            />
          </div>
        </div>
        
        <div class="space-y-2">
          <label class="text-sm font-medium text-gray-700">客戶狀態</label>
          <select
            v-model="searchForm.status"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
          >
            <option value="">全部狀態</option>
            <option value="active">活躍</option>
            <option value="inactive">非活躍</option>
            <option value="blocked">已封鎖</option>
          </select>
        </div>
        
        <div class="space-y-2">
          <label class="text-sm font-medium text-gray-700">客戶等級</label>
          <select
            v-model="searchForm.level"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
          >
            <option value="">全部等級</option>
            <option value="VIP">VIP 會員</option>
            <option value="Gold">金牌會員</option>
            <option value="Silver">銀牌會員</option>
            <option value="Bronze">銅牌會員</option>
          </select>
        </div>
        
        <div class="space-y-2">
          <label class="text-sm font-medium text-gray-700 ">&nbsp;</label>
          <div class="w-full flex items-center justify-center px-4 py-2 bg-gradient-to-r from-gray-100 to-gray-200 text-gray-600 text-sm font-medium rounded-lg border border-gray-300">
            <svg class="w-4 h-4 mr-2 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
            </svg>
            即時搜尋
          </div>
        </div>
      </div>
    </div>

    <!-- 客戶列表 -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
      <!-- 表格標題 -->
      <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
        <div class="flex items-center justify-between">
          <div>
            <h3 class="text-lg font-semibold text-gray-900">客戶列表</h3>
            <p class="text-sm text-gray-600 mt-1">
              共 {{ pagination.total || 0 }} 位客戶，顯示第 {{ (pagination.current_page - 1) * pagination.per_page + 1 }} - {{ Math.min(pagination.current_page * pagination.per_page, pagination.total) }} 筆
            </p>
          </div>
        </div>
      </div>
      
      <!-- 載入狀態 -->
      <div v-if="loading" class="p-12 text-center">
        <div class="inline-flex items-center justify-center w-8 h-8">
          <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
        </div>
        <p class="mt-4 text-sm text-gray-500">載入客戶資料中...</p>
      </div>
      
      <!-- 錯誤狀態 -->
      <div v-else-if="error" class="p-12 text-center">
        <div class="w-12 h-12 mx-auto bg-red-100 rounded-full flex items-center justify-center mb-4">
          <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>
        <h3 class="text-lg font-medium text-gray-900 mb-2">載入失敗</h3>
        <p class="text-sm text-gray-500 mb-4">{{ error }}</p>
        <button
          @click="fetchCustomers"
          class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
        >
          重新載入
        </button>
      </div>
      
      <!-- 空狀態 -->
      <div v-else-if="customers.length === 0" class="p-12 text-center">
        <div class="w-12 h-12 mx-auto bg-gray-100 rounded-full flex items-center justify-center mb-4">
          <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
          </svg>
        </div>
        <h3 class="text-lg font-medium text-gray-900 mb-2">沒有找到客戶</h3>
        <p class="text-sm text-gray-500 mb-4">目前沒有符合條件的客戶資料</p>
        <!-- 新增客戶按鈕已暫時停用 -->
        <!-- <button
          @click="showAddModal = true"
          class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
        >
          <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
          </svg>
          新增第一位客戶
        </button> -->
      </div>
      
      <!-- 客戶表格 -->
      <div v-else class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                客戶資訊
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                聯絡方式
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                等級/狀態
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                預約統計
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                最後互動
              </th>
              <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                操作
              </th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr v-for="customer in customers" :key="customer.id" class="hover:bg-gray-50 transition-colors">
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex items-center">
                  <div class="flex-shrink-0 h-12 w-12">
                    <div v-if="customer.line_picture_url" class="h-12 w-12 rounded-full overflow-hidden border-2 border-gray-200">
                      <img 
                        :src="customer.line_picture_url" 
                        :alt="customer.line_display_name || customer.name"
                        class="h-full w-full object-cover"
                        @error="$event.target.style.display='none'; $event.target.nextElementSibling.style.display='flex'"
                      />
                      <div class="h-12 w-12 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center" style="display: none;">
                        <span class="text-sm font-semibold text-white">
                          {{ (customer.line_display_name || customer.name || '?').charAt(0).toUpperCase() }}
                        </span>
                      </div>
                    </div>
                    <div v-else class="h-12 w-12 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center">
                      <span class="text-sm font-semibold text-white">
                        {{ (customer.line_display_name || customer.name || '?').charAt(0).toUpperCase() }}
                      </span>
                    </div>
                  </div>
                  <div class="ml-4">
                    <div class="text-sm font-medium text-gray-900 flex items-center">
                      {{ customer.line_display_name || customer.name || '未知客戶' }}
                      <span v-if="customer.line_display_name && customer.line_display_name !== customer.name && customer.name" 
                            class="ml-2 text-xs text-gray-500">({{ customer.name }})</span>
                      <!-- 高風險警告標記 -->
                      <span v-if="customer.no_show_count >= 3" 
                            class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-red-100 text-red-800 border border-red-300 animate-pulse"
                            title="高風險客戶：多次爽約">
                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        高風險
                      </span>
                      <span v-else-if="customer.late_count >= 5" 
                            class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-orange-100 text-orange-800 border border-orange-300"
                            title="經常遲到客戶">
                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        常遲到
                      </span>
                    </div>
                    <div class="text-sm text-gray-500">
                      <span>ID: {{ customer.id || '無' }}</span>
                      <span v-if="customer.line_user_id" class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">
                        LINE
                      </span>
                    </div>
                  </div>
                </div>
              </td>
              
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-gray-900">
                  <div class="flex items-center mb-1" v-if="customer.phone">
                    <svg class="w-4 h-4 text-gray-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                    </svg>
                    {{ customer.phone }}
                  </div>
                  <div class="flex items-center" v-if="customer.email">
                    <svg class="w-4 h-4 text-gray-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                    {{ customer.email }}
                  </div>
                  <div v-if="!customer.phone && !customer.email" class="text-gray-400 text-sm">
                    無聯絡資料
                  </div>
                </div>
              </td>
              
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="space-y-2">
                  <span :class="getLevelBadgeClass(customer.level)" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium">
                    {{ getLevelText(customer.level) }}
                  </span>
                  <div>
                    <span :class="getStatusBadgeClass(getCustomerActivityStatus(customer))" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium">
                      {{ getStatusText(getCustomerActivityStatus(customer)) }}
                    </span>
                  </div>
                </div>
              </td>
              
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-gray-900">
                  <div class="flex items-center mb-1">
                    <svg class="w-4 h-4 text-gray-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    <span class="font-medium">{{ customer.total_reservations || 0 }}</span> 次預約
                  </div>
                  <div class="flex items-center mb-1">
                    <svg class="w-4 h-4 text-gray-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1" />
                    </svg>
                    <span class="font-medium text-green-600">NT$ {{ Number(customer.total_spent || 0).toLocaleString() }}</span>
                  </div>
                  <div v-if="customer.no_show_count > 0" class="flex items-center mb-1">
                    <svg class="w-4 h-4 text-red-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <span class="font-medium text-red-600">{{ customer.no_show_count }}</span> 次爽約
                  </div>
                  <div v-if="customer.late_count > 0" class="flex items-center mb-1">
                    <svg class="w-4 h-4 text-orange-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span class="font-medium text-orange-600">{{ customer.late_count }}</span> 次遲到
                  </div>
                  <div v-if="customer.total_reservations > 0" class="text-xs text-gray-500 mt-1">
                    平均每次: NT$ {{ Math.round((customer.total_spent || 0) / (customer.total_reservations || 1)).toLocaleString() }}
                  </div>
                </div>
              </td>
              
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                <div class="flex items-center">
                  <svg class="w-4 h-4 text-gray-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                  {{ formatDate(customer.last_interaction_at) }}
                </div>
              </td>
              
              <td class="px-6 py-4 whitespace-nowrap text-center">
                <div class="flex items-center justify-center space-x-2">
                  <button
                    @click="viewCustomer(customer)"
                    class="inline-flex items-center p-1.5 text-blue-600 hover:text-blue-700 hover:bg-blue-50 rounded-lg transition-colors"
                    title="查看詳情"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                  </button>
                  <button
                    @click="toggleBlockCustomer(customer)"
                    :class="[
                      'inline-flex items-center p-1.5 rounded-lg transition-colors',
                      customer.status === 'blocked' 
                        ? 'text-green-600 hover:text-green-700 hover:bg-green-50' 
                        : 'text-orange-600 hover:text-orange-700 hover:bg-orange-50'
                    ]"
                    :title="customer.status === 'blocked' ? '解除封鎖' : '封鎖客戶'"
                  >
                    <svg v-if="customer.status === 'blocked'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z" />
                    </svg>
                    <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                    </svg>
                  </button>
                  <button
                    @click="editCustomer(customer)"
                    class="inline-flex items-center p-1.5 text-indigo-600 hover:text-indigo-700 hover:bg-indigo-50 rounded-lg transition-colors"
                    title="編輯客戶"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                  </button>
                  <button
                    @click="deleteCustomer(customer)"
                    class="inline-flex items-center p-1.5 text-red-600 hover:text-red-700 hover:bg-red-50 rounded-lg transition-colors"
                    title="刪除客戶"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      
      <!-- 分頁控制 -->
      <div v-if="pagination.total > 0" class="bg-gray-50 px-6 py-3 border-t border-gray-200">
        <div class="flex items-center justify-between">
          <div class="text-sm text-gray-700">
            顯示第 {{ (pagination.current_page - 1) * pagination.per_page + 1 }} 到 
            {{ Math.min(pagination.current_page * pagination.per_page, pagination.total) }} 筆，
            共 {{ pagination.total }} 筆資料
          </div>
          <div class="flex items-center space-x-2">
            <button
              @click="changePage(pagination.current_page - 1)"
              :disabled="pagination.current_page <= 1"
              class="inline-flex items-center px-3 py-2 border border-gray-300 text-sm font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            >
              <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
              </svg>
              上一頁
            </button>
            
            <span class="text-sm text-gray-700">
              第 {{ pagination.current_page }} / {{ pagination.last_page }} 頁
            </span>
            
            <button
              @click="changePage(pagination.current_page + 1)"
              :disabled="pagination.current_page >= pagination.last_page"
              class="inline-flex items-center px-3 py-2 border border-gray-300 text-sm font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            >
              下一頁
              <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
              </svg>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- 新增/編輯客戶 Modal -->
    <div v-if="showAddModal || showEditModal" class="fixed inset-0 bg-black bg-opacity-50 flex justify-center items-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
        <!-- Modal 標題 -->
        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50 rounded-t-xl">
          <div class="flex items-center justify-between">
            <div>
              <h3 class="text-lg font-semibold text-gray-900">
                {{ showAddModal ? '新增客戶' : '編輯客戶' }}
              </h3>
              <p class="text-sm text-gray-600 mt-1">
                {{ showAddModal ? '建立新的客戶資料' : '修改客戶資訊' }}
              </p>
            </div>
            <button
              @click="closeModals"
              class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-colors"
            >
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
        </div>
        
        <!-- Modal 內容 -->
        <form @submit.prevent="showAddModal ? addCustomer() : updateCustomer()" class="p-6">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- 基本資訊 -->
            <div class="md:col-span-2">
              <h4 class="text-md font-medium text-gray-900 mb-4 flex items-center">
                <svg class="w-5 h-5 text-blue-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
                基本資訊
              </h4>
            </div>
            
            <div class="space-y-2">
              <label class="text-sm font-medium text-gray-700">客戶姓名 *</label>
              <input
                v-model="customerForm.name"
                type="text"
                required
                placeholder="請輸入客戶姓名"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
              />
            </div>
            
            <div class="space-y-2">
              <label class="text-sm font-medium text-gray-700">聯絡電話</label>
              <input
                v-model="customerForm.phone"
                type="tel"
                placeholder="0912-345-678"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
              />
            </div>
            
            <div class="md:col-span-2 space-y-2">
              <label class="text-sm font-medium text-gray-700">電子信箱</label>
              <input
                v-model="customerForm.email"
                type="email"
                placeholder="customer@example.com"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
              />
            </div>
            
            <!-- 個人資訊 -->
            <div class="md:col-span-2 pt-4 border-t border-gray-200">
              <h4 class="text-md font-medium text-gray-900 mb-4 flex items-center">
                <svg class="w-5 h-5 text-green-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                個人資訊
              </h4>
            </div>
            
            <div class="space-y-2">
              <label class="text-sm font-medium text-gray-700">性別</label>
              <select
                v-model="customerForm.gender"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
              >
                <option value="">請選擇性別</option>
                <option value="male">男性</option>
                <option value="female">女性</option>
                <option value="other">其他</option>
              </select>
            </div>
            
            <div class="space-y-2">
              <label class="text-sm font-medium text-gray-700">生日</label>
              <input
                v-model="customerForm.birthday"
                type="date"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
              />
            </div>
            
            <div class="md:col-span-2 space-y-2">
              <label class="text-sm font-medium text-gray-700">地址</label>
              <textarea
                v-model="customerForm.address"
                rows="2"
                placeholder="請輸入完整地址"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors resize-none"
              ></textarea>
            </div>
            
            <div class="md:col-span-2 space-y-2">
              <label class="text-sm font-medium text-gray-700">備註</label>
              <textarea
                v-model="customerForm.notes"
                rows="3"
                placeholder="客戶偏好、特殊需求或其他備註..."
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors resize-none"
              ></textarea>
            </div>
          </div>
          
          <!-- Modal 操作按鈕 -->
          <div class="flex justify-end space-x-3 pt-6 mt-6 border-t border-gray-200">
            <button
              type="button"
              @click="closeModals"
              class="px-4 py-2 text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-gray-500 transition-colors"
            >
              取消
            </button>
            <button
              type="submit"
              :disabled="submitting"
              class="px-6 py-2 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-lg hover:from-blue-700 hover:to-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-200 flex items-center"
            >
              <svg v-if="submitting" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
              {{ submitting ? '處理中...' : (showAddModal ? '新增客戶' : '更新資料') }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- 刪除客戶確認 Modal -->
    <div v-if="showDeleteModal && deletingCustomer" class="fixed inset-0 bg-black bg-opacity-50 flex justify-center items-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-xl max-w-lg w-full">
        <div class="px-6 py-4 border-b border-gray-200 bg-red-50 rounded-t-xl">
          <div class="flex items-center space-x-3">
            <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center">
              <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
              </svg>
            </div>
            <div>
              <h3 class="text-xl font-bold text-red-900">危險操作</h3>
              <p class="text-sm text-red-700">刪除後將無法恢復</p>
            </div>
          </div>
        </div>
        
        <div class="p-6">
          <div class="mb-6 p-4 bg-red-50 border-2 border-red-200 rounded-lg">
            <p class="font-semibold text-red-900 mb-3">刪除客戶將同時刪除以下資料：</p>
            <ul class="space-y-2 text-sm">
              <li class="flex items-center text-red-800">
                <svg class="w-5 h-5 mr-2 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span class="font-medium">{{ deletingCustomer.total_reservations || 0 }} 筆預約記錄</span>
              </li>
              <li class="flex items-center text-red-800">
                <svg class="w-5 h-5 mr-2 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1" />
                </svg>
                <span class="font-medium">NT$ {{ Number(deletingCustomer.total_spent || 0).toLocaleString() }} 消費記錄</span>
              </li>
              <li class="flex items-center text-red-800">
                <svg class="w-5 h-5 mr-2 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                </svg>
                <span class="font-medium">所有 LINE 互動記錄</span>
              </li>
            </ul>
          </div>

          <div class="mb-4">
            <p class="text-gray-700 mb-4 font-medium">
              如果您確定要刪除客戶「<span class="text-red-600 font-bold">{{ deletingCustomer.line_display_name || deletingCustomer.name }}</span>」，
              請在下方輸入框中輸入客戶姓名以確認：
            </p>
            <input
              v-model="deleteConfirmText"
              type="text"
              :placeholder="`請輸入：${deletingCustomer.line_display_name || deletingCustomer.name}`"
              class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 transition-colors text-lg"
              @keyup.enter="deleteConfirmText === (deletingCustomer.line_display_name || deletingCustomer.name) && confirmDeleteCustomer()"
            />
          </div>

          <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 mb-4">
            <p class="text-sm text-yellow-800 flex items-start">
              <svg class="w-5 h-5 text-yellow-600 mr-2 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
              </svg>
              <span>
                <strong>建議：</strong>如果只是暫時停止服務，建議使用「封鎖」功能而非刪除，可隨時恢復。
              </span>
            </p>
          </div>
        </div>
        
        <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 rounded-b-xl flex justify-end space-x-3">
          <button
            @click="showDeleteModal = false; deletingCustomer = null; deleteConfirmText = ''"
            class="px-5 py-2 text-gray-700 bg-white border border-gray-300 hover:bg-gray-50 rounded-lg focus:outline-none focus:ring-2 focus:ring-gray-500 transition-colors"
          >
            取消
          </button>
          <button
            @click="confirmDeleteCustomer"
            :disabled="deleteConfirmText !== (deletingCustomer.line_display_name || deletingCustomer.name) || submitting"
            class="px-6 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-200 flex items-center font-medium"
          >
            <svg v-if="submitting" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <svg v-else class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
            </svg>
            {{ submitting ? '刪除中...' : '確認刪除' }}
          </button>
        </div>
      </div>
    </div>

    <!-- 封鎖客戶 Modal -->
    <div v-if="showBlockModal && blockingCustomer" class="fixed inset-0 bg-black bg-opacity-50 flex justify-center items-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-xl max-w-md w-full">
        <div class="px-6 py-4 border-b border-gray-200 bg-red-50 rounded-t-xl">
          <div class="flex items-center justify-between">
            <div class="flex items-center space-x-3">
              <div class="w-10 h-10 bg-red-100 rounded-full flex items-center justify-center">
                <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
              </div>
              <div>
                <h3 class="text-lg font-bold text-red-900">封鎖客戶</h3>
                <p class="text-sm text-red-700">此操作將限制客戶預約功能</p>
              </div>
            </div>
            <button
              @click="showBlockModal = false; blockingCustomer = null"
              class="p-2 text-gray-400 hover:text-gray-600 hover:bg-white hover:bg-opacity-50 rounded-lg transition-colors"
            >
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
        </div>
        
        <div class="p-6">
          <div class="mb-4 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
            <p class="text-sm text-yellow-800 flex items-start">
              <svg class="w-5 h-5 text-yellow-600 mr-2 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <span>
                封鎖客戶「<strong>{{ blockingCustomer.line_display_name || blockingCustomer.name }}</strong>」後：
                <br>• 客戶將無法透過 LINE 或網頁進行任何預約
                <br>• 現有未完成的預約不受影響
                <br>• 可隨時解除封鎖恢復功能
              </span>
            </p>
          </div>

          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">
                封鎖原因 <span class="text-red-500">*</span>
              </label>
              <select
                v-model="blockForm.reason"
                required
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 transition-colors"
              >
                <option value="">請選擇封鎖原因</option>
                <option value="no_show">多次爽約（未到場）</option>
                <option value="late">經常遲到</option>
                <option value="rude">態度不佳或言行不當</option>
                <option value="payment">付款問題</option>
                <option value="spam">疑似濫用或騷擾</option>
                <option value="other">其他原因</option>
              </select>
            </div>
            
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">
                詳細說明
                <span class="text-gray-500 text-xs">（選填，建議填寫以備查詢）</span>
              </label>
              <textarea
                v-model="blockForm.notes"
                rows="3"
                placeholder="請描述封鎖的具體原因，例如：連續 3 次爽約、遲到超過 30 分鐘..."
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 transition-colors resize-none"
              ></textarea>
            </div>
          </div>
        </div>
        
        <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 rounded-b-xl flex justify-end space-x-3">
          <button
            @click="showBlockModal = false; blockingCustomer = null"
            class="px-4 py-2 text-gray-700 bg-white border border-gray-300 hover:bg-gray-50 rounded-lg focus:outline-none focus:ring-2 focus:ring-gray-500 transition-colors"
          >
            取消
          </button>
          <button
            @click="confirmBlockCustomer"
            :disabled="!blockForm.reason || submitting"
            class="px-6 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-200 flex items-center"
          >
            <svg v-if="submitting" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            {{ submitting ? '處理中...' : '確認封鎖' }}
          </button>
        </div>
      </div>
    </div>

    <!-- 查看客戶詳情 Modal -->
    <div v-if="showViewModal && viewingCustomer" class="fixed inset-0 bg-black bg-opacity-50 flex justify-center items-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
        <!-- Modal 標題 -->
        <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-blue-50 to-purple-50 rounded-t-xl sticky top-0 z-10">
          <div class="flex items-center justify-between">
            <div class="flex items-center space-x-4">
              <!-- 客戶頭像 -->
              <div v-if="viewingCustomer.line_picture_url" class="h-16 w-16 rounded-full overflow-hidden border-2 border-white shadow-md">
                <img 
                  :src="viewingCustomer.line_picture_url" 
                  :alt="viewingCustomer.line_display_name || viewingCustomer.name"
                  class="h-full w-full object-cover"
                  @error="$event.target.style.display='none'; $event.target.nextElementSibling.style.display='flex'"
                />
                <div class="h-16 w-16 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center" style="display: none;">
                  <span class="text-xl font-semibold text-white">
                    {{ (viewingCustomer.line_display_name || viewingCustomer.name || '?').charAt(0).toUpperCase() }}
                  </span>
                </div>
              </div>
              <div v-else class="h-16 w-16 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center shadow-md">
                <span class="text-xl font-semibold text-white">
                  {{ (viewingCustomer.line_display_name || viewingCustomer.name || '?').charAt(0).toUpperCase() }}
                </span>
              </div>
              
              <div>
                <h3 class="text-xl font-bold text-gray-900">
                  {{ viewingCustomer.line_display_name || viewingCustomer.name || '未知客戶' }}
                </h3>
                <p class="text-sm text-gray-600 mt-1">
                  ID: {{ viewingCustomer.id }}
                  <span v-if="viewingCustomer.line_user_id" class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">
                    LINE
                  </span>
                  <span :class="getStatusBadgeClass(getCustomerActivityStatus(viewingCustomer))" class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium">
                    {{ getStatusText(getCustomerActivityStatus(viewingCustomer)) }}
                  </span>
                  <span :class="getLevelBadgeClass(viewingCustomer.level)" class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium">
                    {{ getLevelText(viewingCustomer.level) }}
                  </span>
                </p>
              </div>
            </div>
            <button
              @click="showViewModal = false; viewingCustomer = null"
              class="p-2 text-gray-400 hover:text-gray-600 hover:bg-white hover:bg-opacity-50 rounded-lg transition-colors"
            >
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
        </div>
        
        <!-- Modal 內容 -->
        <div class="p-6">
          <!-- 基本資訊卡片 -->
          <div class="bg-white rounded-xl border border-gray-200 p-6 mb-6">
            <h4 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
              <svg class="w-5 h-5 text-blue-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
              </svg>
              基本資訊
            </h4>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div class="flex items-start space-x-3">
                <svg class="w-5 h-5 text-gray-400 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
                <div>
                  <p class="text-sm text-gray-500">姓名</p>
                  <p class="text-base font-medium text-gray-900">{{ viewingCustomer.name || '-' }}</p>
                </div>
              </div>
              <div class="flex items-start space-x-3">
                <svg class="w-5 h-5 text-gray-400 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                </svg>
                <div>
                  <p class="text-sm text-gray-500">電話</p>
                  <p class="text-base font-medium text-gray-900">{{ viewingCustomer.phone || '-' }}</p>
                </div>
              </div>
              <div class="flex items-start space-x-3">
                <svg class="w-5 h-5 text-gray-400 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                </svg>
                <div>
                  <p class="text-sm text-gray-500">Email</p>
                  <p class="text-base font-medium text-gray-900">{{ viewingCustomer.email || '-' }}</p>
                </div>
              </div>
              <div class="flex items-start space-x-3">
                <svg class="w-5 h-5 text-gray-400 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                <div>
                  <p class="text-sm text-gray-500">生日</p>
                  <p class="text-base font-medium text-gray-900">{{ viewingCustomer.birthday || '-' }}</p>
                </div>
              </div>
              <div class="flex items-start space-x-3">
                <svg class="w-5 h-5 text-gray-400 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                </svg>
                <div>
                  <p class="text-sm text-gray-500">性別</p>
                  <p class="text-base font-medium text-gray-900">
                    {{ viewingCustomer.gender === 'male' ? '男性' : viewingCustomer.gender === 'female' ? '女性' : viewingCustomer.gender === 'other' ? '其他' : '-' }}
                  </p>
                </div>
              </div>
              <div class="flex items-start space-x-3">
                <svg class="w-5 h-5 text-gray-400 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <div>
                  <p class="text-sm text-gray-500">最後互動</p>
                  <p class="text-base font-medium text-gray-900">{{ formatDate(viewingCustomer.last_interaction_at) }}</p>
                </div>
              </div>
              <div v-if="viewingCustomer.address" class="md:col-span-2 flex items-start space-x-3">
                <svg class="w-5 h-5 text-gray-400 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                <div>
                  <p class="text-sm text-gray-500">地址</p>
                  <p class="text-base font-medium text-gray-900">{{ viewingCustomer.address }}</p>
                </div>
              </div>
              <div v-if="viewingCustomer.notes" class="md:col-span-2 flex items-start space-x-3">
                <svg class="w-5 h-5 text-gray-400 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <div>
                  <p class="text-sm text-gray-500">備註</p>
                  <p class="text-base font-medium text-gray-900">{{ viewingCustomer.notes }}</p>
                </div>
              </div>
            </div>
          </div>

          <!-- 統計資訊卡片 -->
          <div class="bg-white rounded-xl border border-gray-200 p-6 mb-6">
            <h4 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
              <svg class="w-5 h-5 text-purple-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
              </svg>
              消費統計
            </h4>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
              <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-lg p-4">
                <div class="flex items-center justify-between">
                  <div>
                    <p class="text-sm text-blue-600 font-medium">總預約次數</p>
                    <p class="text-2xl font-bold text-blue-900 mt-1">{{ viewingCustomer.all_reservations_count || viewingCustomer.total_reservations || 0 }}</p>
                  </div>
                  <div class="w-12 h-12 bg-blue-200 rounded-full flex items-center justify-center">
                    <svg class="w-6 h-6 text-blue-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                  </div>
                </div>
              </div>
              <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-lg p-4">
                <div class="flex items-center justify-between">
                  <div>
                    <p class="text-sm text-green-600 font-medium">總消費金額</p>
                    <p class="text-2xl font-bold text-green-900 mt-1">NT$ {{ Number(viewingCustomer.total_spent || 0).toLocaleString() }}</p>
                  </div>
                  <div class="w-12 h-12 bg-green-200 rounded-full flex items-center justify-center">
                    <svg class="w-6 h-6 text-green-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1" />
                    </svg>
                  </div>
                </div>
              </div>
              <div class="bg-gradient-to-br from-purple-50 to-purple-100 rounded-lg p-4">
                <div class="flex items-center justify-between">
                  <div>
                    <p class="text-sm text-purple-600 font-medium">平均消費</p>
                    <p class="text-2xl font-bold text-purple-900 mt-1">
                      NT$ {{ viewingCustomer.total_reservations > 0 ? Math.round((viewingCustomer.total_spent || 0) / viewingCustomer.total_reservations).toLocaleString() : 0 }}
                    </p>
                  </div>
                  <div class="w-12 h-12 bg-purple-200 rounded-full flex items-center justify-center">
                    <svg class="w-6 h-6 text-purple-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z" />
                    </svg>
                  </div>
                </div>
              </div>
              <div v-if="viewingCustomer.no_show_count > 0 || viewingCustomer.late_count > 0" class="bg-gradient-to-br from-yellow-50 to-orange-100 rounded-lg p-4">
                <div class="flex items-center justify-between">
                  <div>
                    <p class="text-sm text-orange-600 font-medium">特殊記錄</p>
                    <div class="mt-1 space-y-1">
                      <p v-if="viewingCustomer.no_show_count > 0" class="text-lg font-bold text-red-700">
                        爽約 {{ viewingCustomer.no_show_count }} 次
                      </p>
                      <p v-if="viewingCustomer.late_count > 0" class="text-lg font-bold text-orange-700">
                        遲到 {{ viewingCustomer.late_count }} 次
                      </p>
                    </div>
                  </div>
                  <div class="w-12 h-12 bg-orange-200 rounded-full flex items-center justify-center">
                    <svg class="w-6 h-6 text-orange-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- 查看預約歷史按鈕 -->
          <div class="bg-white rounded-xl border border-gray-200 p-4">
            <button
              @click="openReservationHistory(viewingCustomer)"
              class="w-full flex items-center justify-center px-6 py-3 bg-gradient-to-r from-indigo-500 to-purple-600 text-white rounded-lg hover:from-indigo-600 hover:to-purple-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition-all duration-200 shadow-sm"
            >
              <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
              </svg>
              <span class="font-medium">查看預約歷史記錄</span>
            </button>
          </div>

          <!-- 快速操作按鈕 -->
          <div class="flex justify-end items-center pt-4 border-t border-gray-200">
            <div class="flex space-x-3">
              <button
                @click="toggleBlockCustomer(viewingCustomer); showViewModal = false"
                :class="[
                  'px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-offset-2 transition-all duration-200 flex items-center',
                  viewingCustomer.status === 'blocked'
                    ? 'bg-green-100 text-green-700 hover:bg-green-200 focus:ring-green-500'
                    : 'bg-orange-100 text-orange-700 hover:bg-orange-200 focus:ring-orange-500'
                ]"
              >
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path v-if="viewingCustomer.status === 'blocked'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z" />
                  <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                </svg>
                {{ viewingCustomer.status === 'blocked' ? '解除封鎖' : '封鎖客戶' }}
              </button>
              <button
                @click="editCustomer(viewingCustomer); showViewModal = false"
                class="px-4 py-2 bg-indigo-100 text-indigo-700 hover:bg-indigo-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition-all duration-200 flex items-center"
              >
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
                編輯資料
              </button>
              <button
                @click="showViewModal = false; viewingCustomer = null"
                class="px-4 py-2 bg-gray-100 text-gray-700 hover:bg-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition-all duration-200"
              >
                關閉
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- 預約歷史 Modal -->
    <div v-if="showReservationHistoryModal && viewingCustomer" class="fixed inset-0 bg-black bg-opacity-50 flex justify-center items-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
        <!-- Modal 標題 -->
        <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-indigo-50 to-purple-50 rounded-t-xl sticky top-0 z-10">
          <div class="flex items-center justify-between">
            <div class="flex items-center space-x-3">
              <div class="w-10 h-10 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                </svg>
              </div>
              <div>
                <h3 class="text-xl font-bold text-gray-900">預約歷史記錄</h3>
                <p class="text-sm text-gray-600 mt-0.5">
                  {{ viewingCustomer.line_display_name || viewingCustomer.name }} 的所有預約記錄
                </p>
              </div>
            </div>
            <button
              @click="showReservationHistoryModal = false; customerReservations = []"
              class="p-2 text-gray-400 hover:text-gray-600 hover:bg-white hover:bg-opacity-50 rounded-lg transition-colors"
            >
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
        </div>
        
        <!-- Modal 內容 -->
        <div class="p-6">
          <!-- 載入中 -->
          <div v-if="loadingReservations" class="text-center py-12">
            <div class="inline-flex items-center justify-center w-12 h-12">
              <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-600"></div>
            </div>
            <p class="mt-4 text-sm text-gray-500">載入預約記錄中...</p>
          </div>
          
          <!-- 無預約記錄 -->
          <div v-else-if="!customerReservations || customerReservations.length === 0" class="text-center py-12">
            <div class="w-16 h-16 mx-auto bg-gray-100 rounded-full flex items-center justify-center mb-4">
              <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
              </svg>
            </div>
            <h3 class="text-lg font-medium text-gray-900 mb-2">尚無預約記錄</h3>
            <p class="text-sm text-gray-500">此客戶目前還沒有任何預約記錄</p>
          </div>
          
          <!-- 預約列表 -->
          <div v-else class="space-y-4">
            <div
              v-for="reservation in customerReservations"
              :key="reservation.id"
              class="border-l-4 pl-5 py-4 rounded-r-lg hover:shadow-md transition-all duration-200"
              :class="{
                'border-gray-500 bg-gray-50': reservation.status === 'cancelled',
                'border-red-500 bg-red-50': reservation.check_in_status === 'no_show' && reservation.status !== 'cancelled',
                'border-orange-500 bg-orange-50': reservation.check_in_status === 'late' && reservation.status !== 'cancelled',
                'border-purple-500 bg-purple-50': reservation.status === 'completed',
                'border-blue-500 bg-blue-50': reservation.check_in_status === 'checked_in' && reservation.status !== 'completed' && reservation.status !== 'cancelled',
                'border-green-500 bg-green-50': reservation.status === 'confirmed' && !reservation.check_in_status,
                'border-yellow-500 bg-yellow-50': reservation.status === 'pending'
              }"
            >
              <div class="flex items-start justify-between">
                <div class="flex-1">
                  <!-- 日期時間 -->
                  <div class="flex items-center space-x-2 mb-3">
                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    <span class="text-lg font-semibold text-gray-900">
                      {{ reservation.date }}
                    </span>
                    <span class="text-gray-500">{{ reservation.available_time?.start_time }}</span>
                  </div>
                  
                  <!-- 服務資訊 -->
                  <div class="flex items-center space-x-2 mb-2">
                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                    <span class="text-base text-gray-700 font-medium">
                      {{ reservation.service?.name || '未指定服務' }}
                    </span>
                  </div>
                  
                  <!-- 金額 -->
                  <div class="flex items-center space-x-2">
                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1" />
                    </svg>
                    <span class="text-base font-semibold text-green-700">
                      NT$ {{ reservation.payment_amount ? Number(reservation.payment_amount).toLocaleString() : (reservation.service?.price ? Number(reservation.service.price).toLocaleString() : '0') }}
                    </span>
                  </div>
                  
                  <!-- 備註 -->
                  <div v-if="reservation.reservation_notes || reservation.notes" class="mt-3 pt-3 border-t border-gray-200">
                    <div class="flex items-start space-x-2">
                      <svg class="w-4 h-4 text-gray-400 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
                      </svg>
                      <div class="text-sm text-gray-600">
                        <p v-if="reservation.reservation_notes" class="mb-1">
                          <span class="font-medium text-gray-700">預約備註：</span>{{ reservation.reservation_notes }}
                        </p>
                        <p v-if="reservation.notes && reservation.notes !== reservation.reservation_notes">
                          <span class="font-medium text-gray-700">系統備註：</span>{{ reservation.notes }}
                        </p>
                      </div>
                    </div>
                  </div>
                </div>
                
                <!-- 狀態標籤 -->
                <div class="flex flex-col items-end space-y-2 ml-4">
                  <!-- 預約狀態 -->
                  <span
                    class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium"
                    :class="{
                      'bg-purple-100 text-purple-800': reservation.status === 'completed',
                      'bg-green-100 text-green-800': reservation.status === 'confirmed' && reservation.status !== 'completed',
                      'bg-yellow-100 text-yellow-800': reservation.status === 'pending',
                      'bg-gray-100 text-gray-800': reservation.status === 'cancelled'
                    }"
                  >
                    {{ reservation.status === 'completed' ? '✓ 已完成' : reservation.status === 'confirmed' ? '已確認' : reservation.status === 'pending' ? '待確認' : '已取消' }}
                  </span>
                  
                  <!-- 報到狀態 -->
                  <span
                    v-if="reservation.check_in_status && reservation.status !== 'completed' && reservation.status !== 'cancelled'"
                    class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium"
                    :class="{
                      'bg-blue-100 text-blue-800': reservation.check_in_status === 'checked_in',
                      'bg-orange-100 text-orange-800': reservation.check_in_status === 'late',
                      'bg-red-100 text-red-800': reservation.check_in_status === 'no_show'
                    }"
                  >
                    {{ reservation.check_in_status === 'checked_in' ? '✓ 已報到' : reservation.check_in_status === 'late' ? '⏰ 遲到' : '✗ 爽約' }}
                  </span>
                  
                  <!-- 付款狀態 -->
                  <span
                    v-if="reservation.payment_status && reservation.payment_status !== 'unpaid'"
                    class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium"
                    :class="{
                      'bg-green-100 text-green-800': reservation.payment_status === 'paid',
                      'bg-yellow-100 text-yellow-800': reservation.payment_status === 'partial',
                      'bg-gray-100 text-gray-800': reservation.payment_status === 'refunded'
                    }"
                  >
                    {{ reservation.payment_status === 'paid' ? '💰 已付款' : reservation.payment_status === 'partial' ? '💰 部分付款' : '💰 已退款' }}
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Modal 底部 -->
        <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 rounded-b-xl flex justify-end">
          <button
            @click="showReservationHistoryModal = false; customerReservations = []"
            class="px-6 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition-all duration-200"
          >
            關閉
          </button>
        </div>
      </div>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted, watch } from 'vue'
import { apiGet, apiPost, apiPut, apiDelete } from '../utils/api.js'

// Debounce 函數
function debounce(fn, delay) {
  let timeoutId
  return function (...args) {
    clearTimeout(timeoutId)
    timeoutId = setTimeout(() => fn.apply(this, args), delay)
  }
}

// API 基礎設定


// 響應式數據
const loading = ref(false)
const error = ref('')
const customers = ref([])
const statistics = ref({})
const pagination = ref({
  current_page: 1,
  last_page: 1,
  per_page: 20,
  total: 0
})

// 搜尋表單
const searchForm = reactive({
  search: '',
  status: '',
  level: '',
  page: 1
})

// Modal 狀態
const showAddModal = ref(false)
const showEditModal = ref(false)
const showBlockModal = ref(false)
const showDeleteModal = ref(false)
const blockingCustomer = ref(null)
const deletingCustomer = ref(null)
const deleteConfirmText = ref('')
const currentCustomer = ref(null)
const submitting = ref(false)
const calculatingStats = ref(false)
const lastRecalculateTime = ref({}) // 記錄每個客戶的上次計算時間
const recalculateCooldown = 30 // 冷卻時間（秒）

// 客戶表單
const customerForm = reactive({
  name: '',
  phone: '',
  email: '',
  gender: '',
  birthday: '',
  address: '',
  notes: ''
})

// 封鎖表單
const blockForm = reactive({
  reason: '',
  notes: ''
})

// 獲取客戶列表
async function fetchCustomers() {
  loading.value = true
  error.value = ''
  
  try {
    const params = {}
    if (searchForm.search) params.search = searchForm.search
    if (searchForm.status) params.status = searchForm.status
    if (searchForm.level) params.level = searchForm.level
    params.page = searchForm.page
    
    const data = await apiGet('/customers', params)
    
    // 驗證返回的資料結構
    if (data && data.data && Array.isArray(data.data)) {
      customers.value = data.data
      pagination.value = data.pagination || {
        current_page: 1,
        last_page: 1,
        per_page: 20,
        total: 0
      }
    } else {
      throw new Error('返回的客戶資料格式不正確')
    }
  } catch (err) {
    error.value = err.message || '獲取客戶資料失敗'
    customers.value = []
    
    if (import.meta.env.DEV) {
      console.error('獲取客戶列表失敗:', err)
    }
  } finally {
    loading.value = false
  }
}

// 獲取統計資料
async function fetchStatistics() {
  try {
    const data = await apiGet('/customers/statistics')
    statistics.value = data.data
  } catch (err) {
    if (import.meta.env.DEV) {
      console.error('獲取統計資料失敗:', err)
    }
  }
}

// 搜尋客戶
function searchCustomers() {
  searchForm.page = 1
  fetchCustomers()
}

// 建立 debounced 搜尋函數
const debouncedSearch = debounce(() => {
  searchCustomers()
}, 500)

// 監聽搜尋表單變化，自動觸發搜尋
watch(
  () => [searchForm.search, searchForm.status, searchForm.level],
  () => {
    debouncedSearch()
  }
)

// 換頁
function changePage(page) {
  if (page >= 1 && page <= pagination.value.last_page) {
    searchForm.page = page
    fetchCustomers()
  }
}

// 重置客戶表單
function resetCustomerForm() {
  Object.assign(customerForm, {
    name: '',
    phone: '',
    email: '',
    gender: '',
    birthday: '',
    address: '',
    notes: ''
  })
}

// 新增客戶
async function addCustomer() {
  submitting.value = true
  
  try {
    await apiPost('/customers', customerForm)
    closeModals()
    await fetchCustomers()
    await fetchStatistics()
  } catch (err) {
    error.value = err.message
    if (import.meta.env.DEV) {
      console.error('新增客戶失敗:', err)
    }
    // 如果是驗證錯誤，顯示詳細信息
    if (err.response && err.response.data && err.response.data.errors) {
      const errors = err.response.data.errors
      const errorMessages = Object.values(errors).flat().join('\n')
      alert('驗證錯誤:\n' + errorMessages)
    }
  } finally {
    submitting.value = false
  }
}

// 編輯客戶
function editCustomer(customer) {
  currentCustomer.value = customer
  Object.assign(customerForm, {
    name: customer.name || '',
    phone: customer.phone || '',
    email: customer.email || '',
    gender: customer.gender || '',
    birthday: customer.birthday || '',
    address: customer.address || '',
    notes: customer.notes || ''
  })
  showEditModal.value = true
}

// 更新客戶
async function updateCustomer() {
  submitting.value = true
  
  try {
    await apiPut(`/customers/${currentCustomer.value.id}`, customerForm)
    closeModals()
    await fetchCustomers()
  } catch (err) {
    // 處理不同類型的錯誤
    if (err.message.includes('No query results for model') || err.message.includes('404')) {
      // 客戶已不存在
      closeModals()
      alert('客戶資料已不存在，將自動刷新列表')
      await fetchCustomers()
    } else {
      // 其他錯誤
      error.value = err.message
      alert(`更新客戶失敗：${err.message}`)
    }
    
    if (import.meta.env.DEV) {
      console.error('更新客戶失敗:', err)
    }
  } finally {
    submitting.value = false
  }
}

// 刪除客戶 - 顯示確認 Modal
function deleteCustomer(customer) {
  deletingCustomer.value = customer
  deleteConfirmText.value = ''
  showDeleteModal.value = true
}

// 確認刪除客戶
async function confirmDeleteCustomer() {
  const customerName = deletingCustomer.value.line_display_name || deletingCustomer.value.name || '未知客戶'
  
  if (deleteConfirmText.value !== customerName) {
    alert('輸入的客戶姓名不符，請重新確認')
    return
  }
  
  submitting.value = true
  
  try {
    await apiDelete(`/customers/${deletingCustomer.value.id}`)
    
    showDeleteModal.value = false
    deletingCustomer.value = null
    deleteConfirmText.value = ''
    
    await fetchCustomers()
    await fetchStatistics()
    
    alert(`客戶「${customerName}」已成功刪除`)
  } catch (err) {
    if (err.message.includes('No query results for model') || err.message.includes('404')) {
      showDeleteModal.value = false
      alert(`客戶「${customerName}」已不存在，將自動刷新列表`)
      await fetchCustomers()
      await fetchStatistics()
    } else {
      error.value = err.message
      alert(`刪除客戶失敗：${err.message}`)
    }
    
    if (import.meta.env.DEV) {
      console.error('刪除客戶失敗:', err)
    }
  } finally {
    submitting.value = false
  }
}

// 封鎖/解鎖客戶
async function toggleBlockCustomer(customer) {
  const customerName = customer.line_display_name || customer.name || '未知客戶'
  const isBlocked = customer.status === 'blocked'
  
  if (isBlocked) {
    // 解除封鎖 - 直接確認
    if (!confirm(`確定要解除封鎖客戶「${customerName}」嗎？解除後該客戶可以正常進行預約。`)) return
    
    try {
      await apiPost(`/customers/${customer.id}/unblock`)
      await fetchCustomers()
      await fetchStatistics()
      alert(`已成功解除封鎖客戶「${customerName}」`)
    } catch (err) {
      handleApiError(err, '解除封鎖', customerName)
    }
  } else {
    // 封鎖客戶 - 顯示封鎖 Modal
    blockingCustomer.value = customer
    blockForm.reason = ''
    blockForm.notes = ''
    showBlockModal.value = true
  }
}

// 確認封鎖客戶
async function confirmBlockCustomer() {
  if (!blockForm.reason) {
    alert('請選擇封鎖原因')
    return
  }
  
  submitting.value = true
  const customerName = blockingCustomer.value.line_display_name || blockingCustomer.value.name || '未知客戶'
  
  try {
    await apiPost(`/customers/${blockingCustomer.value.id}/block`, {
      reason: blockForm.reason,
      notes: blockForm.notes
    })
    
    showBlockModal.value = false
    blockingCustomer.value = null
    await fetchCustomers()
    await fetchStatistics()
    
    alert(`已成功封鎖客戶「${customerName}」`)
  } catch (err) {
    handleApiError(err, '封鎖', customerName)
  } finally {
    submitting.value = false
  }
}

// 查看客戶詳情
const showViewModal = ref(false)
const showReservationHistoryModal = ref(false)
const viewingCustomer = ref(null)
const customerReservations = ref([])
const loadingReservations = ref(false)

async function viewCustomer(customer) {
  viewingCustomer.value = customer
  showViewModal.value = true
  
  // 載入客戶的預約歷史
  await fetchCustomerReservations(customer.id)
}

// 獲取客戶預約歷史
async function fetchCustomerReservations(customerId) {
  loadingReservations.value = true
  
  try {
    const data = await apiGet(`/customers/${customerId}`)
    if (data && data.data && data.data.reservations) {
      customerReservations.value = data.data.reservations
    }
  } catch (err) {
    console.error('載入預約歷史失敗:', err)
    customerReservations.value = []
  } finally {
    loadingReservations.value = false
  }
}

// 打開預約歷史 Modal
async function openReservationHistory(customer) {
  viewingCustomer.value = customer
  showReservationHistoryModal.value = true
  await fetchCustomerReservations(customer.id)
}

// 通用錯誤處理函數
function handleApiError(err, actionName, customerName = '') {
  if (err.message.includes('No query results for model') || err.message.includes('404')) {
    // 資源不存在
    const message = customerName 
      ? `客戶「${customerName}」已不存在，將自動刷新列表`
      : '資源已不存在，將自動刷新列表'
    alert(message)
    return true // 表示需要刷新列表
  } else {
    // 其他錯誤
    error.value = err.message
    const message = customerName 
      ? `${actionName}客戶「${customerName}」失敗：${err.message}`
      : `${actionName}失敗：${err.message}`
    alert(message)
    return false
  }
}

// 關閉所有Modal
function closeModals() {
  showAddModal.value = false
  showEditModal.value = false
  currentCustomer.value = null
  resetCustomerForm()
}

// 工具函數
function getLevelBadgeClass(level) {
  const classes = {
    'VIP': 'bg-purple-100 text-purple-800',
    'Gold': 'bg-yellow-100 text-yellow-800',
    'Silver': 'bg-gray-100 text-gray-800',
    'Bronze': 'bg-orange-100 text-orange-800'
  }
  return classes[level] || 'bg-gray-100 text-gray-800'
}

function getLevelText(level) {
  const texts = {
    'VIP': 'VIP會員',
    'Gold': '金牌會員',
    'Silver': '銀牌會員',
    'Bronze': '銅牌會員'
  }
  return texts[level] || level || '一般會員'
}

function getStatusBadgeClass(status) {
  const classes = {
    'active': 'bg-green-100 text-green-800',
    'inactive': 'bg-yellow-100 text-yellow-800',
    'blocked': 'bg-red-100 text-red-800'
  }
  return classes[status] || 'bg-gray-100 text-gray-800'
}

function getStatusText(status) {
  const texts = {
    'active': '活躍',
    'inactive': '非活躍',
    'blocked': '已封鎖'
  }
  return texts[status] || status
}

// 根據最後互動時間動態判斷活躍狀態
function getCustomerActivityStatus(customer) {
  if (customer.status === 'blocked') {
    return 'blocked'
  }
  
  if (!customer.last_interaction_at) {
    return 'inactive'
  }
  
  const lastInteraction = new Date(customer.last_interaction_at)
  const now = new Date()
  const daysDiff = Math.floor((now - lastInteraction) / (1000 * 60 * 60 * 24))
  
  // 30 天內有互動視為活躍
  return daysDiff <= 30 ? 'active' : 'inactive'
}

function formatDate(dateString) {
  if (!dateString) return '-'
  return new Date(dateString).toLocaleDateString('zh-TW')
}

// 重新計算所有客戶統計數據
async function recalculateAllStats() {
  calculatingStats.value = true
  
  try {
    const response = await apiPost('/customers/recalculate-stats')
    if (response.success) {
      // 刷新客戶列表和統計數據
      await Promise.all([
        fetchCustomers(),
        fetchStatistics()
      ])
      
      // 顯示成功訊息
      if (import.meta.env.DEV) {
        console.log('統計數據更新成功:', response.message)
      }
    }
  } catch (err) {
    error.value = err.message || '重新計算統計數據失敗'
    if (import.meta.env.DEV) {
      console.error('重新計算統計數據失敗:', err)
    }
  } finally {
    calculatingStats.value = false
  }
}

// 檢查是否可以重新計算統計（冷卻時間檢查）
function canRecalculateStats(customerId) {
  const lastTime = lastRecalculateTime.value[customerId]
  if (!lastTime) return true
  
  const now = Date.now()
  const timePassed = (now - lastTime) / 1000 // 轉換為秒
  return timePassed >= recalculateCooldown
}

// 獲取剩餘冷卻時間
function getRemainingCooldown(customerId) {
  const lastTime = lastRecalculateTime.value[customerId]
  if (!lastTime) return 0
  
  const now = Date.now()
  const timePassed = (now - lastTime) / 1000
  const remaining = recalculateCooldown - timePassed
  return Math.max(0, Math.ceil(remaining))
}

// 重新計算單一客戶統計數據
async function recalculateCustomerStats(customerId) {
  // 檢查冷卻時間
  if (!canRecalculateStats(customerId)) {
    const remaining = getRemainingCooldown(customerId)
    alert(`請稍後再試，冷卻時間剩餘 ${remaining} 秒`)
    return
  }
  
  calculatingStats.value = true
  
  try {
    const response = await apiPost(`/customers/${customerId}/recalculate-stats`)
    if (response.success) {
      // 記錄計算時間
      lastRecalculateTime.value[customerId] = Date.now()
      
      // 刷新客戶列表和詳情
      await fetchCustomers()
      
      // 如果正在查看該客戶的詳情，也刷新預約歷史
      if (viewingCustomer.value && viewingCustomer.value.id === customerId) {
        await fetchCustomerReservations(customerId)
      }
      
      alert('統計數據已更新成功')
      
      if (import.meta.env.DEV) {
        console.log('客戶統計數據更新成功:', response.message)
      }
    }
  } catch (err) {
    // 處理不同類型的錯誤
    if (err.message.includes('No query results for model') || err.message.includes('404')) {
      // 客戶已不存在，直接刷新列表
      alert('客戶已不存在，將自動刷新列表')
      await fetchCustomers()
    } else {
      // 其他錯誤
      error.value = err.message || '重新計算客戶統計數據失敗'
      alert(`重新計算統計失敗：${err.message}`)
    }
    
    if (import.meta.env.DEV) {
      console.error('重新計算客戶統計數據失敗:', err)
    }
  } finally {
    calculatingStats.value = false
  }
}

// 組件載入時獲取數據
onMounted(() => {
  fetchCustomers()
  fetchStatistics()
})
</script>
