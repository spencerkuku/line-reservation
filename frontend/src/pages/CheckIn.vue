<template>
  <div class="min-h-screen bg-gray-50 p-6">
    <!-- 頁面標題 -->
    <div class="mb-8">
      <h1 class="text-3xl font-bold text-gray-900">報到管理</h1>
      <p class="text-gray-600 mt-2">即時掌握今日預約與報到狀態</p>
    </div>

    <!-- 統計卡片 -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
      <!-- 今日總預約 -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-gray-600">今日總預約</p>
            <p class="text-2xl font-bold text-gray-900 mt-2">{{ statistics.total }}</p>
          </div>
          <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
          </div>
        </div>
      </div>

      <!-- 已報到 -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-gray-600">已報到</p>
            <p class="text-2xl font-bold text-green-600 mt-2">{{ statistics.checked_in + statistics.late }}</p>
            <p class="text-xs text-gray-500 mt-1">正常 {{ statistics.checked_in }} · 遲到 {{ statistics.late }}</p>
          </div>
          <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
        </div>
      </div>

      <!-- 待報到 -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-gray-600">待報到</p>
            <p class="text-2xl font-bold text-yellow-600 mt-2">{{ statistics.pending }}</p>
          </div>
          <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
            <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
        </div>
      </div>

      <!-- 爽約 -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-gray-600">爽約</p>
            <p class="text-2xl font-bold text-red-600 mt-2">{{ statistics.no_show }}</p>
          </div>
          <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center">
            <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
        </div>
      </div>
    </div>

    <!-- 今日預約列表 -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
      <div class="px-6 py-4 border-b border-gray-200">
        <div class="flex items-center justify-between">
          <h2 class="text-lg font-semibold text-gray-900">今日預約列表</h2>
          <div class="flex items-center gap-3">
            <span class="text-sm text-gray-500">共 {{ reservations.length }} 筆</span>
            <button 
              @click="loadTodayReservations" 
              :disabled="loading"
              class="text-sm text-blue-600 hover:text-blue-700 disabled:opacity-50"
            >
              {{ loading ? '載入中...' : '重新整理' }}
            </button>
          </div>
        </div>
      </div>
      
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">預約時間</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">客戶資訊</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">服務項目</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">報到狀態</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">付款狀態</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">操作</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr v-for="reservation in reservations" :key="reservation.id" class="hover:bg-gray-50">
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                <div class="font-medium">{{ reservation.reservation_time }}</div>
              </td>
              <td class="px-6 py-4">
                <div class="flex items-start space-x-3">
                  <!-- LINE 頭貼 -->
                  <div class="flex-shrink-0">
                    <img 
                      v-if="reservation.customer?.line_picture_url" 
                      :src="reservation.customer.line_picture_url" 
                      :alt="reservation.customer.line_display_name || '客戶頭貼'"
                      class="h-12 w-12 rounded-full object-cover border-2 border-gray-200"
                      @error="handleImageError"
                    />
                    <div 
                      v-else 
                      class="h-12 w-12 rounded-full bg-gradient-to-br from-blue-400 to-purple-500 flex items-center justify-center text-white font-semibold text-lg border-2 border-gray-200"
                    >
                      {{ getInitials(reservation.reservation_name) }}
                    </div>
                  </div>
                  
                  <!-- 客戶詳細資訊 -->
                  <div class="flex-1 min-w-0">
                    <!-- LINE 帳號名稱 -->
                    <div v-if="reservation.customer?.line_display_name" class="flex items-center space-x-2 mb-1">
                      <svg class="w-4 h-4 text-green-500 flex-shrink-0" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M19.365 9.863c.349 0 .63.285.63.631 0 .345-.281.63-.63.63H17.61v1.125h1.755c.349 0 .63.283.63.63 0 .344-.281.629-.63.629h-2.386c-.345 0-.627-.285-.627-.629V8.108c0-.345.282-.63.63-.63h2.386c.346 0 .627.285.627.63 0 .349-.281.63-.63.63H17.61v1.125h1.755zm-3.855 3.016c0 .27-.174.51-.432.596-.064.021-.133.031-.199.031-.211 0-.391-.09-.51-.25l-2.443-3.317v2.94c0 .344-.279.629-.631.629-.346 0-.626-.285-.626-.629V8.108c0-.27.173-.51.43-.595.06-.023.136-.033.194-.033.195 0 .375.104.495.254l2.462 3.33V8.108c0-.345.282-.63.63-.63.345 0 .63.285.63.63v4.771zm-5.741 0c0 .344-.282.629-.631.629-.345 0-.627-.285-.627-.629V8.108c0-.345.282-.63.63-.63.346 0 .628.285.628.63v4.771zm-2.466.629H4.917c-.345 0-.63-.285-.63-.629V8.108c0-.345.285-.63.63-.63.348 0 .63.285.63.63v4.141h1.756c.348 0 .629.283.629.63 0 .344-.282.629-.629.629M24 10.314C24 4.943 18.615.572 12 .572S0 4.943 0 10.314c0 4.811 4.27 8.842 10.035 9.608.391.082.923.258 1.058.59.12.301.079.766.038 1.08l-.164 1.02c-.045.301-.24 1.186 1.049.645 1.291-.539 6.916-4.078 9.436-6.975C23.176 14.393 24 12.458 24 10.314"/>
                      </svg>
                      <span class="text-sm font-semibold text-gray-900 truncate">
                        {{ reservation.customer.line_display_name }}
                      </span>
                      <span 
                        v-if="reservation.customer.customer_level" 
                        :class="getLevelBadgeClass(reservation.customer.customer_level)"
                        class="px-2 py-0.5 text-xs font-bold rounded-full flex-shrink-0"
                      >
                        {{ reservation.customer.customer_level }}
                      </span>
                    </div>
                    
                    <!-- 預約名字 -->
                    <div class="flex items-start space-x-1.5 mb-0.5">
                      <svg class="w-3.5 h-3.5 text-gray-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                      </svg>
                      <span class="text-sm text-gray-700">{{ reservation.reservation_name }}</span>
                    </div>
                    
                    <!-- 電話 -->
                    <div class="flex items-start space-x-1.5">
                      <svg class="w-3.5 h-3.5 text-gray-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                      </svg>
                      <span class="text-sm text-gray-600">{{ reservation.reservation_phone }}</span>
                    </div>
                  </div>
                </div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm font-medium text-gray-900">{{ reservation.service_name }}</div>
                <div class="text-xs text-gray-500">NT$ {{ formatPrice(reservation.service_price) }}</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div>
                  <span :class="getCheckInStatusClass(reservation.check_in_status)" class="px-2 py-1 text-xs font-semibold rounded-full">
                    {{ reservation.check_in_status_text }}
                  </span>
                  <div v-if="reservation.check_in_time" class="text-xs text-gray-500 mt-1">
                    {{ reservation.check_in_time }}
                  </div>
                </div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div>
                  <span :class="getPaymentStatusClass(reservation.payment_status)" class="px-2 py-1 text-xs font-semibold rounded-full">
                    {{ reservation.payment_status_text }}
                  </span>
                  <div v-if="reservation.payment_amount > 0" class="text-xs text-gray-500 mt-1">
                    NT$ {{ formatPrice(reservation.payment_amount) }} ({{ reservation.payment_method_text }})
                  </div>
                </div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                <div class="flex flex-col space-y-1">
                  <button
                    v-if="reservation.check_in_status === 'pending'"
                    @click="checkIn(reservation)"
                    class="text-green-600 hover:text-green-900 text-left"
                  >
                    報到
                  </button>
                  <button
                    v-if="reservation.check_in_status === 'pending'"
                    @click="markNoShow(reservation)"
                    class="text-red-600 hover:text-red-900 text-left"
                  >
                    爽約
                  </button>
                  <button
                    v-if="['checked_in', 'late'].includes(reservation.check_in_status)"
                    @click="openPaymentModal(reservation)"
                    class="text-blue-600 hover:text-blue-900 text-left"
                  >
                    收款
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="reservations.length === 0">
              <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                <div class="flex flex-col items-center justify-center">
                  <svg class="w-12 h-12 text-gray-400 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                  </svg>
                  <p class="text-sm font-medium">今日暫無預約記錄</p>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- 付款記錄 Modal -->
    <div v-if="showPaymentModal" class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center p-4 z-50" @click.self="closePaymentModal">
      <div class="bg-white rounded-xl shadow-xl max-w-md w-full">
        <div class="px-6 py-4 border-b border-gray-200">
          <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold text-gray-900">記錄付款</h3>
            <button @click="closePaymentModal" class="text-gray-400 hover:text-gray-500">
              <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
        </div>
        
        <div class="px-6 py-4 space-y-4">
          <!-- 客戶資訊 -->
          <div class="bg-gray-50 rounded-lg p-4">
            <div class="flex items-center space-x-3">
              <!-- LINE 頭貼 -->
              <div class="flex-shrink-0">
                <img 
                  v-if="selectedReservation?.customer?.line_picture_url" 
                  :src="selectedReservation.customer.line_picture_url" 
                  :alt="selectedReservation.customer.line_display_name || '客戶頭貼'"
                  class="h-14 w-14 rounded-full object-cover border-2 border-gray-300"
                  @error="handleImageError"
                />
                <div 
                  v-else 
                  class="h-14 w-14 rounded-full bg-gradient-to-br from-blue-400 to-purple-500 flex items-center justify-center text-white font-semibold text-xl border-2 border-gray-300"
                >
                  {{ getInitials(selectedReservation?.reservation_name) }}
                </div>
              </div>
              
              <!-- 客戶詳細資訊 -->
              <div class="flex-1">
                <div v-if="selectedReservation?.customer?.line_display_name" class="flex items-center space-x-2 mb-1">
                  <svg class="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M19.365 9.863c.349 0 .63.285.63.631 0 .345-.281.63-.63.63H17.61v1.125h1.755c.349 0 .63.283.63.63 0 .344-.281.629-.63.629h-2.386c-.345 0-.627-.285-.627-.629V8.108c0-.345.282-.63.63-.63h2.386c.346 0 .627.285.627.63 0 .349-.281.63-.63.63H17.61v1.125h1.755zm-3.855 3.016c0 .27-.174.51-.432.596-.064.021-.133.031-.199.031-.211 0-.391-.09-.51-.25l-2.443-3.317v2.94c0 .344-.279.629-.631.629-.346 0-.626-.285-.626-.629V8.108c0-.27.173-.51.43-.595.06-.023.136-.033.194-.033.195 0 .375.104.495.254l2.462 3.33V8.108c0-.345.282-.63.63-.63.345 0 .63.285.63.63v4.771zm-5.741 0c0 .344-.282.629-.631.629-.345 0-.627-.285-.627-.629V8.108c0-.345.282-.63.63-.63.346 0 .628.285.628.63v4.771zm-2.466.629H4.917c-.345 0-.63-.285-.63-.629V8.108c0-.345.285-.63.63-.63.348 0 .63.285.63.63v4.141h1.756c.348 0 .629.283.629.63 0 .344-.282.629-.629.629M24 10.314C24 4.943 18.615.572 12 .572S0 4.943 0 10.314c0 4.811 4.27 8.842 10.035 9.608.391.082.923.258 1.058.59.12.301.079.766.038 1.08l-.164 1.02c-.045.301-.24 1.186 1.049.645 1.291-.539 6.916-4.078 9.436-6.975C23.176 14.393 24 12.458 24 10.314"/>
                  </svg>
                  <span class="text-sm font-semibold text-gray-900">{{ selectedReservation.customer.line_display_name }}</span>
                  <span 
                    v-if="selectedReservation.customer.customer_level" 
                    :class="getLevelBadgeClass(selectedReservation.customer.customer_level)"
                    class="px-2 py-0.5 text-xs font-bold rounded-full"
                  >
                    {{ selectedReservation.customer.customer_level }}
                  </span>
                </div>
                <div class="text-sm text-gray-700 font-medium">{{ selectedReservation?.reservation_name }}</div>
                <div class="text-xs text-gray-500">{{ selectedReservation?.reservation_phone }}</div>
              </div>
            </div>
          </div>
          
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700">服務費用</label>
              <div class="mt-1 text-sm font-semibold text-gray-900">NT$ {{ formatPrice(selectedReservation?.service_price) }}</div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">已付款</label>
              <div class="mt-1 text-sm font-semibold text-green-600">NT$ {{ formatPrice(selectedReservation?.payment_amount || 0) }}</div>
            </div>
          </div>
          
          <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3">
            <div class="flex items-center justify-between">
              <span class="text-sm font-medium text-yellow-800">尚需付款</span>
              <span class="text-lg font-bold text-yellow-900">
                NT$ {{ formatPrice(Math.max(0, (selectedReservation?.service_price || 0) - (selectedReservation?.payment_amount || 0))) }}
              </span>
            </div>
          </div>
          
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">付款金額 *</label>
            <input
              v-model.number="paymentForm.amount"
              type="number"
              min="0"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              placeholder="輸入付款金額"
            />
          </div>
          
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">付款方式 *</label>
            <select
              v-model="paymentForm.method"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            >
              <option value="">請選擇</option>
              <option value="cash">現金</option>
              <option value="credit_card">信用卡</option>
              <option value="debit_card">金融卡</option>
              <option value="transfer">轉帳</option>
              <option value="line_pay">LINE Pay</option>
              <option value="other">其他</option>
            </select>
          </div>
          
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">備註</label>
            <textarea
              v-model="paymentForm.note"
              rows="3"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none"
              placeholder="輸入備註（選填）"
            ></textarea>
          </div>
        </div>
        
        <div class="px-6 py-4 border-t border-gray-200 flex justify-end gap-3">
          <button
            @click="closePaymentModal"
            class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors"
          >
            取消
          </button>
          <button
            @click="recordPayment"
            :disabled="!paymentForm.amount || !paymentForm.method"
            class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 disabled:bg-gray-300 disabled:cursor-not-allowed transition-colors"
          >
            確認收款
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { apiGet, apiPost } from '../utils/api';

