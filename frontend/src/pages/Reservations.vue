<template>
  <div class="min-h-screen bg-gray-50 p-6">
    <!-- 頁面標題區域 -->
    <div class="mb-8">
      <h1 class="text-3xl font-bold text-gray-900">預約管理</h1>
      <p class="text-gray-600 mt-2">查看與管理所有預約紀錄</p>
    </div>

    <!-- 統計卡片區域 -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-6 mb-8">
      <!-- 今日預約 -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-gray-600">今日預約</p>
            <p class="text-2xl font-bold text-blue-600 mt-2">{{ todayReservations }}</p>
          </div>
          <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
          </div>
        </div>
      </div>

      <!-- 待確認預約 -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-gray-600">待確認</p>
            <p class="text-2xl font-bold text-yellow-600 mt-2">{{ pendingReservations }}</p>
          </div>
          <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
            <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
        </div>
      </div>

      <!-- 已確認預約 -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-gray-600">已確認</p>
            <p class="text-2xl font-bold text-green-600 mt-2">{{ confirmedReservations }}</p>
          </div>
          <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
        </div>
      </div>

      <!-- 已完成 - 新增 -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-gray-600">已完成</p>
            <p class="text-2xl font-bold text-purple-600 mt-2">{{ completedReservations }}</p>
          </div>
          <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
            <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
        </div>
      </div>

      <!-- 爽約記錄 - 新增 -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-gray-600">爽約記錄</p>
            <p class="text-2xl font-bold text-red-600 mt-2">{{ noShowReservations }}</p>
          </div>
          <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center">
            <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </div>
        </div>
      </div>
    </div>

    <!-- 搜尋與篩選區域 -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
      <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4">
        <div class="flex flex-col sm:flex-row items-start sm:items-center space-y-3 sm:space-y-0 sm:space-x-4 w-full lg:w-auto">
          <div class="relative w-full sm:w-80">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
              <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
              </svg>
            </div>
            <input
              v-model="search"
              type="text"
              placeholder="搜尋預約姓名、預約名稱、服務項目、備註..."
              class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors"
            />
          </div>
          
          <select
            v-model="statusFilter"
            class="px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors w-full sm:w-auto"
          >
            <option value="">所有狀態</option>
            <option value="pending">待確認</option>
            <option value="confirmed">已確認</option>
            <option value="completed">已完成</option>
            <option value="cancelled">已取消</option>
            <option value="no_show">爽約</option>
          </select>
          
          <select
            v-model="dateFilter"
            class="px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors w-full sm:w-auto"
          >
            <option value="">所有時間</option>
            <option value="today">今天</option>
            <option value="week">本週</option>
            <option value="month">本月</option>
          </select>
        </div>
        
        <div class="flex items-center space-x-3">
          <!-- 自動刷新控制 -->
          <button
            @click="toggleAutoRefresh"
            :class="{
              'bg-green-100 text-green-700 border-green-200': autoRefreshEnabled,
              'bg-gray-100 text-gray-600 border-gray-200': !autoRefreshEnabled
            }"
            class="inline-flex items-center px-3 py-2 border rounded-lg text-sm font-medium transition-colors hover:shadow-sm"
            title="切換自動刷新"
          >
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
            {{ autoRefreshEnabled ? '自動刷新' : '手動刷新' }}
          </button>
          
          <!-- 手動刷新按鈕 -->
          <button
            @click="fetchReservations"
            :disabled="loading"
            class="inline-flex items-center px-3 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            title="立即刷新"
          >
            <svg class="w-4 h-4 mr-2" :class="{ 'animate-spin': loading }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
            刷新
          </button>
          
          <div class="flex items-center space-x-2 text-sm text-gray-600 bg-gray-50 px-3 py-2 rounded-lg">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
            </svg>
            顯示 {{ filteredRecords.length }} 筆，共 {{ reservations.length }} 筆預約
          </div>
        </div>
      </div>
    </div>
    <!-- 預約列表 -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
      <!-- 載入狀態 -->
      <div v-if="loading" class="p-12 text-center">
        <div class="inline-flex items-center justify-center w-16 h-16 bg-blue-100 rounded-full mb-4">
          <svg class="animate-spin w-8 h-8 text-blue-600" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
        </div>
        <p class="text-gray-600 font-medium">載入預約資料中...</p>
        <p class="text-gray-500 text-sm mt-1">請稍候</p>
      </div>

      <!-- 錯誤狀態 -->
      <div v-else-if="error" class="p-12 text-center">
        <div class="inline-flex items-center justify-center w-16 h-16 bg-red-100 rounded-full mb-4">
          <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.732 15.5c-.77.833.192 2.5 1.732 2.5z" />
          </svg>
        </div>
        <p class="text-red-600 font-medium mb-2">載入失敗</p>
        <p class="text-gray-600 text-sm mb-4">{{ error }}</p>
        <button 
          @click="fetchReservations" 
          class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors"
        >
          <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
          </svg>
          重新載入
        </button>
      </div>

      <!-- 空狀態 -->
      <div v-else-if="filteredRecords.length === 0" class="p-12 text-center">
        <div class="inline-flex items-center justify-center w-16 h-16 bg-gray-100 rounded-full mb-4">
          <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v1M9 5a2 2 0 012 2v1M9 5V4a1 1 0 011-1h4a1 1 0 011 1v1m0 0h2a2 2 0 012 2v1m-3 0V8a1 1 0 00-1-1H9a1 1 0 00-1 1v3m0 0v3a2 2 0 002 2h6a2 2 0 002-2v-3" />
          </svg>
        </div>
        <p class="text-gray-600 font-medium mb-2">
          {{ search || statusFilter || dateFilter ? '沒有符合條件的預約' : '尚無預約紀錄' }}
        </p>
        <p class="text-gray-500 text-sm mb-4">
          {{ search || statusFilter || dateFilter ? '請嘗試調整搜尋或篩選條件' : '系統中還沒有任何預約' }}
        </p>
        <button 
          v-if="search || statusFilter || dateFilter"
          @click="clearFilters" 
          class="text-blue-600 hover:text-blue-800 font-medium text-sm"
        >
          清除篩選條件
        </button>
      </div>

      <!-- 表格內容 -->
      <div v-else class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">預約資訊</th>
              <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">服務項目</th>
              <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">預約時間</th>
              <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider notes-column">備註</th>
              <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">狀態</th>
              <th class="px-6 py-4 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">報到/收款</th>
              <th class="px-6 py-4 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">操作</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr 
              v-for="(record, index) in filteredRecords" 
              :key="record.id" 
              class="hover:bg-gray-50 transition-colors duration-200"
              :class="{ 
                'bg-yellow-50': record.status === 'pending',
                'bg-red-50': record.check_in_status === 'no_show' || record.no_show
              }"
            >
              <!-- 預約資訊 -->
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex items-center">
                  <div class="flex-shrink-0 h-10 w-10">
                    <div v-if="record.customer?.line_picture_url" class="h-10 w-10 rounded-full overflow-hidden border-2 border-gray-200">
                      <img 
                        :src="record.customer.line_picture_url" 
                        :alt="record.customer?.line_display_name || record.customer?.name || record.customer_name"
                        class="h-full w-full object-cover"
                        @error="$event.target.style.display='none'; $event.target.nextElementSibling.style.display='flex'"
                      />
                      <div class="h-10 w-10 rounded-full bg-gradient-to-r from-blue-500 to-purple-600 flex items-center justify-center shadow-md" style="display: none;">
                        <span class="text-sm font-medium text-white">
                          {{ (record.customer?.line_display_name || record.customer?.name || record.customer_name)?.charAt(0)?.toUpperCase() || 'C' }}
                        </span>
                      </div>
                    </div>
                    <div v-else class="h-10 w-10 rounded-full bg-gradient-to-r from-blue-500 to-purple-600 flex items-center justify-center shadow-md">
                      <span class="text-sm font-medium text-white">{{ (record.customer?.line_display_name || record.customer?.name || record.customer_name)?.charAt(0)?.toUpperCase() || 'C' }}</span>
                    </div>
                  </div>
                  <div class="ml-4">
                    <div class="text-sm font-medium text-gray-900">
                      {{ record.customer?.line_display_name || record.customer?.name || record.customer_name || '未指定客戶' }}
                      <span v-if="record.reservation_name && record.reservation_name !== (record.customer?.line_display_name || record.customer?.name)" 
                            class="ml-1 text-xs text-gray-500">
                        ({{ record.reservation_name }})
                      </span>
                    </div>
                    <div class="text-sm text-gray-500 flex items-center">
                      <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800">
                        #{{ record.id }}
                      </span>
                      <span v-if="record.customer_phone || record.reservation_phone" class="ml-2 text-gray-400">
                        {{ record.reservation_phone || record.customer_phone }}
                      </span>
                      <span v-if="record.customer_line_user_id" class="ml-2 text-xs text-blue-500">LINE</span>
                      
                      <!-- 備註提示圖標 -->
                      <span v-if="record.reservation_notes" 
                            class="ml-2 inline-flex items-center" 
                            :title="'預約備註：' + record.reservation_notes">
                        <svg class="w-3 h-3 text-yellow-500" fill="currentColor" viewBox="0 0 20 20">
                          <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                        </svg>
                      </span>
                    </div>
                  </div>
                </div>
              </td>
              
              <!-- 服務項目 -->
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm font-medium text-gray-900">{{ record.service_name || record.item || '未指定服務' }}</div>
                <div v-if="record.service_price" class="text-sm text-gray-500">NT$ {{ formatCurrency(record.service_price) }}</div>
              </td>
              
              <!-- 預約時間 -->
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-gray-900">{{ formatDateTime(record.reservation_date || record.time, record.reservation_time) }}</div>
                <div v-if="isToday(record.reservation_date || record.time)" class="text-xs text-blue-600 font-medium">今日</div>
                <div v-else-if="isTomorrow(record.reservation_date || record.time)" class="text-xs text-green-600 font-medium">明日</div>
              </td>
              
              <!-- 備註 -->
              <td class="px-6 py-4 max-w-xs notes-column">
                <div v-if="record.reservation_notes" class="space-y-1">
                  <!-- 預約備註 -->
                  <div class="text-xs text-green-700 bg-green-50 px-2 py-1 rounded border-l-2 border-green-300">
                    <span class="font-medium">預約備註：</span>
                    <span class="truncate">{{ record.reservation_notes.length > 30 ? record.reservation_notes.substring(0, 30) + '...' : record.reservation_notes }}</span>
                  </div>
                </div>
                <div v-else class="text-xs text-gray-400 italic">無備註</div>
              </td>
              
              <!-- 狀態 -->
              <td class="px-6 py-4 whitespace-nowrap">
                <span :class="statusClass(record.status)" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium">
                  <span :class="statusDotClass(record.status)" class="w-1.5 h-1.5 rounded-full mr-1.5"></span>
                  {{ getStatusText(record.status) }}
                </span>
              </td>
              
              <!-- 報到/收款狀態 - 新增 -->
              <td class="px-6 py-4">
                <div class="flex flex-col space-y-2">
                  <!-- 報到狀態 -->
                  <div class="flex items-center justify-center">
                    <div v-if="['checked_in', 'late'].includes(record.check_in_status)" class="flex items-center">
                      <svg class="w-4 h-4 text-green-500 mr-1" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                      </svg>
                      <span class="text-xs font-medium text-green-700">
                        {{ record.check_in_status === 'late' ? '已報到(遲到)' : '已報到' }}
                      </span>
                    </div>
                    <div v-else-if="record.check_in_status === 'no_show' || record.no_show" class="flex items-center">
                      <svg class="w-4 h-4 text-red-500 mr-1" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                      </svg>
                      <span class="text-xs font-medium text-red-700">爽約</span>
                    </div>
                    <div v-else class="flex items-center">
                      <svg class="w-4 h-4 text-gray-300 mr-1" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm0-2a6 6 0 100-12 6 6 0 000 12z" clip-rule="evenodd" />
                      </svg>
                      <span class="text-xs text-gray-400">未報到</span>
                    </div>
                  </div>
                  
                  <!-- 收款狀態 -->
                  <div class="flex items-center justify-center">
                    <div v-if="record.payment_status === 'paid'" class="flex items-center">
                      <svg class="w-4 h-4 text-green-500 mr-1" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07.34-.433.582a2.305 2.305 0 01-.567.267z" />
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.076 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.076-2.354-1.253V5z" clip-rule="evenodd" />
                      </svg>
                      <span class="text-xs font-medium text-green-700">
                        已收 ${{ formatCurrency(record.payment_amount) }}
                      </span>
                    </div>
                    <div v-else-if="record.payment_status === 'partial'" class="flex items-center">
                      <svg class="w-4 h-4 text-yellow-500 mr-1" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07.34-.433.582a2.305 2.305 0 01-.567.267z" />
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.076 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.076-2.354-1.253V5z" clip-rule="evenodd" />
                      </svg>
                      <span class="text-xs font-medium text-yellow-700">
                        部分 ${{ formatCurrency(record.payment_amount) }}
                      </span>
                    </div>
                    <div v-else-if="record.payment_amount > 0" class="flex items-center">
                      <svg class="w-4 h-4 text-blue-500 mr-1" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07.34-.433.582a2.305 2.305 0 01-.567.267z" />
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.076 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.076-2.354-1.253V5z" clip-rule="evenodd" />
                      </svg>
                      <span class="text-xs font-medium text-blue-700">
                        已付 ${{ formatCurrency(record.payment_amount) }}
                      </span>
                    </div>
                    <div v-else class="flex items-center">
                      <svg class="w-4 h-4 text-gray-300 mr-1" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm0-2a6 6 0 100-12 6 6 0 000 12z" clip-rule="evenodd" />
                      </svg>
                      <span class="text-xs text-gray-400">未付款</span>
                    </div>
                  </div>
                </div>
              </td>
              
              <!-- 操作 -->
              <td class="px-6 py-4 whitespace-nowrap text-center">
                <div class="flex items-center justify-center space-x-2">
                  <button
                    @click="viewRecord(record)"
                    class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md text-blue-700 bg-blue-100 hover:bg-blue-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors"
                  >
                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                    詳情
                  </button>
                  
                  <button
                    v-if="record.status === 'pending'"
                    @click="confirmRecord(record)"
                    class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md text-green-700 bg-green-100 hover:bg-green-200 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition-colors"
                  >
                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    確認
                  </button>
                  
                  <button
                    v-if="['pending', 'confirmed'].includes(record.status) && record.check_in_status !== 'no_show' && !record.no_show"
                    @click="cancelRecord(record)"
                    class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md text-red-700 bg-red-100 hover:bg-red-200 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition-colors"
                  >
                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                    取消
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      
      <!-- 分頁控制 -->
      <div v-if="filteredRecords.length > 0" class="bg-white px-6 py-4 border-t border-gray-200">
        <div class="flex items-center justify-between">
          <div class="text-sm text-gray-700">
            顯示第 {{ ((currentPage - 1) * itemsPerPage) + 1 }} 到 {{ Math.min(currentPage * itemsPerPage, filteredRecords.length) }} 筆，
            共 {{ filteredRecords.length }} 筆預約
          </div>
          <div class="flex items-center space-x-2">
            <button
              @click="currentPage = Math.max(1, currentPage - 1)"
              :disabled="currentPage === 1"
              class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            >
              上一頁
            </button>
            <span class="px-3 py-2 text-sm font-medium text-gray-700">
              第 {{ currentPage }} 頁，共 {{ totalPages }} 頁
            </span>
            <button
              @click="currentPage = Math.min(totalPages, currentPage + 1)"
              :disabled="currentPage === totalPages"
              class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            >
              下一頁
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- 預約詳情模態框 -->
    <div v-if="showDetailModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50" @click="closeDetailModal">
      <div class="relative top-20 mx-auto p-5 border w-full max-w-2xl bg-white rounded-xl shadow-xl" @click.stop>
        <div class="flex items-center justify-between pb-4 border-b border-gray-200">
          <h3 class="text-lg font-semibold text-gray-900">預約詳情</h3>
          <button @click="closeDetailModal" class="text-gray-400 hover:text-gray-600 transition-colors">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
        
        <div v-if="selectedRecord" class="py-4 space-y-6">
          <!-- 基本資訊 -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="space-y-4">
              <h4 class="text-sm font-semibold text-gray-900 uppercase tracking-wide">客戶資訊</h4>
              <div class="space-y-3">
                <div class="flex items-center">
                  <div class="flex-shrink-0 h-10 w-10 mr-3">
                    <div v-if="selectedRecord.customer?.line_picture_url" class="h-10 w-10 rounded-full overflow-hidden border-2 border-gray-200">
                      <img 
                        :src="selectedRecord.customer.line_picture_url" 
                        :alt="selectedRecord.customer?.line_display_name || selectedRecord.customer?.name || selectedRecord.customer_name"
                        class="h-full w-full object-cover"
                        @error="$event.target.style.display='none'; $event.target.nextElementSibling.style.display='flex'"
                      />
                      <div class="h-10 w-10 rounded-full bg-gradient-to-r from-green-500 to-blue-600 flex items-center justify-center" style="display: none;">
                        <span class="text-sm font-medium text-white">
                          {{ (selectedRecord.customer?.line_display_name || selectedRecord.customer?.name || selectedRecord.customer_name)?.charAt(0)?.toUpperCase() || 'C' }}
                        </span>
                      </div>
                    </div>
                    <div v-else class="h-10 w-10 rounded-full bg-gradient-to-r from-green-500 to-blue-600 flex items-center justify-center">
                      <span class="text-sm font-medium text-white">{{ (selectedRecord.customer?.line_display_name || selectedRecord.customer?.name || selectedRecord.customer_name)?.charAt(0)?.toUpperCase() || 'C' }}</span>
                    </div>
                  </div>
                  <div>
                    <p class="text-sm font-medium text-gray-900">
                      {{ selectedRecord.customer?.line_display_name || selectedRecord.customer?.name || selectedRecord.customer_name || '未指定' }}
                      <span v-if="selectedRecord.reservation_name && selectedRecord.reservation_name !== (selectedRecord.customer?.line_display_name || selectedRecord.customer?.name)" 
                            class="ml-1 text-xs text-gray-500">
                        ({{ selectedRecord.reservation_name }})
                      </span>
                    </p>
                    <p class="text-sm text-gray-500">預約編號：#{{ selectedRecord.id }}</p>
                  </div>
                </div>
                <div v-if="selectedRecord.customer_phone || selectedRecord.reservation_phone" class="flex items-center text-sm text-gray-600">
                  <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                  </svg>
                  {{ selectedRecord.reservation_phone || selectedRecord.customer_phone }}
                </div>
              </div>
            </div>
            
            <div class="space-y-4">
              <h4 class="text-sm font-semibold text-gray-900 uppercase tracking-wide">服務資訊</h4>
              <div class="space-y-3">
                <div class="p-3 bg-gray-50 rounded-lg">
                  <p class="text-sm font-medium text-gray-900">{{ selectedRecord.service_name || selectedRecord.item || '未指定服務' }}</p>
                  <p v-if="selectedRecord.service_price" class="text-sm text-gray-600">費用：NT$ {{ formatCurrency(selectedRecord.service_price) }}</p>
                </div>
                <div class="flex items-center text-sm text-gray-600">
                  <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                  </svg>
                  {{ formatDateTime(selectedRecord.reservation_date || selectedRecord.time, selectedRecord.reservation_time) }}
                </div>
              </div>
            </div>
          </div>
          
          <!-- 狀態資訊 -->
          <div class="space-y-4">
            <h4 class="text-sm font-semibold text-gray-900 uppercase tracking-wide">狀態資訊</h4>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <!-- 預約狀態 -->
              <div class="p-3 bg-gray-50 rounded-lg">
                <p class="text-xs text-gray-500 mb-1">預約狀態</p>
                <span :class="statusClass(selectedRecord.status)" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium">
                  <span :class="statusDotClass(selectedRecord.status)" class="w-1.5 h-1.5 rounded-full mr-1.5"></span>
                  {{ getStatusText(selectedRecord.status) }}
                </span>
              </div>
              
              <!-- 報到狀態 -->
              <div class="p-3 bg-gray-50 rounded-lg">
                <p class="text-xs text-gray-500 mb-1">報到狀態</p>
                <div v-if="['checked_in', 'late'].includes(selectedRecord.check_in_status)" class="flex items-center">
                  <svg class="w-4 h-4 text-green-500 mr-1" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                  </svg>
                  <span class="text-sm font-medium text-green-700">
                    {{ selectedRecord.check_in_status === 'late' ? '已報到(遲到)' : '已報到' }}
                  </span>
                </div>
                <div v-else-if="selectedRecord.check_in_status === 'no_show' || selectedRecord.no_show" class="flex items-center">
                  <svg class="w-4 h-4 text-red-500 mr-1" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                  </svg>
                  <span class="text-sm font-medium text-red-700">爽約</span>
                </div>
                <div v-else class="flex items-center">
                  <svg class="w-4 h-4 text-gray-400 mr-1" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm0-2a6 6 0 100-12 6 6 0 000 12z" clip-rule="evenodd" />
                  </svg>
                  <span class="text-sm text-gray-500">未報到</span>
                </div>
                <p v-if="selectedRecord.check_in_time" class="text-xs text-gray-500 mt-1">
                  報到時間：{{ selectedRecord.check_in_time }}
                </p>
              </div>
              
              <!-- 收款狀態 -->
              <div class="p-3 bg-gray-50 rounded-lg">
                <p class="text-xs text-gray-500 mb-1">收款狀態</p>
                <div v-if="selectedRecord.payment_status === 'paid'" class="flex items-center">
                  <svg class="w-4 h-4 text-green-500 mr-1" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07.34-.433.582a2.305 2.305 0 01-.567.267z" />
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.076 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.076-2.354-1.253V5z" clip-rule="evenodd" />
                  </svg>
                  <span class="text-sm font-medium text-green-700">已付清</span>
                </div>
                <div v-else-if="selectedRecord.payment_status === 'partial'" class="flex items-center">
                  <svg class="w-4 h-4 text-yellow-500 mr-1" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07.34-.433.582a2.305 2.305 0 01-.567.267z" />
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.076 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.076-2.354-1.253V5z" clip-rule="evenodd" />
                  </svg>
                  <span class="text-sm font-medium text-yellow-700">部分付款</span>
                </div>
                <div v-else class="flex items-center">
                  <svg class="w-4 h-4 text-gray-400 mr-1" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm0-2a6 6 0 100-12 6 6 0 000 12z" clip-rule="evenodd" />
                  </svg>
                  <span class="text-sm text-gray-500">未付款</span>
                </div>
                <p v-if="selectedRecord.payment_amount > 0" class="text-xs text-gray-600 mt-1">
                  已付：NT$ {{ formatCurrency(selectedRecord.payment_amount) }}
                </p>
                <p v-if="selectedRecord.service_price" class="text-xs text-gray-500 mt-1">
                  總額：NT$ {{ formatCurrency(selectedRecord.service_price) }}
                </p>
                <p v-if="selectedRecord.payment_time" class="text-xs text-blue-600 mt-1">
                  <svg class="w-3 h-3 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                  {{ formatPaymentTime(selectedRecord.payment_time) }}
                </p>
              </div>
            </div>
          </div>
          
          <!-- 備註資訊 -->
          <div v-if="selectedRecord.reservation_notes" class="space-y-4">
            <h4 class="text-sm font-semibold text-gray-900 uppercase tracking-wide">預約備註</h4>
            
            <!-- 預約備註 -->
            <div class="p-4 bg-green-50 border border-green-200 rounded-lg">
              <div class="flex items-center mb-2">
                <svg class="w-4 h-4 text-green-600 mr-2" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                </svg>
                <span class="text-sm font-medium text-green-800">備註內容</span>
              </div>
              <p class="text-sm text-green-800">{{ selectedRecord.reservation_notes }}</p>
            </div>
          </div>
        </div>
        
        <div class="flex justify-end space-x-3 pt-4 border-t border-gray-200">
          <button
            @click="closeDetailModal"
            class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 border border-gray-300 rounded-md hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition-colors"
          >
            關閉
          </button>
          
          <button
            v-if="selectedRecord?.status === 'pending'"
            @click="confirmRecord(selectedRecord); closeDetailModal()"
            class="px-4 py-2 text-sm font-medium text-white bg-green-600 border border-transparent rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition-colors"
          >
            確認預約
          </button>
          
          <button
            v-if="['pending', 'confirmed'].includes(selectedRecord?.status) && selectedRecord?.check_in_status !== 'no_show' && !selectedRecord?.no_show"
            @click="cancelRecord(selectedRecord); closeDetailModal()"
            class="px-4 py-2 text-sm font-medium text-white bg-red-600 border border-transparent rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition-colors"
          >
            取消預約
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { apiGet, apiPut } from '../utils/api.js'

