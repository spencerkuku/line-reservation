<script setup>
import { ref, computed, watch, nextTick, onUnmounted, onMounted } from 'vue'
import FullCalendar from '@fullcalendar/vue3'
import dayGridPlugin from '@fullcalendar/daygrid'
import timeGridPlugin from '@fullcalendar/timegrid'
import interactionPlugin from '@fullcalendar/interaction'
import zhTwLocale from '@fullcalendar/core/locales/zh-tw'
import { apiGet, apiPost, apiPut, apiDelete } from '../utils/api.js'

// 核心狀態
const calendarEvents = ref([])
const calendarRef = ref(null)
const isLoading = ref(false)

// 快速建立狀態
const showQuickCreate = ref(false)
const quickInput = ref(null)
const quickSlotTitle = ref('可預約時段')
const selectedTime = ref(null)

// 編輯 Modal 狀態
const showEditModal = ref(false)
const editForm = ref({
  id: null,
  title: '',
  description: '',
  start: null,
  end: null
})

// Toast 通知
const toast = ref({ show: false, type: 'success', message: '' })

// 當前檢視模式
const currentView = ref('timeGridWeek')
const currentDate = ref(new Date())

// 獲取可預約時段
async function fetchAvailableTimes() {
  isLoading.value = true
  try {
    const data = await apiGet('/available-times')
    console.log('Fetched data:', data) // Debug log
    
    // 檢查返回的數據結構
    let timeData = data.data || data
    
    // 如果是分頁數據，取 data 屬性
    if (timeData && typeof timeData === 'object' && timeData.data) {
      timeData = timeData.data
    }
    
    // 確保是數組
    if (!Array.isArray(timeData)) {
      console.error('API 返回的數據不是數組:', timeData)
      timeData = []
    }
    
    console.log('Processing timeData:', timeData) // Debug log
    
    const events = timeData.map(item => {
      console.log('Processing item:', item) // Debug log for each item
      
      const event = {
        id: item.id,
        title: item.title || '可預約時段',
        start: item.start_time || item.start,
        end: item.end_time || item.end,
        description: item.description || '',
        extendedProps: {
          description: item.description || ''
        }
      }
      
      console.log('Created event:', event) // Debug log for the final event
      return event
    })
    
    console.log('Final events:', events) // Debug log
    calendarEvents.value = events
  } catch (err) {
    console.error('Fetch error:', err)
    showToast('error', '載入失敗')
  } finally {
    isLoading.value = false
  }
}

// 簡化的 CRUD 操作
async function createSlot(slotData) {
  try {
    // 確保時間格式正確 - 使用 ISO 格式但保持本地時區
    const startTime = new Date(slotData.start)
    const endTime = new Date(slotData.end)
    
    console.log('Creating slot with data:', {
      title: slotData.title,
      description: slotData.description || '',
      start_time: formatBackendDateTime(startTime),
      end_time: formatBackendDateTime(endTime),
      max_capacity: 10
    })
    
    const response = await apiPost('/available-times', {
      title: slotData.title,
      description: slotData.description || '',
      start_time: formatBackendDateTime(startTime),
      end_time: formatBackendDateTime(endTime),
      max_capacity: 10
    })
    
    console.log('Create response:', response)
    await fetchAvailableTimes()
    showToast('success', '時段已建立')
  } catch (err) {
    console.error('Create slot error:', err)
    const errorMessage = err.message || '建立失敗'
    showToast('error', errorMessage)
  }
}

async function updateSlot(id, slotData) {
  try {
    const startTime = new Date(slotData.start)
    const endTime = new Date(slotData.end)
    
    await apiPut(`/available-times/${id}`, {
      title: slotData.title,
      description: slotData.description || '',
      start_time: formatBackendDateTime(startTime),
      end_time: formatBackendDateTime(endTime),
      max_capacity: 10
    })
    await fetchAvailableTimes()
    showToast('success', '時段已更新')
  } catch (err) {
    console.error('Update slot error:', err)
    const errorMessage = err.message || '更新失敗'
    showToast('error', errorMessage)
  }
}