const reservations = ref([]);
const loading = ref(false);
const statistics = ref({
  total: 0,
  checked_in: 0,
  late: 0,
  no_show: 0,
  pending: 0,
  paid: 0,
  unpaid: 0,
  partial: 0
});

const showPaymentModal = ref(false);
const selectedReservation = ref(null);
const paymentForm = ref({
  amount: null,
  method: '',
  note: ''
});

let refreshInterval = null;

const currentDate = computed(() => {
  const now = new Date();
  return now.toLocaleDateString('zh-TW', { year: 'numeric', month: 'long', day: 'numeric', weekday: 'long' });
});

// 載入今日預約列表
const loadTodayReservations = async () => {
  loading.value = true;
  try {
    const response = await apiGet('/check-in/today');
    if (response.success) {
      reservations.value = response.data;
      statistics.value = response.statistics;
    }
  } catch (error) {
    console.error('載入預約列表失敗:', error);
  } finally {
    loading.value = false;
  }
};

// 報到
const checkIn = async (reservation) => {
  if (!confirm(`確認 ${reservation.customer_name} 報到？`)) return;
  
  try {
    const response = await apiPost(`/check-in/reservations/${reservation.id}/check-in`);
    if (response.success) {
      alert('報到成功！');
      loadTodayReservations();
    }
  } catch (error) {
    alert(error.response?.data?.message || '報到失敗');
  }
};