// 響應式數據
const search = ref('')
const statusFilter = ref('')
const dateFilter = ref('')
const records = ref([])
const reservations = computed(() => records.value)
const loading = ref(false)
const error = ref('')
const currentPage = ref(1)

// 自動刷新相關
const autoRefreshEnabled = ref(true)
const autoRefreshInterval = ref(null)

// 啟動自動刷新
function startAutoRefresh() {
  if (autoRefreshInterval.value) {
    clearInterval(autoRefreshInterval.value)
  }
  
  
  autoRefreshInterval.value = setInterval(() => {
    if (autoRefreshEnabled.value && !loading.value) {
      fetchReservations()
    }
  }, 5000) // 每5秒刷新一次，確保即時更新
}

// 停止自動刷新
function stopAutoRefresh() {
  if (autoRefreshInterval.value) {
    clearInterval(autoRefreshInterval.value)
    autoRefreshInterval.value = null
  }
}

// 切換自動刷新
function toggleAutoRefresh() {
  autoRefreshEnabled.value = !autoRefreshEnabled.value
  
  if (autoRefreshEnabled.value) {
    startAutoRefresh()
  } else {
    stopAutoRefresh()
  }
}
const itemsPerPage = ref(20)
const showDetailModal = ref(false)
const selectedRecord = ref(null)

