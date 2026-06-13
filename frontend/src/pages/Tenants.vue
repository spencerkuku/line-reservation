<template>
  <div class="min-h-screen bg-gray-50 pt-4 px-4 sm:px-6 lg:px-8 pb-6">
    <!-- 統計卡片 -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6 mt-2">
      <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="flex items-center">
          <div class="p-3 bg-blue-100 rounded-lg">
            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
            </svg>
          </div>
          <div class="ml-4">
            <p class="text-sm text-gray-500">總租戶數</p>
            <p class="text-2xl font-bold text-gray-900">{{ stats.total || 0 }}</p>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="flex items-center">
          <div class="p-3 bg-green-100 rounded-lg">
            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <div class="ml-4">
            <p class="text-sm text-gray-500">活躍租戶</p>
            <p class="text-2xl font-bold text-green-600">{{ stats.active || 0 }}</p>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="flex items-center">
          <div class="p-3 bg-yellow-100 rounded-lg">
            <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <div class="ml-4">
            <p class="text-sm text-gray-500">試用中</p>
            <p class="text-2xl font-bold text-yellow-600">{{ stats.trial || 0 }}</p>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="flex items-center">
          <div class="p-3 bg-red-100 rounded-lg">
            <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
          </div>
          <div class="ml-4">
            <p class="text-sm text-gray-500">即將到期</p>
            <p class="text-2xl font-bold text-red-600">{{ (stats.expiring_soon || 0) + (stats.trial_expiring_soon || 0) }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- 操作欄 -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div class="flex items-center space-x-4">
          <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
              <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
              </svg>
            </div>
            <input
              v-model="search"
              type="text"
              placeholder="搜尋租戶..."
              class="w-full sm:w-80 pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              @input="debouncedSearch"
            />
          </div>
          <select
            v-model="statusFilter"
            class="border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500"
            @change="fetchTenants"
          >
            <option value="">全部狀態</option>
            <option value="active">活躍</option>
            <option value="trial">試用</option>
            <option value="suspended">暫停</option>
            <option value="inactive">停用</option>
          </select>
        </div>
        <button
          @click="showAddModal = true"
          class="inline-flex items-center px-4 py-2.5 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors"
        >
          <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
          </svg>
          新增租戶
        </button>
      </div>
    </div>

    <!-- 租戶列表 -->
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

      <!-- 錯誤狀態 -->
      <div v-else-if="error" class="p-12 text-center">
        <div class="inline-flex items-center justify-center w-16 h-16 bg-red-100 rounded-full mb-4">
          <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.732 15.5c-.77.833.192 2.5 1.732 2.5z" />
          </svg>
        </div>
        <p class="text-red-600 font-medium mb-2">{{ error }}</p>
        <button @click="fetchTenants" class="text-blue-600 hover:text-blue-800 font-medium">重新載入</button>
      </div>

      <!-- 空狀態 -->
      <div v-else-if="tenants.length === 0" class="p-12 text-center">
        <div class="inline-flex items-center justify-center w-16 h-16 bg-gray-100 rounded-full mb-4">
          <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
          </svg>
        </div>
        <p class="text-gray-600 font-medium">尚無租戶</p>
        <p class="text-gray-500 text-sm mt-1">點擊「新增租戶」開始建立</p>
      </div>

      <!-- 表格內容 -->
      <div v-else class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">租戶資訊</th>
              <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">聯絡方式</th>
              <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">方案</th>
              <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">狀態</th>
              <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">統計</th>
              <th class="px-6 py-4 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">操作</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr v-for="tenant in tenants" :key="tenant.id" class="hover:bg-gray-50 transition-colors">
              <!-- 租戶資訊 -->
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex items-center">
                  <div class="flex-shrink-0 h-10 w-10">
                    <div class="h-10 w-10 rounded-lg bg-gradient-to-r from-indigo-400 to-indigo-600 flex items-center justify-center">
                      <span class="text-sm font-medium text-white">{{ tenant.name?.charAt(0) || 'T' }}</span>
                    </div>
                  </div>
                  <div class="ml-4">
                    <div class="text-sm font-medium text-gray-900">{{ tenant.name }}</div>
                    <div class="text-sm text-gray-500">{{ tenant.slug }}</div>
                  </div>
                </div>
              </td>
              <!-- 聯絡方式 -->
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-gray-900">{{ tenant.email }}</div>
                <div class="text-sm text-gray-500">{{ tenant.phone || '-' }}</div>
              </td>
              <!-- 方案 -->
              <td class="px-6 py-4 whitespace-nowrap">
                <span :class="planClass(tenant.plan)" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium">
                  {{ planText(tenant.plan) }}
                </span>
              </td>
              <!-- 狀態 -->
              <td class="px-6 py-4 whitespace-nowrap">
                <span :class="statusClass(tenant.status)" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium">
                  {{ statusText(tenant.status) }}
                </span>
                <div v-if="tenant.status === 'trial' && tenant.trial_ends_at" class="text-xs text-gray-500 mt-1">
                  試用至 {{ formatDate(tenant.trial_ends_at) }}
                </div>
                <div v-if="tenant.status === 'active' && tenant.subscription_ends_at" class="text-xs text-gray-500 mt-1">
                  到期日 {{ formatDate(tenant.subscription_ends_at) }}
                </div>
              </td>
              <!-- 統計 -->
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-gray-900">{{ tenant.customers_count || 0 }} 客戶</div>
                <div class="text-sm text-gray-500">{{ tenant.reservations_count || 0 }} 預約</div>
              </td>
              <!-- 操作 -->
              <td class="px-6 py-4 whitespace-nowrap text-center">
                <div class="flex items-center justify-center space-x-2">
                  <button
                    @click="editTenant(tenant)"
                    class="inline-flex items-center px-3 py-1.5 bg-blue-100 text-blue-700 text-xs font-medium rounded-md hover:bg-blue-200 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors"
                  >
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                    編輯
                  </button>
                  <button
                    @click="showTenantDetails(tenant)"
                    class="inline-flex items-center px-3 py-1.5 bg-gray-100 text-gray-700 text-xs font-medium rounded-md hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-500 transition-colors"
                  >
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                    詳情
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- 分頁 -->
      <div v-if="pagination.last_page > 1" class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
        <div class="flex-1 flex justify-between sm:hidden">
          <button
            @click="changePage(pagination.current_page - 1)"
            :disabled="pagination.current_page === 1"
            class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50"
          >
            上一頁
          </button>
          <button
            @click="changePage(pagination.current_page + 1)"
            :disabled="pagination.current_page === pagination.last_page"
            class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50"
          >
            下一頁
          </button>
        </div>
        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
          <div>
            <p class="text-sm text-gray-700">
              顯示第 <span class="font-medium">{{ pagination.from }}</span> 至 <span class="font-medium">{{ pagination.to }}</span> 筆，共 <span class="font-medium">{{ pagination.total }}</span> 筆
            </p>
          </div>
          <div>
            <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
              <button
                v-for="page in visiblePages"
                :key="page"
                @click="changePage(page)"
                :class="[
                  page === pagination.current_page
                    ? 'z-10 bg-blue-50 border-blue-500 text-blue-600'
                    : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50',
                  'relative inline-flex items-center px-4 py-2 border text-sm font-medium'
                ]"
              >
                {{ page }}
              </button>
            </nav>
          </div>
        </div>
      </div>
    </div>

    <!-- 新增/編輯租戶 Modal -->
    <div v-if="showAddModal || showEditModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 flex items-center justify-center">
      <div class="relative mx-auto p-6 w-full max-w-2xl bg-white rounded-xl shadow-xl">
        <div class="flex items-center justify-between mb-6">
          <h3 class="text-xl font-bold text-gray-900">{{ showEditModal ? '編輯租戶' : '新增租戶' }}</h3>
          <button @click="closeModal" class="text-gray-400 hover:text-gray-600">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <form @submit.prevent="submitForm" class="space-y-4">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- 公司名稱 -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">公司名稱 *</label>
              <input
                v-model="form.name"
                type="text"
                required
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                placeholder="輸入公司名稱"
              />
            </div>
            <!-- Email -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
              <input
                v-model="form.email"
                type="email"
                required
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                placeholder="admin@company.com"
              />
            </div>
            <!-- 電話 -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">聯絡電話</label>
              <input
                v-model="form.phone"
                type="text"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                placeholder="02-1234-5678"
              />
            </div>
            <!-- 方案 -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">訂閱方案</label>
              <select
                v-model="form.plan"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              >
                <option value="basic">基本版</option>
                <option value="standard">標準版</option>
                <option value="premium">專業版</option>
              </select>
            </div>
            <!-- 狀態（僅編輯時顯示）-->
            <div v-if="showEditModal">
              <label class="block text-sm font-medium text-gray-700 mb-1">狀態</label>
              <select
                v-model="form.status"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              >
                <option value="active">活躍</option>
                <option value="trial">試用</option>
                <option value="suspended">暫停</option>
                <option value="inactive">停用</option>
              </select>
            </div>
          </div>

          <!-- 訂閱設定 -->
          <div class="border-t pt-4 mt-4">
            <h4 class="text-sm font-semibold text-gray-700 mb-3">訂閱設定</h4>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <!-- 訂閱類型 -->
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">訂閱類型</label>
                <select
                  v-model="form.subscription_type"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                  @change="handleSubscriptionTypeChange"
                >
                  <option value="trial">試用期</option>
                  <option value="subscription">正式訂閱</option>
                </select>
              </div>
              <!-- 試用到期日 -->
              <div v-if="form.subscription_type === 'trial'">
                <label class="block text-sm font-medium text-gray-700 mb-1">試用到期日</label>
                <input
                  v-model="form.trial_ends_at"
                  type="date"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                />
                <p class="text-xs text-gray-500 mt-1">試用期結束後，Webhook 將停止運作</p>
              </div>
              <!-- 訂閱到期日 -->
              <div v-else>
                <label class="block text-sm font-medium text-gray-700 mb-1">訂閱到期日</label>
                <input
                  v-model="form.subscription_ends_at"
                  type="date"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                />
                <p class="text-xs text-gray-500 mt-1">訂閱到期後，Webhook 將停止運作</p>
              </div>
            </div>
          </div>

          <!-- 地址 -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">公司地址</label>
            <input
              v-model="form.address"
              type="text"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              placeholder="台北市信義區..."
            />
          </div>

          <!-- 按鈕 -->
          <div class="flex justify-end space-x-3 pt-4 border-t">
            <button
              type="button"
              @click="closeModal"
              class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors"
            >
              取消
            </button>
            <button
              type="submit"
              :disabled="submitting"
              class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 transition-colors"
            >
              {{ submitting ? '處理中...' : (showEditModal ? '更新' : '建立') }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- 租戶詳情 Modal -->
    <div v-if="showDetailsModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 flex items-center justify-center">
      <div class="relative mx-auto p-6 w-full max-w-2xl bg-white rounded-xl shadow-xl">
        <div class="flex items-center justify-between mb-6">
          <h3 class="text-xl font-bold text-gray-900">租戶詳情</h3>
          <button @click="showDetailsModal = false" class="text-gray-400 hover:text-gray-600">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <div v-if="selectedTenant" class="space-y-6">
          <!-- 基本資訊 -->
          <div>
            <h4 class="text-sm font-semibold text-gray-700 mb-3">基本資訊</h4>
            <div class="grid grid-cols-2 gap-4 bg-gray-50 p-4 rounded-lg">
              <div>
                <p class="text-sm text-gray-500">公司名稱</p>
                <p class="font-medium">{{ selectedTenant.name }}</p>
              </div>
              <div>
                <p class="text-sm text-gray-500">識別碼</p>
                <p class="font-medium font-mono">{{ selectedTenant.slug }}</p>
              </div>
              <div>
                <p class="text-sm text-gray-500">Email</p>
                <p class="font-medium">{{ selectedTenant.email }}</p>
              </div>
              <div>
                <p class="text-sm text-gray-500">電話</p>
                <p class="font-medium">{{ selectedTenant.phone || '-' }}</p>
              </div>
            </div>
          </div>

          <!-- Webhook URL -->
          <div>
            <h4 class="text-sm font-semibold text-gray-700 mb-3">LINE Webhook URL</h4>
            <div class="bg-blue-50 p-4 rounded-lg">
              <div class="flex items-center justify-between">
                <code class="text-sm text-blue-800 break-all">{{ selectedTenant.full_webhook_url }}</code>
                <button
                  @click="copyWebhookUrl"
                  class="ml-2 p-2 text-blue-600 hover:bg-blue-100 rounded-lg transition-colors"
                >
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" />
                  </svg>
                </button>
              </div>
              <p class="text-xs text-blue-600 mt-2">請將此 URL 設定到 LINE Developers Console 的 Webhook URL</p>
            </div>
          </div>

          <!-- 訂閱資訊 -->
          <div>
            <h4 class="text-sm font-semibold text-gray-700 mb-3">訂閱資訊</h4>
            <div class="grid grid-cols-2 gap-4 bg-gray-50 p-4 rounded-lg">
              <div>
                <p class="text-sm text-gray-500">方案</p>
                <span :class="planClass(selectedTenant.plan)" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium">
                  {{ planText(selectedTenant.plan) }}
                </span>
              </div>
              <div>
                <p class="text-sm text-gray-500">狀態</p>
                <span :class="statusClass(selectedTenant.status)" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium">
                  {{ statusText(selectedTenant.status) }}
                </span>
              </div>
              <div v-if="selectedTenant.trial_ends_at">
                <p class="text-sm text-gray-500">試用期結束</p>
                <p class="font-medium">{{ formatDate(selectedTenant.trial_ends_at) }}</p>
              </div>
              <div v-if="selectedTenant.subscription_ends_at">
                <p class="text-sm text-gray-500">訂閱到期日</p>
                <p class="font-medium">{{ formatDate(selectedTenant.subscription_ends_at) }}</p>
              </div>
            </div>
          </div>

          <!-- 操作按鈕 -->
          <div class="flex justify-between items-center pt-4 border-t">
            <button
              @click="resetPassword(selectedTenant)"
              class="px-4 py-2 text-orange-600 border border-orange-300 rounded-lg hover:bg-orange-50 transition-colors"
            >
              重設密碼
            </button>
            <div class="flex space-x-3">
              <button
                @click="editTenant(selectedTenant); showDetailsModal = false"
                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
              >
                編輯
              </button>
              <button
                @click="showDetailsModal = false"
                class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors"
              >
                關閉
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- 新建租戶成功 Modal -->
    <div v-if="showCredentialsModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 flex items-center justify-center">
      <div class="relative mx-auto p-6 w-full max-w-md bg-white rounded-xl shadow-xl">
        <div class="text-center mb-6">
          <div class="inline-flex items-center justify-center w-16 h-16 bg-green-100 rounded-full mb-4">
            <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <h3 class="text-xl font-bold text-gray-900">租戶建立成功</h3>
          <p class="text-gray-600 mt-2">請將以下登入資訊提供給租戶管理員</p>
        </div>

        <div class="bg-gray-50 p-4 rounded-lg space-y-3">
          <div>
            <p class="text-sm text-gray-500">登入信箱</p>
            <p class="font-mono font-medium">{{ newTenantCredentials.email }}</p>
          </div>
          <div>
            <p class="text-sm text-gray-500">臨時密碼</p>
            <div class="flex items-center space-x-2">
              <p class="font-mono font-medium text-blue-600">{{ newTenantCredentials.password }}</p>
              <button
                @click="copyPassword"
                class="p-1 text-gray-500 hover:text-gray-700"
              >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" />
                </svg>
              </button>
            </div>
          </div>
          <div>
            <p class="text-sm text-gray-500">Webhook URL</p>
            <p class="font-mono text-sm break-all">{{ newTenantCredentials.webhook_url }}</p>
          </div>
        </div>

        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 mt-4">
          <p class="text-sm text-yellow-800">
            <strong>注意：</strong>租戶首次登入時需要修改密碼
          </p>
        </div>

        <div class="flex justify-center mt-6">
          <button
            @click="showCredentialsModal = false"
            class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
          >
            知道了
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { apiGet, apiPost, apiPut } from '../utils/api.js'

// 狀態
const loading = ref(false)
const error = ref(null)
const tenants = ref([])
const stats = ref({})
const search = ref('')
const statusFilter = ref('')
const pagination = ref({})

// Modal 狀態
const showAddModal = ref(false)
const showEditModal = ref(false)
const showDetailsModal = ref(false)
const showCredentialsModal = ref(false)
const submitting = ref(false)
const selectedTenant = ref(null)
const newTenantCredentials = ref({})

// 表單
const form = reactive({
  name: '',
  email: '',
  phone: '',
  address: '',
  plan: 'basic',
  status: 'trial',
  subscription_type: 'trial', // 'trial' 或 'subscription'
  trial_ends_at: '',
  subscription_ends_at: ''
})

// 計算可見頁碼
const visiblePages = computed(() => {
  const pages = []
  const current = pagination.value.current_page || 1
  const last = pagination.value.last_page || 1
  
  for (let i = Math.max(1, current - 2); i <= Math.min(last, current + 2); i++) {
    pages.push(i)
  }
  return pages
})

// 載入租戶列表
const fetchTenants = async (page = 1) => {
  loading.value = true
  error.value = null
  
  try {
    const params = { page }
    if (search.value) params.search = search.value
    if (statusFilter.value) params.status = statusFilter.value
    
    const response = await apiGet('/system/tenants', params)
    tenants.value = response.data.data
    pagination.value = {
      current_page: response.data.current_page,
      last_page: response.data.last_page,
      from: response.data.from,
      to: response.data.to,
      total: response.data.total
    }
  } catch (err) {
    error.value = err.response?.data?.message || '載入失敗'
  } finally {
    loading.value = false
  }
}

// 載入統計
const fetchStats = async () => {
  try {
    const response = await apiGet('/system/tenants/statistics')
    stats.value = response.data
  } catch (err) {
    console.error('載入統計失敗:', err)
  }
}

// 搜尋防抖
let searchTimeout = null
const debouncedSearch = () => {
  if (searchTimeout) clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    fetchTenants()
  }, 300)
}