// 標記爽約
const markNoShow = async (reservation) => {
  if (!confirm(`確認標記 ${reservation.customer_name} 為爽約？`)) return;
  
  try {
    const response = await apiPost(`/check-in/reservations/${reservation.id}/no-show`);
    if (response.success) {
      alert('已標記為爽約');
      loadTodayReservations();
    }
  } catch (error) {
    alert(error.response?.data?.message || '操作失敗');
  }
};

// 開啟付款 Modal
const openPaymentModal = (reservation) => {
  selectedReservation.value = reservation;
  const remaining = reservation.service_price - (reservation.payment_amount || 0);
  paymentForm.value = {
    amount: remaining > 0 ? remaining : null,
    method: '',
    note: ''
  };
  showPaymentModal.value = true;
};

// 關閉付款 Modal
const closePaymentModal = () => {
  showPaymentModal.value = false;
  selectedReservation.value = null;
  paymentForm.value = {
    amount: null,
    method: '',
    note: ''
  };
};

// 記錄付款
const recordPayment = async () => {
  if (!selectedReservation.value || !paymentForm.value.amount || !paymentForm.value.method) return;
  
  try {
    const response = await apiPost(`/check-in/reservations/${selectedReservation.value.id}/payment`, paymentForm.value);
    if (response.success) {
      alert('付款記錄成功！');
      closePaymentModal();
      loadTodayReservations();
    }
  } catch (error) {
    alert(error.response?.data?.message || '付款記錄失敗');
  }
};

