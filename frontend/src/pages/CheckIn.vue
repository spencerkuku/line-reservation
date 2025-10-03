<template>
  <div class="p-6 space-y-6">
    <!-- 頁面標題 -->
    <div class="flex justify-between items-center">
      <h1 class="text-2xl font-bold text-gray-900">報到管理</h1>
      <div class="text-sm text-gray-500">
        {{ currentDate }}
      </div>
    </div>

    <!-- 統計卡片 -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
      <div class="bg-white rounded-lg shadow p-6">
        <div class="text-sm text-gray-500 mb-2">今日總預約</div>
        <div class="text-3xl font-bold text-gray-900">{{ statistics.total }}</div>
      </div>
      <div class="bg-white rounded-lg shadow p-6">
        <div class="text-sm text-gray-500 mb-2">已報到</div>
        <div class="text-3xl font-bold text-green-600">{{ statistics.checked_in + statistics.late }}</div>
      </div>
      <div class="bg-white rounded-lg shadow p-6">
        <div class="text-sm text-gray-500 mb-2">待報到</div>
        <div class="text-3xl font-bold text-yellow-600">{{ statistics.pending }}</div>
      </div>
      <div class="bg-white rounded-lg shadow p-6">
        <div class="text-sm text-gray-500 mb-2">爽約</div>
        <div class="text-3xl font-bold text-red-600">{{ statistics.no_show }}</div>
      </div>
    </div>

    <!-- 今日預約列表 -->
    <div class="bg-white rounded-lg shadow">
      <div class="px-6 py-4 border-b border-gray-200">
        <h2 class="text-lg font-semibold text-gray-900">今日預約列表</h2>
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
              <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                今日暫無預約記錄
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- 付款記錄 Modal -->
    <div v-if="showPaymentModal" class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center p-4 z-50">
      <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
        <div class="px-6 py-4 border-b border-gray-200">
          <div class="flex items-center justify-between">
            <h3 class="text-lg font-medium text-gray-900">記錄付款</h3>
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
          <div>
            <label class="block text-sm font-medium text-gray-700">服務費用</label>
            <div class="mt-1 text-sm text-gray-900">NT$ {{ selectedReservation?.service_price }}</div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">已付款金額</label>
            <div class="mt-1 text-sm text-gray-900">NT$ {{ selectedReservation?.payment_amount }}</div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">付款金額 *</label>
            <input
              v-model.number="paymentForm.amount"
              type="number"
              min="0"
              class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
              placeholder="輸入付款金額"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">付款方式 *</label>
            <select
              v-model="paymentForm.method"
              class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
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
              class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
              placeholder="輸入備註（選填）"
            ></textarea>
          </div>
        </div>
        <div class="px-6 py-4 border-t border-gray-200 flex justify-end gap-3">
          <button
            @click="closePaymentModal"
            class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50"
          >
            取消
          </button>
          <button
            @click="recordPayment"
            :disabled="!paymentForm.amount || !paymentForm.method"
            class="px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700 disabled:bg-gray-300 disabled:cursor-not-allowed"
          >
            確認收款
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { apiGet, apiPost } from '../utils/api';

const reservations = ref([]);
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

const currentDate = computed(() => {
  const now = new Date();
  return now.toLocaleDateString('zh-TW', { year: 'numeric', month: 'long', day: 'numeric', weekday: 'long' });
});

// 載入今日預約列表
const loadTodayReservations = async () => {
  try {
    const response = await apiGet('/check-in/today');
    if (response.success) {
      reservations.value = response.data;
      statistics.value = response.statistics;
    }
  } catch (error) {
    console.error('載入預約列表失敗:', error);
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
  const remaining = reservation.service_price - reservation.payment_amount;
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
  setInterval(loadTodayReservations, 30000);
});
</script>