// 換頁
const changePage = (page) => {
  fetchTenants(page)
}

// 編輯租戶
const editTenant = (tenant) => {
  selectedTenant.value = tenant
  
  // 判斷訂閱類型
  const subscriptionType = tenant.subscription_ends_at ? 'subscription' : 'trial'
  
  Object.assign(form, {
    name: tenant.name,
    email: tenant.email,
    phone: tenant.phone || '',
    address: tenant.address || '',
    plan: tenant.plan,
    status: tenant.status,
    subscription_type: subscriptionType,
    trial_ends_at: tenant.trial_ends_at ? tenant.trial_ends_at.split('T')[0] : '',
    subscription_ends_at: tenant.subscription_ends_at ? tenant.subscription_ends_at.split('T')[0] : ''
  })
  showEditModal.value = true
}

// 顯示詳情
const showTenantDetails = async (tenant) => {
  try {
    const response = await apiGet(`/system/tenants/${tenant.id}`)
    selectedTenant.value = response.data.tenant
    showDetailsModal.value = true
  } catch (err) {
    alert(err.response?.data?.message || '載入詳情失敗')
  }
}

// 關閉 Modal
const closeModal = () => {
  showAddModal.value = false
  showEditModal.value = false
  resetForm()
}

// 重置表單
const resetForm = () => {
  // 預設試用到期日為 14 天後
  const defaultTrialEnd = new Date()
  defaultTrialEnd.setDate(defaultTrialEnd.getDate() + 14)
  
  Object.assign(form, {
    name: '',
    email: '',
    phone: '',
    address: '',
    plan: 'basic',
    status: 'trial',
    subscription_type: 'trial',
    trial_ends_at: defaultTrialEnd.toISOString().split('T')[0],
    subscription_ends_at: ''
  })
  selectedTenant.value = null
}