// 統計數據
const todayReservations = computed(() => {
  const today = new Date().toLocaleDateString('zh-TW', { timeZone: 'Asia/Taipei' }) // 本地日期
  return records.value.filter(record => {
    const recordDateStr = record.reservation_date || record.time
    if (!recordDateStr) return false
    const recordDate = new Date(recordDateStr)
    const recordDay = recordDate.toLocaleDateString('zh-TW', { timeZone: 'Asia/Taipei' })
    return recordDay === today
  }).length
})


const pendingReservations = computed(() => {
  return records.value.filter(record => record.status === 'pending').length
})

const confirmedReservations = computed(() => {
  return records.value.filter(record => record.status === 'confirmed').length
})

// 新增：已完成預約統計
const completedReservations = computed(() => {
  return records.value.filter(record => record.status === 'completed').length
})

// 新增：爽約統計
const noShowReservations = computed(() => {
  return records.value.filter(record => record.check_in_status === 'no_show' || record.no_show).length
})

// 篩選邏輯
const filteredRecords = computed(() => {
  let filtered = records.value

  // 搜尋篩選
  if (search.value.trim()) {
    const keyword = search.value.toLowerCase()
    filtered = filtered.filter(
      (r) =>
        (r.customer_name || r.name || '').toLowerCase().includes(keyword) ||
        (r.reservation_name || '').toLowerCase().includes(keyword) ||
        (r.service_name || r.item || '').toLowerCase().includes(keyword) ||
        getStatusText(r.status).toLowerCase().includes(keyword) ||
        (r.customer_phone || r.phone || '').toLowerCase().includes(keyword) ||
        (r.reservation_phone || '').toLowerCase().includes(keyword) ||
        (r.reservation_notes || '').toLowerCase().includes(keyword)
    )
  }

  // 狀態篩選
  if (statusFilter.value) {
    filtered = filtered.filter(record => record.status === statusFilter.value)
  }

  // 日期篩選
  if (dateFilter.value) {
    const now = new Date()
    const today = new Date(now.getFullYear(), now.getMonth(), now.getDate())
    
    filtered = filtered.filter(record => {
      const recordDate = new Date(record.reservation_date || record.time)
      
      switch (dateFilter.value) {
        case 'today':
          return recordDate.toDateString() === today.toDateString()
        case 'week':
          const weekStart = new Date(today)
          weekStart.setDate(today.getDate() - today.getDay())
          const weekEnd = new Date(weekStart)
          weekEnd.setDate(weekStart.getDate() + 6)
          return recordDate >= weekStart && recordDate <= weekEnd
        case 'month':
          return recordDate.getMonth() === today.getMonth() && 
                 recordDate.getFullYear() === today.getFullYear()
        default:
          return true
      }
    })
  }

return filtered.sort((a, b) => {
    const now = new Date()
    const dateA = new Date(a.reservation_date || a.time)
    const dateB = new Date(b.reservation_date || b.time)

    // pending 最優先
    if (a.status === 'pending' && b.status !== 'pending') return -1
    if (b.status === 'pending' && a.status !== 'pending') return 1

    // 都不是 pending，接下來比時間
    const aFuture = dateA > now
    const bFuture = dateB > now

    // 未來的預約排前面，按時間升序
    if (aFuture && bFuture) return dateA - dateB
    // 過期的預約排後面，按時間降序
    if (!aFuture && !bFuture) return dateB - dateA
    // 一個未來一個過期 → 未來排前
    if (aFuture && !bFuture) return -1
    if (!aFuture && bFuture) return 1

    return 0
  })

})