async function deleteSlot(id) {
  try {
    await apiDelete(`/available-times/${id}`)
    await fetchAvailableTimes()
    showToast('success', '時段已刪除')
  } catch (err) {
    showToast('error', '刪除失敗')
  }
}

// Google Calendar 風格的 FullCalendar 配置
const calendarOptions = computed(() => ({
  plugins: [dayGridPlugin, timeGridPlugin, interactionPlugin],
  locale: zhTwLocale,
  
  // 移除預設工具列，使用自定義
  headerToolbar: false,
  
  // Google 風格檢視設定
  initialView: 'timeGridWeek',
  views: {
    timeGridWeek: {
      dayHeaderFormat: { weekday: 'short', day: 'numeric' }
    },
    timeGridDay: {
      dayHeaderFormat: { weekday: 'long', day: 'numeric' }
    }
  },
  
  // 簡潔的時間設定 - 24小時顯示範圍
  slotMinTime: '06:00:00',
  slotMaxTime: '24:00:00',
  slotDuration: '00:30:00',
  slotLabelInterval: '01:00:00',
  height: 'auto',
  
  // 簡化功能
  weekNumbers: false,
  nowIndicator: true,
  allDaySlot: false,
  
  // 核心互動
  selectable: true,
  selectMirror: true,
  editable: true,
  
  // 事件樣式
  eventDisplay: 'block',
  eventClassNames: 'available-slot',
  
  // 事件資料 - 使用響應式數據
  events: calendarEvents.value,
  
  // 事件處理
  select: handleTimeSelect,
  eventClick: handleSlotEdit,
  eventChange: handleSlotUpdate,
  eventDrop: handleSlotDrop,
  eventResize: handleSlotResize
}))

// FullCalendar 配置現在是 computed，會自動響應 calendarEvents 的變化

// 快速建立時段 - Google 風格
function handleTimeSelect(info) {
  // 檢查是否為未來時間
  const now = new Date()
  const startTime = new Date(info.start)
  
  if (startTime <= now) {
    showToast('error', '無法設定過去的時間')
    return
  }
  
  // 暫時移除營業時間限制，允許全天時段
  // const startHour = startTime.getHours()
  // if (startHour < 8 || startHour >= 20) {
  //   showToast('error', '請選擇營業時間內 (08:00-20:00)')
  //   return
  // }
  
  selectedTime.value = info
  quickSlotTitle.value = '可預約時段'
  showQuickCreate.value = true
  
  nextTick(() => {
    quickInput.value?.focus()
  })
}

// 編輯時段
function handleSlotEdit(info) {
  const event = info.event
  editForm.value = {
    id: event.id,
    title: event.title,
    description: event.extendedProps.description || '',
    start: event.start,
    end: event.end
  }
  showEditModal.value = true
}

// 時段更新處理
async function handleSlotUpdate(info) {
  const event = info.event
  await updateSlot(event.id, {
    title: event.title,
    description: event.extendedProps.description || '',
    start: event.start,
    end: event.end
  })
}

// 拖拽處理
async function handleSlotDrop(info) {
  const now = new Date()
  const startTime = new Date(info.event.start)
  
  if (startTime <= now) {
    info.revert()
    showToast('error', '無法移動到過去的時間')
    return
  }
  
  await handleSlotUpdate(info)
}

// 調整大小處理
async function handleSlotResize(info) {
  const start = new Date(info.event.start)
  const end = new Date(info.event.end)
  const durationMinutes = Math.floor((end.getTime() - start.getTime()) / (1000 * 60))
  
  if (durationMinutes < 30) {
    info.revert()
    showToast('error', '時段不得少於30分鐘')
    return
  }
  
  if (durationMinutes > 480) {
    info.revert()
    showToast('error', '時段不得超過8小時')
    return
  }
  
  await handleSlotUpdate(info)
}