// 提交表單
const submitForm = async () => {
  submitting.value = true
  
  try {
    if (showEditModal.value) {
      // 更新
      await apiPut(`/system/tenants/${selectedTenant.value.id}`, form)
      alert('租戶更新成功')
    } else {
      // 新增
      const response = await apiPost('/system/tenants', form)
      newTenantCredentials.value = {
        email: response.data.admin_user.email,
        password: response.data.admin_user.temporary_password,
        webhook_url: response.data.webhook_url
      }
      showCredentialsModal.value = true
    }
    
    closeModal()
    fetchTenants()
    fetchStats()
  } catch (err) {
    alert(err.response?.data?.message || '操作失敗')
  } finally {
    submitting.value = false
  }
}

// 重設密碼
const resetPassword = async (tenant) => {
  if (!confirm(`確定要重設 ${tenant.name} 的管理員密碼嗎？`)) return
  
  try {
    const response = await apiPost(`/system/tenants/${tenant.id}/reset-password`, {})
    newTenantCredentials.value = {
      email: response.data.email,
      password: response.data.temporary_password,
      webhook_url: tenant.full_webhook_url
    }
    showDetailsModal.value = false
    showCredentialsModal.value = true
  } catch (err) {
    alert(err.response?.data?.message || '重設密碼失敗')
  }
}