// 分頁邏輯
const totalPages = computed(() => Math.ceil(filteredRecords.value.length / itemsPerPage.value))

// 獲取預約列表
async function fetchReservations() {
  loading.value = true
  error.value = ''
  
  try {
    // 添加時間戳參數防止快取
    const timestamp = Date.now()
    const data = await apiGet(`/reservations?_t=${timestamp}`)
    
    const newData = data.data || data
    records.value = newData
    
  } catch (err) {
    error.value = err.message || '載入預約資料失敗'
  } finally {
    loading.value = false
  }
}

// 確認預約
async function confirmRecord(record) {
  if (!confirm(`確定要確認 ${record.customer_name || record.name} 的預約嗎？`)) return
  
  try {
    loading.value = true
    await apiPut(`/reservations/${record.id}/confirm`)
    await fetchReservations()
    
    // 顯示成功通知
    showNotification('預約已確認', 'success')
    
  } catch (err) {
    showNotification(`確認失敗: ${err.message}`, 'error')
  } finally {
    loading.value = false
  }
}

// 取消預約
async function cancelRecord(record) {
  if (!confirm(`確定要取消 ${record.customer_name || record.name} 的預約嗎？`)) return
  
  try {
    loading.value = true
    await apiPut(`/reservations/${record.id}/cancel`)
    await fetchReservations()
    
    // 顯示成功通知
    showNotification('預約已取消', 'success')
    
  } catch (err) {
    showNotification(`取消失敗: ${err.message}`, 'error')
  } finally {
    loading.value = false
  }
}

