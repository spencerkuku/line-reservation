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
                {{ reservation.reservation_time }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm font-medium text-gray-900">{{ reservation.customer_name }}</div>
                <div class="text-sm text-gray-500">{{ reservation.customer_phone }}</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                {{ reservation.service_name }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span :class="getCheckInStatusClass(reservation.check_in_status)" class="px-2 py-1 text-xs font-semibold rounded-full">
                  {{ reservation.check_in_status_text }}
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span :class="getPaymentStatusClass(reservation.payment_status)" class="px-2 py-1 text-xs font-semibold rounded-full">
                  {{ reservation.payment_status_text }}
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                <button
                  v-if="reservation.check_in_status === 'pending'"
                  @click="checkIn(reservation)"
                  class="text-green-600 hover:text-green-900"
                >
                  報到
                </button>
                <button
                  v-if="reservation.check_in_status === 'pending'"
                  @click="markNoShow(reservation)"
                  class="text-red-600 hover:text-red-900"
                >
                  爽約
                </button>
                <button
                  v-if="['checked_in', 'late'].includes(reservation.check_in_status)"
                  @click="openPaymentModal(reservation)"
                  class="text-blue-600 hover:text-blue-900"
                >
                  收款
                </button>
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
          <div>
            <label class="block text-sm font-medium text-gray-700">客戶姓名</label>
            <div class="mt-1 text-sm text-gray-900">{{ selectedReservation?.customer_name }}</div>
          </div>
          
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700">服務費用</label>
              <div class="mt-1 text-sm font-semibold text-gray-900">NT$ {{ selectedReservation?.service_price }}</div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">已付款</label>
              <div class="mt-1 text-sm font-semibold text-green-600">NT$ {{ selectedReservation?.payment_amount || 0 }}</div>
            </div>
          </div>
          
          <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3">
            <div class="flex items-center justify-between">
              <span class="text-sm font-medium text-yellow-800">尚需付款</span>
              <span class="text-lg font-bold text-yellow-900">
                NT$ {{ Math.max(0, (selectedReservation?.service_price || 0) - (selectedReservation?.payment_amount || 0)) }}
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