// 通用複製函數（包含降級方案）
const copyToClipboard = async (text, successMsg = '已複製到剪貼簿') => {
  try {
    // 方法 1: 現代 Clipboard API (需要 HTTPS)
    if (navigator.clipboard && window.isSecureContext) {
      await navigator.clipboard.writeText(text)
      alert(successMsg)
      return true
    }
    
    // 方法 2: 降級方案 - execCommand (適用於 HTTP)
    const textArea = document.createElement('textarea')
    textArea.value = text
    textArea.style.position = 'fixed'
    textArea.style.left = '-999999px'
    textArea.style.top = '-999999px'
    document.body.appendChild(textArea)
    textArea.focus()
    textArea.select()
    
    const successful = document.execCommand('copy')
    textArea.remove()
    
    if (successful) {
      alert(successMsg)
      return true
    } else {
      throw new Error('execCommand 複製失敗')
    }
  } catch (err) {
    console.error('複製失敗:', err)
    alert(`複製失敗: ${err.message}\n請手動複製`)
    return false
  }
}

// 複製 Webhook URL
const copyWebhookUrl = () => {
  copyToClipboard(
    selectedTenant.value.full_webhook_url,
    'Webhook URL 已複製到剪貼簿'
  )
}

// 處理訂閱類型變更
const handleSubscriptionTypeChange = () => {
  if (form.subscription_type === 'trial') {
    // 切換到試用：自動設定狀態為 trial，清空訂閱日期
    form.status = 'trial'
    form.subscription_ends_at = ''
    // 如果沒有試用到期日，預設 14 天後
    if (!form.trial_ends_at) {
      const date = new Date()
      date.setDate(date.getDate() + 14)
      form.trial_ends_at = date.toISOString().split('T')[0]
    }
  } else {
    // 切換到正式訂閱：自動設定狀態為 active，清空試用日期
    form.status = 'active'
    form.trial_ends_at = ''
    // 如果沒有訂閱到期日，預設 1 年後
    if (!form.subscription_ends_at) {
      const date = new Date()
      date.setFullYear(date.getFullYear() + 1)
      form.subscription_ends_at = date.toISOString().split('T')[0]
    }
  }
}