// 狀態樣式
const statusClass = (status) => {
  switch (status) {
    case 'pending':
      return 'bg-yellow-100 text-yellow-800 border border-yellow-200'
    case 'confirmed':
      return 'bg-blue-100 text-blue-800 border border-blue-200'
    case 'completed':
      return 'bg-green-100 text-green-800 border border-green-200'
    case 'cancelled':
      return 'bg-red-100 text-red-800 border border-red-200'
    default:
      return 'bg-gray-100 text-gray-800 border border-gray-200'
  }
}

const statusDotClass = (status) => {
  switch (status) {
    case 'pending':
      return 'bg-yellow-400'
    case 'confirmed':
      return 'bg-blue-400'
    case 'completed':
      return 'bg-green-400'
    case 'cancelled':
      return 'bg-red-400'
    default:
      return 'bg-gray-400'
  }
}

const getStatusText = (status) => {
  switch (status) {
    case 'pending':
      return '待確認'
    case 'confirmed':
      return '已確認'
    case 'completed':
      return '已完成'
    case 'cancelled':
      return '已取消'
    default:
      return status
  }
}

// 格式化日期時間
const formatDateTime = (date, time) => {
  if (!date) return '未設定'
  
  try {
    const dateObj = new Date(date)
    const formattedDate = dateObj.toLocaleDateString('zh-TW', {
      year: 'numeric',
      month: '2-digit',
      day: '2-digit'
    })
    
    if (time) {
      return `${formattedDate} ${time}`
    }
    return formattedDate
  } catch (err) {
    return date + (time ? ` ${time}` : '')
  }
}