// 快速建立功能
async function createQuickSlot() {
  if (!quickSlotTitle.value.trim()) {
    showToast('error', '請輸入時段名稱')
    return
  }
  
  await createSlot({
    title: quickSlotTitle.value.trim(),
    start: selectedTime.value.start,
    end: selectedTime.value.end
  })
  
  cancelQuickCreate()
}

function cancelQuickCreate() {
  showQuickCreate.value = false
  selectedTime.value = null
  quickSlotTitle.value = '可預約時段'
}

function showDetailModal() {
  editForm.value = {
    id: null,
    title: quickSlotTitle.value,
    description: '',
    start: selectedTime.value.start,
    end: selectedTime.value.end
  }
  cancelQuickCreate()
  showEditModal.value = true
}

// 編輯 Modal 功能
async function saveSlot() {
  if (!editForm.value.title.trim()) {
    showToast('error', '請輸入時段名稱')
    return
  }
  
  if (editForm.value.id) {
    // 更新現有時段
    await updateSlot(editForm.value.id, editForm.value)
  } else {
    // 建立新時段
    await createSlot(editForm.value)
  }
  
  closeEditModal()
}

async function deleteCurrentSlot() {
  if (editForm.value.id) {
    await deleteSlot(editForm.value.id)
    closeEditModal()
  }
}

function closeEditModal() {
  showEditModal.value = false
  editForm.value = {
    id: null,
    title: '',
    description: '',
    start: null,
    end: null
  }
}

// 日曆導航
function goToToday() {
  const calendar = calendarRef.value?.getApi()
  if (calendar) {
    calendar.today()
    currentDate.value = calendar.getDate()
  }
}

function navigatePrev() {
  const calendar = calendarRef.value?.getApi()
  if (calendar) {
    calendar.prev()
    currentDate.value = calendar.getDate()
  }
}

function navigateNext() {
  const calendar = calendarRef.value?.getApi()
  if (calendar) {
    calendar.next()
    currentDate.value = calendar.getDate()
  }
}

function changeView(viewName) {
  const calendar = calendarRef.value?.getApi()
  if (calendar) {
    calendar.changeView(viewName)
    currentView.value = viewName
  }
}

// 格式化時間
function formatTimeRange(timeInfo) {
  if (!timeInfo) return ''
  const start = new Date(timeInfo.start)
  const end = new Date(timeInfo.end)
  return `${formatTime(start)} - ${formatTime(end)}`
}

function formatTime(date) {
  return date.toLocaleTimeString('zh-TW', { 
    hour: '2-digit', 
    minute: '2-digit',
    hour12: false
  })
}

function formatDateTime(date) {
  return date.toLocaleString('zh-TW', {
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    hour12: false
  })
}

function formatLocalDateTime(date) {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  const hours = String(date.getHours()).padStart(2, '0')
  const minutes = String(date.getMinutes()).padStart(2, '0')
  const seconds = String(date.getSeconds()).padStart(2, '0')
  
  return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`
}

// 為後端API格式化時間 - 確保發送本地時間而非UTC
function formatBackendDateTime(date) {
  // 獲取本地時間的各個部分
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  const hours = String(date.getHours()).padStart(2, '0')
  const minutes = String(date.getMinutes()).padStart(2, '0')
  const seconds = String(date.getSeconds()).padStart(2, '0')
  
  // 返回本地時間格式，不包含時區資訊
  return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`
}

// 當前期間顯示
const currentPeriod = computed(() => {
  if (!currentDate.value) return ''
  
  const date = new Date(currentDate.value)
  
  if (currentView.value === 'timeGridWeek') {
    return `${date.getFullYear()}年${date.getMonth() + 1}月`
  } else if (currentView.value === 'timeGridDay') {
    return date.toLocaleDateString('zh-TW', { 
      year: 'numeric', 
      month: 'long', 
      day: 'numeric' 
    })
  } else {
    return `${date.getFullYear()}年${date.getMonth() + 1}月`
  }
})

