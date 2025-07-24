<template>
  <div class="min-h-screen bg-gray-50 p-6">
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
      <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 mb-4">
        <div>
          <h2 class="text-lg font-semibold text-gray-900">客戶搜尋與篩選</h2>
          <p class="text-sm text-gray-600">快速找到您需要的客戶資料</p>
        </div>
        <button
          @click="showAddModal = true"
          class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-blue-600 to-blue-700 text-white text-sm font-medium rounded-lg hover:from-blue-700 hover:to-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-all duration-200 shadow-sm"
        >
          <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
          </svg>
          新增客戶
        </button>
      </div>
      
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
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
          <label class="text-sm font-medium text-gray-700">&nbsp;</label>
          <button
            @click="searchCustomers"
            class="w-full inline-flex items-center justify-center px-4 py-2 bg-gradient-to-r from-indigo-600 to-purple-600 text-white text-sm font-medium rounded-lg hover:from-indigo-700 hover:to-purple-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition-all duration-200"
          >
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            搜尋
          </button>
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
        <button
          @click="showAddModal = true"
          class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
        >
          <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
          </svg>
          新增第一位客戶
        </button>
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
                    <div class="h-12 w-12 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center">
                      <span class="text-sm font-semibold text-white">
                        {{ (customer.line_display_name || customer.name).charAt(0).toUpperCase() }}
                      </span>
                    </div>
                  </div>
                  <div class="ml-4">
                    <div class="text-sm font-medium text-gray-900">
                      {{ customer.line_display_name || customer.name }}
                      <span v-if="customer.line_display_name && customer.line_display_name !== customer.name" 
                            class="ml-2 text-xs text-gray-500">({{ customer.name }})</span>
                    </div>
                    <div class="text-sm text-gray-500">
                      <span>ID: {{ customer.id }}</span>
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
                    <span :class="getStatusBadgeClass(customer.status)" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium">
                      {{ getStatusText(customer.status) }}
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
                    預約 {{ customer.total_reservations || 0 }} 次
                  </div>
                  <div class="flex items-center">
                    <svg class="w-4 h-4 text-gray-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1" />
                    </svg>
                    ${{ customer.total_spent || 0 }}
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
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { apiGet, apiPost, apiPut, apiDelete } from '../utils/api.js'

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
const currentCustomer = ref(null)
const submitting = ref(false)

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
    customers.value = data.data
    pagination.value = data.pagination
  } catch (err) {
    error.value = err.message
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
    console.error('獲取統計資料失敗:', err)
  }
}

// 搜尋客戶
function searchCustomers() {
  searchForm.page = 1
  fetchCustomers()
}

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
    error.value = err.message
  } finally {
    submitting.value = false
  }
}

// 刪除客戶
async function deleteCustomer(customer) {
  if (!confirm(`確定要刪除客戶「${customer.line_display_name || customer.name}」嗎？`)) return
  
  try {
    await apiDelete(`/customers/${customer.id}`)
    await fetchCustomers()
    await fetchStatistics()
  } catch (err) {
    error.value = err.message
  }
}

// 查看客戶詳情
function viewCustomer(customer) {
  // 這裡可以導向客戶詳情頁面或開啟詳情modal
  if (import.meta.env.DEV) {
    console.log('查看客戶:', customer)
  }
  // TODO: 實現客戶詳情查看功能
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

function formatDate(dateString) {
  if (!dateString) return '-'
  return new Date(dateString).toLocaleDateString('zh-TW')
}

// 組件載入時獲取數據
onMounted(() => {
  fetchCustomers()
  fetchStatistics()
})
</script>