// 格式化金額
const formatCurrency = (amount) => {
  if (!amount) return '0'
  return new Intl.NumberFormat('zh-TW').format(amount)
}

// 格式化收款時間
const formatPaymentTime = (paymentTime) => {
  if (!paymentTime) return ''
  
  try {
    const dateObj = new Date(paymentTime)
    const formattedTime = dateObj.toLocaleString('zh-TW', {
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit',
      hour12: false
    })
    return `收款時間：${formattedTime}`
  } catch (err) {
    return `收款時間：${paymentTime}`
  }
}

// 日期判斷
const isToday = (date) => {
  if (!date) return false
  const today = new Date().toDateString()
  const recordDate = new Date(date).toDateString()
  return today === recordDate
}

const isTomorrow = (date) => {
  if (!date) return false
  const tomorrow = new Date()
  tomorrow.setDate(tomorrow.getDate() + 1)
  const recordDate = new Date(date).toDateString()
  return tomorrow.toDateString() === recordDate
}

// 查看詳情
function viewRecord(record) {
  selectedRecord.value = record
  showDetailModal.value = true
}

// 關閉詳情模態框
function closeDetailModal() {
  showDetailModal.value = false
  selectedRecord.value = null
}

// 清除篩選條件
function clearFilters() {
  search.value = ''
  statusFilter.value = ''
  dateFilter.value = ''
  currentPage.value = 1
}