// 報到狀態樣式
const getCheckInStatusClass = (status) => {
  const classes = {
    pending: 'bg-yellow-100 text-yellow-800',
    checked_in: 'bg-green-100 text-green-800',
    late: 'bg-orange-100 text-orange-800',
    no_show: 'bg-red-100 text-red-800'
  };
  return classes[status] || 'bg-gray-100 text-gray-800';
};

// 付款狀態樣式
const getPaymentStatusClass = (status) => {
  const classes = {
    unpaid: 'bg-red-100 text-red-800',
    partial: 'bg-yellow-100 text-yellow-800',
    paid: 'bg-green-100 text-green-800',
    refunded: 'bg-gray-100 text-gray-800'
  };
  return classes[status] || 'bg-gray-100 text-gray-800';
};

// 格式化價格
const formatPrice = (price) => {
  return new Intl.NumberFormat('zh-TW').format(price || 0);
};

// 獲取客戶名字縮寫（用於頭貼）
const getInitials = (name) => {
  if (!name) return '?';
  // 如果是中文名，取第一個字
  if (/[\u4e00-\u9fa5]/.test(name)) {
    return name.charAt(0);
  }
  // 如果是英文名，取首字母
  const parts = name.split(' ');
  if (parts.length >= 2) {
    return (parts[0].charAt(0) + parts[1].charAt(0)).toUpperCase();
  }
  return name.charAt(0).toUpperCase();
};

// 處理圖片載入錯誤
const handleImageError = (event) => {
  event.target.style.display = 'none';
};

// 獲取等級徽章樣式
const getLevelBadgeClass = (level) => {
  const classes = {
    'VIP': 'bg-gradient-to-r from-yellow-400 to-yellow-600 text-white',
    'Gold': 'bg-gradient-to-r from-yellow-300 to-yellow-500 text-gray-900',
    'Silver': 'bg-gradient-to-r from-gray-300 to-gray-400 text-gray-900',
    'Bronze': 'bg-gradient-to-r from-orange-300 to-orange-400 text-gray-900'
  };
  return classes[level] || 'bg-gray-200 text-gray-700';
};

onMounted(() => {
  loadTodayReservations();
  // 每30秒自動刷新
  refreshInterval = setInterval(loadTodayReservations, 30000);
});

onUnmounted(() => {
  if (refreshInterval) {
    clearInterval(refreshInterval);
  }
});
</script>