// Toast 通知
function showToast(type, message) {
  toast.value = { show: true, type, message }
  setTimeout(() => toast.value.show = false, 3000)
}

// 快捷鍵處理
function handleKeydown(e) {
  if (e.key === 'Escape') {
    if (showQuickCreate.value) cancelQuickCreate()
    if (showEditModal.value) closeEditModal()
  }
  if (e.key === 'Enter') {
    if (showQuickCreate.value) createQuickSlot()
    if (showEditModal.value) saveSlot()
  }
}

document.addEventListener('keydown', handleKeydown)
onUnmounted(() => document.removeEventListener('keydown', handleKeydown))

// 組件載入時獲取數據並導航到今天
onMounted(() => {
  fetchAvailableTimes()
  
  // 等待 FullCalendar 初始化完成後導航到今天
  nextTick(() => {
    setTimeout(() => {
      goToToday()
    }, 100)
  })
})
</script>

<template>
  <div class="min-h-screen bg-gray-50 p-6">
    <!-- 頁面標題和工具列 -->
    <div class="mb-8">
      <h1 class="text-3xl font-bold text-gray-900">設定可預約時段</h1>
      <p class="text-gray-600 mt-2">管理您的營業時間與可預約時段，拖拽即可調整時間</p>
    </div>

    <!-- Google 風格的工具列 -->
    <div class="flex items-center justify-between p-4 md:p-6 border-b border-gray-200 bg-white rounded-t-xl mb-6">
      <!-- 日曆導航 -->
      <div class="flex items-center gap-2">
        <button @click="goToToday" class="px-4 py-2 text-sm font-medium text-gray-700 bg-transparent border-0 rounded-md cursor-pointer transition-colors duration-200 hover:bg-gray-100 hover:text-gray-900">今天</button>
        <button @click="navigatePrev" class="flex items-center justify-center w-9 h-9 border-0 rounded-full bg-transparent text-gray-500 cursor-pointer transition-colors duration-200 text-sm hover:bg-gray-100">‹</button>
        <button @click="navigateNext" class="flex items-center justify-center w-9 h-9 border-0 rounded-full bg-transparent text-gray-500 cursor-pointer transition-colors duration-200 text-sm hover:bg-gray-100">›</button>
        <h2 class="text-lg font-semibold text-gray-900 mx-2 md:mx-4">{{ currentPeriod }}</h2>
      </div>
      
      <!-- 檢視切換 -->
      <div class="flex border border-gray-300 rounded-lg overflow-hidden">
        <button 
          @click="changeView('timeGridWeek')"
          :class="[
            'px-4 py-2 border-0 text-sm font-medium cursor-pointer transition-all duration-200',
            currentView === 'timeGridWeek' 
              ? 'bg-blue-500 text-white' 
              : 'bg-white text-gray-500 hover:bg-gray-50 hover:text-gray-700'
          ]"
        >
          週
        </button>
        <button 
          @click="changeView('timeGridDay')"
          :class="[
            'px-4 py-2 border-0 text-sm font-medium cursor-pointer transition-all duration-200',
            currentView === 'timeGridDay' 
              ? 'bg-blue-500 text-white' 
              : 'bg-white text-gray-500 hover:bg-gray-50 hover:text-gray-700'
          ]"
        >
          日
        </button>
        <button 
          @click="changeView('dayGridMonth')"
          :class="[
            'px-4 py-2 border-0 text-sm font-medium cursor-pointer transition-all duration-200',
            currentView === 'dayGridMonth' 
              ? 'bg-blue-500 text-white' 
              : 'bg-white text-gray-500 hover:bg-gray-50 hover:text-gray-700'
          ]"
        >
          月
        </button>
      </div>
    </div>

    <!-- 主要日曆區域 -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden relative">
      <div v-if="isLoading" class="absolute inset-0 flex flex-col items-center justify-center bg-white bg-opacity-90 z-10">
        <div class="w-8 h-8 border-3 border-gray-200 border-t-blue-500 rounded-full animate-spin"></div>
        <p class="mt-3 text-gray-500 text-sm">載入中...</p>
      </div>
      
      <FullCalendar 
        ref="calendarRef"
        :options="calendarOptions" 
        class="p-4 md:p-6"
      />
    </div>

    <!-- 快速建立氣泡 -->
    <div v-if="showQuickCreate" class="fixed bg-white border border-gray-300 rounded-xl shadow-lg p-5 min-w-80 z-[1000] top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 max-md:left-4 max-md:right-4 max-md:transform-none max-md:top-1/2 max-md:-mt-24 max-md:min-w-0">
      <input 
        ref="quickInput"
        v-model="quickSlotTitle"
        placeholder="可預約時段"
        @keyup.enter="createQuickSlot"
        @keyup.escape="cancelQuickCreate"
        class="w-full border-0 outline-none text-base font-medium text-gray-900 mb-2 bg-transparent placeholder-gray-400"
      />
      <div class="text-gray-500 text-sm mb-4 font-medium">
        {{ formatTimeRange(selectedTime) }}
      </div>
      <div class="flex justify-end gap-2">
        <button @click="createQuickSlot" class="bg-blue-500 text-white border-0 rounded-md px-4 py-2 text-sm font-medium cursor-pointer transition-all duration-200 hover:bg-blue-600 hover:-translate-y-0.5 hover:shadow-lg hover:shadow-blue-500/40">儲存</button>
        <button @click="showDetailModal" class="bg-transparent text-blue-500 border border-gray-300 rounded-md px-4 py-2 text-sm font-medium cursor-pointer transition-all duration-200 hover:bg-gray-50 hover:border-blue-500">更多選項</button>
      </div>
    </div>

    <!-- 編輯 Modal -->
    <div v-if="showEditModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-[1000] p-4">
      <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-hidden">
        <!-- 簡潔的標題列 -->
        <div class="flex items-center justify-between px-6 pt-6 pb-4 border-b border-gray-200">
          <h3 class="text-lg font-semibold text-gray-900 m-0">{{ editForm.id ? '編輯' : '新增' }}可預約時段</h3>
          <button @click="closeEditModal" class="w-8 h-8 border-0 rounded-full bg-transparent text-gray-500 text-xl cursor-pointer flex items-center justify-center transition-all duration-200 hover:bg-gray-100 hover:text-gray-700">×</button>
        </div>
        
        <!-- 最少必要欄位 -->
        <div class="p-6">
          <div class="mb-5">
            <label class="block text-sm font-medium text-gray-900 mb-2">時段名稱</label>
            <input 
              v-model="editForm.title" 
              placeholder="例如：諮詢服務"
              class="w-full border border-gray-300 rounded-md p-3 text-sm text-gray-900 transition-all duration-200 bg-white focus:outline-none focus:border-blue-500 focus:ring-3 focus:ring-blue-500/10"
            />
          </div>
          
          <div class="mb-5">
            <label class="block text-sm font-medium text-gray-900 mb-2">時間</label>
            <div class="p-3 bg-gray-50 rounded-md text-gray-500 text-sm font-medium border border-gray-200">
              {{ formatDateTime(editForm.start) }} - {{ formatDateTime(editForm.end) }}
            </div>
          </div>
          
          <div class="mb-5">
            <label class="block text-sm font-medium text-gray-900 mb-2">備註 <span class="font-normal text-gray-500">(選填)</span></label>
            <textarea 
              v-model="editForm.description"
              rows="2"
              placeholder="額外說明..."
              class="w-full border border-gray-300 rounded-md p-3 text-sm text-gray-900 transition-all duration-200 bg-white resize-y min-h-[60px] focus:outline-none focus:border-blue-500 focus:ring-3 focus:ring-blue-500/10"
            ></textarea>
          </div>
        </div>
        
        <!-- 簡單的操作按鈕 -->
        <div class="flex items-center justify-between px-6 pb-6 pt-4 border-t border-gray-200">
          <button 
            v-if="editForm.id" 
            @click="deleteCurrentSlot" 
            class="bg-transparent text-red-600 border border-red-200 rounded-md px-4 py-2 text-sm font-medium cursor-pointer transition-all duration-200 hover:bg-red-50 hover:border-red-600"
          >
            刪除
          </button>
          <div class="flex gap-3 ml-auto">
            <button @click="closeEditModal" class="bg-transparent text-gray-500 border border-gray-300 rounded-md px-4 py-2 text-sm font-medium cursor-pointer transition-all duration-200 hover:bg-gray-50 hover:text-gray-700 hover:border-gray-400">取消</button>
            <button @click="saveSlot" class="bg-blue-500 text-white border-0 rounded-md px-4 py-2 text-sm font-medium cursor-pointer transition-all duration-200 hover:bg-blue-600 hover:-translate-y-0.5 hover:shadow-lg hover:shadow-blue-500/40">儲存</button>
          </div>
        </div>
      </div>
    </div>

    <!-- 簡單的 Toast 通知 -->
    <div v-if="toast.show" :class="[
      'fixed top-6 right-6 px-4 py-3 rounded-lg text-sm font-medium shadow-lg z-[1100] animate-slide-in',
      toast.type === 'success' ? 'bg-green-100 text-green-800 border border-green-200' : 'bg-red-100 text-red-800 border border-red-200'
    ]">
      {{ toast.message }}
    </div>
  </div>