// 通知功能（簡化版）
function showNotification(message, type = 'info') {
  // 在實際應用中，這裡可以整合一個更完善的通知系統
  if (type === 'error') {
    alert(`錯誤：${message}`)
  } else {
    alert(message)
  }
}

// 頁面載入時獲取預約列表
onMounted(() => {
  fetchReservations()
  startAutoRefresh()
})

// 組件卸載時清理定時器
onUnmounted(() => {
  stopAutoRefresh()
})
</script>

<style scoped>
/* 額外的動畫與過渡效果 */
@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.fade-in {
  animation: fadeIn 0.3s ease-out;
}

/* 模態框動畫 */
.modal-enter-active,
.modal-leave-active {
  transition: opacity 0.3s ease;
}

.modal-enter-from,
.modal-leave-to {
  opacity: 0;
}

/* 表格行hover效果增強 */
tbody tr:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

/* 按鈕組hover效果 */
.space-x-2 button:hover {
  transform: translateY(-1px);
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

/* 統計卡片hover效果 */
.hover\:shadow-md:hover {
  transform: translateY(-2px);
}

/* 自訂滾動條樣式 */
.overflow-x-auto::-webkit-scrollbar {
  height: 8px;
}

.overflow-x-auto::-webkit-scrollbar-track {
  background: #f1f5f9;
  border-radius: 4px;
}

.overflow-x-auto::-webkit-scrollbar-thumb {
  background: #cbd5e1;
  border-radius: 4px;
}

.overflow-x-auto::-webkit-scrollbar-thumb:hover {
  background: #94a3b8;
}

/* 響應式表格優化 */
@media (max-width: 768px) {
  .min-w-full {
    font-size: 0.875rem;
  }
  
  .px-6 {
    padding-left: 1rem;
    padding-right: 1rem;
  }
  
  .py-4 {
    padding-top: 0.75rem;
    padding-bottom: 0.75rem;
  }
  
  /* 在小螢幕上隱藏備註列 */
  .notes-column {
    display: none;
  }
}

@media (min-width: 1024px) {
  .notes-column {
    display: table-cell;
  }
}

/* 狀態徽章動畫 */
.rounded-full {
  transition: all 0.2s ease;
}

.rounded-full:hover {
  transform: scale(1.05);
}

/* 搜尋框焦點效果 */
input:focus {
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

select:focus {
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}
</style>