// 複製密碼
const copyPassword = () => {
  copyToClipboard(
    newTenantCredentials.value.password,
    '密碼已複製到剪貼簿'
  )
}

// 格式化日期
const formatDate = (dateStr) => {
  if (!dateStr) return '-'
  return new Date(dateStr).toLocaleDateString('zh-TW')
}

// 狀態樣式
const statusClass = (status) => {
  const classes = {
    active: 'bg-green-100 text-green-800',
    trial: 'bg-yellow-100 text-yellow-800',
    suspended: 'bg-red-100 text-red-800',
    inactive: 'bg-gray-100 text-gray-800'
  }
  return classes[status] || 'bg-gray-100 text-gray-800'
}

const statusText = (status) => {
  const texts = {
    active: '活躍',
    trial: '試用',
    suspended: '暫停',
    inactive: '停用'
  }
  return texts[status] || status
}

// 方案樣式
const planClass = (plan) => {
  const classes = {
    basic: 'bg-gray-100 text-gray-800',
    standard: 'bg-blue-100 text-blue-800',
    premium: 'bg-purple-100 text-purple-800'
  }
  return classes[plan] || 'bg-gray-100 text-gray-800'
}

const planText = (plan) => {
  const texts = {
    basic: '基本版',
    standard: '標準版',
    premium: '專業版'
  }
  return texts[plan] || plan
}

// 載入資料
onMounted(() => {
  fetchTenants()
  fetchStats()
})
</script>