</template>

<style scoped>
/* 自定義樣式 */
.border-3 {
  border-width: 3px;
}

/* 自定義動畫 */
@keyframes slide-in {
  from {
    transform: translateX(100%);
    opacity: 0;
  }
  to {
    transform: translateX(0);
    opacity: 1;
  }
}

.animate-slide-in {
  animation: slide-in 0.3s ease-out;
}

/* FullCalendar 客製化 - 無法用 Tailwind 替代的樣式 */
:deep(.fc) {
  font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif;
}

:deep(.fc-toolbar) {
  display: none; /* 使用自定義工具列 */
}

:deep(.fc-col-header-cell) {
  background-color: #f9fafb;
  border-color: #e5e7eb;
  font-weight: 600;
  color: #374151;
  font-size: 12px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  padding: 12px 8px;
}

:deep(.fc-timegrid-slot) {
  border-color: #e5e7eb;
  height: 48px;
}

:deep(.fc-timegrid-slot-label) {
  color: #6b7280;
  font-size: 12px;
  font-weight: 500;
}

:deep(.fc-scrollgrid) {
  border-color: #e5e7eb;
  border-radius: 8px;
  overflow: hidden;
}

:deep(.fc-daygrid-day) {
  background-color: #ffffff;
}

:deep(.fc-daygrid-day:hover) {
  background-color: #f9fafb;
}

/* 可預約時段樣式 - 無法用 Tailwind 替代的樣式 */
:deep(.available-slot) {
  background-color: #3b82f6 !important;
  border: none !important;
  border-radius: 6px !important;
  color: #ffffff !important;
  font-size: 12px !important;
  font-weight: 500 !important;
  padding: 4px 8px !important;
  cursor: pointer !important;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1) !important;
}

:deep(.available-slot:hover) {
  background-color: #2563eb !important;
  box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4) !important;
  transform: translateY(-1px) !important;
}

:deep(.fc-event-dragging) {
  opacity: 0.7 !important;
  transform: rotate(2deg) !important;
}

:deep(.fc-highlight) {
  background-color: rgba(59, 130, 246, 0.1) !important;
}

:deep(.fc-select-mirror) {
  background-color: rgba(59, 130, 246, 0.3) !important;
  border-radius: 6px !important;
}

/* 響應式設計 - 使用 Tailwind 無法完全處理的複雜響應式邏輯 */
@media (max-width: 768px) {
  :deep(.fc-timegrid-slot-label) {
    font-size: 10px;
  }

  :deep(.available-slot) {
    font-size: 10px !important;
    padding: 2px 4px !important;
  }
}
</style>

