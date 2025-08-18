<script setup>
import { ref, computed, watch, nextTick, onUnmounted, onMounted, toRaw } from 'vue'
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
  end: null,
  current_bookings: 0,
  max_capacity: 1,
  reservations: []
})

// Toast 通知
const toast = ref({ show: false, type: 'success', message: '' })

// 當前檢視模式
const currentView = ref('timeGridWeek')
const currentDate = ref(new Date())

// 強制更新 FullCalendar 的事件來源（完全受控刷新）
function refreshCalendarSource() {
  const cal = calendarRef.value?.getApi()
  if (!cal) return
  // 先清空，再以最新資料重建事件
  cal.removeAllEvents()
  const eventsCopy = JSON.parse(JSON.stringify(toRaw(calendarEvents.value) || []))
  cal.addEventSource(eventsCopy)
}

// 獲取可預約時段
async function fetchAvailableTimes() {
  isLoading.value = true
  try {
  // 加上快取破壞參數，避免任何中間層/瀏覽器快取導致舊資料
  const data = await apiGet('/available-times', { _: Date.now() })
    
    // 檢查返回的數據結構
    let timeData = data.data || data
    
    // 如果是分頁數據，取 data 屬性
    if (timeData && typeof timeData === 'object' && timeData.data) {
      timeData = timeData.data
    }
    
    // 確保是數組
    if (!Array.isArray(timeData)) {
      timeData = []
    }
    
    const events = timeData.map(item => {
      // 檢查預約狀態
      const reservations = item.reservations || []
      const confirmedReservations = reservations.filter(r => r.status === 'confirmed' || r.status === 'pending')
      const isFullyBooked = item.current_bookings >= item.max_capacity
      const hasReservations = confirmedReservations.length > 0
      
      const event = {
        id: String(item.id), // 確保 ID 為字串類型
        title: item.title || '可預約時段',
        start: item.start_time || item.start,
        end: item.end_time || item.end,
        description: item.description || '',
        extendedProps: {
          description: item.description || '',
          current_bookings: item.current_bookings || 0,
          max_capacity: item.max_capacity || 1,
          reservations: reservations,
          isFullyBooked: isFullyBooked,
          hasReservations: hasReservations
        }
      }
      
      // 根據預約狀態設定事件類別
      if (isFullyBooked) {
        event.classNames = ['available-slot', 'fully-booked']
      } else if (hasReservations) {
        event.classNames = ['available-slot', 'partially-booked']
      } else {
        event.classNames = ['available-slot', 'available']
      }
      
      return event
    })
    
  calendarEvents.value = events
  // 同步刷新日曆事件來源
  nextTick(refreshCalendarSource)
  } catch (err) {
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
    
    const response = await apiPost('/available-times', {
      title: slotData.title,
      description: slotData.description || '',
      start_time: formatBackendDateTime(startTime),
      end_time: formatBackendDateTime(endTime),
      max_capacity: 10
    })
    
  // 重新獲取最新數據以確保同步
  await fetchAvailableTimes()
  nextTick(refreshCalendarSource)
    
    showToast('success', '時段已建立')
  } catch (err) {
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
    
  // 重新獲取最新數據以確保同步
  await fetchAvailableTimes()
  nextTick(refreshCalendarSource)
    
    showToast('success', '時段已更新')
  } catch (err) {
    const errorMessage = err.message || '更新失敗'
    showToast('error', errorMessage)
  }
}

async function deleteSlot(id) {
  try {
  // 先立即從前端移除，提供即時回饋
    const originalEvents = [...calendarEvents.value]
    calendarEvents.value = calendarEvents.value.filter(event => String(event.id) !== String(id))

    // 同步移除 FullCalendar 畫面上的事件（確保立即消失）
    const cal = calendarRef.value?.getApi()
    if (cal) {
      const ev = cal.getEventById(String(id))
      if (ev) ev.remove()
    }
  // 若上面沒有拿到事件，保險起見重建來源
  nextTick(refreshCalendarSource)
    
    try {
      // 執行後端刪除
      await apiDelete(`/available-times/${id}`)
      
  // 重新獲取最新數據，確保與後端同步
  await fetchAvailableTimes()
  nextTick(refreshCalendarSource)
      showToast('success', '時段已刪除')
    } catch (backendError) {
      // 如果後端刪除失敗，恢復前端狀態
      calendarEvents.value = originalEvents
      
      // 檢查是否是 404 錯誤（已經被刪除）
      if (backendError.status === 404) {
        // 404 表示資源已經不存在，重新獲取資料同步狀態
  await fetchAvailableTimes()
  nextTick(refreshCalendarSource)
        showToast('success', '時段已刪除')
      } else {
        const errorMessage = backendError.message || '刪除失敗'
        showToast('error', errorMessage)
      }
    }
    
  } catch (err) {
    // 發生其他錯誤，重新獲取數據確保同步
    await fetchAvailableTimes()
    const errorMessage = err.message || '刪除失敗'
    showToast('error', errorMessage)
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
  aspectRatio: window.innerWidth <= 768 ? 0.8 : 1.35,
  
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
  eventClassNames: function(arg) {
    // 動態設定事件類名
    const props = arg.event.extendedProps
    if (props.isFullyBooked) {
      return ['available-slot', 'fully-booked']
    } else if (props.hasReservations) {
      return ['available-slot', 'partially-booked']
    } else {
      return ['available-slot', 'available']
    }
  },
  
  // 自定義事件內容
  eventContent: function(arg) {
    const props = arg.event.extendedProps
    const current = props.current_bookings || 0
    const max = props.max_capacity || 1
    
    let statusIcon = ''
    if (props.isFullyBooked) {
      statusIcon = '🔴' // 已滿
    } else if (props.hasReservations) {
      statusIcon = '🟡' // 部分預約
    } else {
      statusIcon = '🟢' // 可預約
    }
    
    // 格式化時間顯示（上午9點到~下午3點）
    function formatEventTime(start, end) {
      const startDate = new Date(start)
      const endDate = new Date(end)
      
      const formatTime = (date) => {
        const hours = date.getHours()
        const minutes = date.getMinutes()
        const period = hours < 12 ? '上午' : '下午'
        const displayHours = hours === 0 ? 12 : (hours > 12 ? hours - 12 : hours)
        const minuteStr = minutes > 0 ? `:${minutes.toString().padStart(2, '0')}` : ':00'
        return `${period}${displayHours}${minuteStr}`
      }
      
      return `${formatTime(startDate)}-${formatTime(endDate)}`
    }
    
    // 顯示時間、標題與內容（描述），圖示改為註解不顯示
    const container = document.createElement('div')
    container.className = 'event-content'

    // 註解：如需顯示圖示可改為取消註解
    // const iconEl = document.createElement('span')
    // iconEl.className = 'event-icon'
    // iconEl.textContent = statusIcon
    // container.appendChild(iconEl)

    // 時間顯示
    const timeEl = document.createElement('div')
    timeEl.className = 'event-time'
    timeEl.textContent = formatEventTime(arg.event.start, arg.event.end)
    container.appendChild(timeEl)

    // 標題顯示
    const titleEl = document.createElement('div')
    titleEl.className = 'event-title'
    titleEl.textContent = arg.event.title || ''
    container.appendChild(titleEl)

    // 描述顯示
    const desc = props.description || ''
    if (desc) {
      const descEl = document.createElement('div')
      descEl.className = 'event-desc'
      descEl.textContent = desc
      container.appendChild(descEl)
    }

    return { domNodes: [container] }
  },
  
  // 事件資料來源改由 refreshCalendarSource 主動注入，避免閉包或快取問題
  
  // 事件處理
  select: handleTimeSelect,
  eventClick: handleSlotEdit,
  eventChange: handleSlotUpdate,
  eventDrop: handleSlotDrop,
  eventResize: handleSlotResize
}))

// 不再使用 watcher 觸發全量刷新，改由 refreshCalendarSource 在資料變動後主動控制

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
  const props = event.extendedProps
  
  editForm.value = {
    id: event.id,
    title: event.title,
    description: props.description || '',
    start: event.start,
    end: event.end,
    current_bookings: props.current_bookings || 0,
    max_capacity: props.max_capacity || 1,
    reservations: props.reservations || []
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
    end: null,
    current_bookings: 0,
    max_capacity: 1,
    reservations: []
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
  <div class="min-h-screen bg-gray-50 p-2 md:p-6 overflow-hidden">
    <!-- 頁面標題和工具列 -->


    <!-- 手機優化的工具列 -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between p-2 md:p-6 border-b border-gray-200 bg-white rounded-t-xl mb-2 md:mb-6 gap-3 md:gap-0">
      <!-- 日曆導航 - 手機版優化 -->
      <div class="flex items-center justify-between md:justify-start gap-1 md:gap-2">
        <div class="flex items-center gap-1 md:gap-2">
          <button @click="goToToday" class="px-2 py-2 md:px-4 md:py-2 text-xs md:text-sm font-medium text-gray-700 bg-transparent border-0 rounded-md cursor-pointer transition-colors duration-200 hover:bg-gray-100 hover:text-gray-900 min-h-[40px] md:min-h-auto">今天</button>
          <button @click="navigatePrev" class="flex items-center justify-center w-9 h-9 md:w-9 md:h-9 border-0 rounded-full bg-transparent text-gray-500 cursor-pointer transition-colors duration-200 text-lg md:text-sm hover:bg-gray-100">‹</button>
          <button @click="navigateNext" class="flex items-center justify-center w-9 h-9 md:w-9 md:h-9 border-0 rounded-full bg-transparent text-gray-500 cursor-pointer transition-colors duration-200 text-lg md:text-sm hover:bg-gray-100">›</button>
        </div>
        <h2 class="text-sm md:text-lg font-semibold text-gray-900 mx-1 md:mx-4">{{ currentPeriod }}</h2>
      </div>
      
      <!-- 檢視切換 - 手機版優化 -->
      <div class="flex border border-gray-300 rounded-lg overflow-hidden w-full md:w-auto">
        <button 
          @click="changeView('timeGridWeek')"
          :class="[
            'flex-1 md:flex-none px-3 py-2 md:px-4 md:py-2 border-0 text-xs md:text-sm font-medium cursor-pointer transition-all duration-200 min-h-[40px] md:min-h-auto',
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
            'flex-1 md:flex-none px-3 py-2 md:px-4 md:py-2 border-0 text-xs md:text-sm font-medium cursor-pointer transition-all duration-200 min-h-[40px] md:min-h-auto',
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
            'flex-1 md:flex-none px-3 py-2 md:px-4 md:py-2 border-0 text-xs md:text-sm font-medium cursor-pointer transition-all duration-200 min-h-[40px] md:min-h-auto',
            currentView === 'dayGridMonth' 
              ? 'bg-blue-500 text-white' 
              : 'bg-white text-gray-500 hover:bg-gray-50 hover:text-gray-700'
          ]"
        >
          月
        </button>
      </div>
    </div>

    <!-- 主要日曆區域 - 修復手機版顯示 -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden relative">
      <div v-if="isLoading" class="absolute inset-0 flex flex-col items-center justify-center bg-white bg-opacity-90 z-10">
        <div class="w-8 h-8 border-3 border-gray-200 border-t-blue-500 rounded-full animate-spin"></div>
        <p class="mt-3 text-gray-500 text-sm">載入中...</p>
      </div>
      
      <FullCalendar 
        ref="calendarRef"
        :options="calendarOptions" 
        class="p-2 md:p-6"
      />
    </div>

    <!-- 手機優化的快速建立氣泡 -->
    <div v-if="showQuickCreate" class="fixed bg-white border border-gray-300 rounded-xl shadow-lg p-4 md:p-5 w-[calc(100vw-2rem)] md:min-w-80 md:w-auto z-[1000] top-4 left-4 right-4 md:top-1/2 md:left-1/2 md:right-auto md:transform md:-translate-x-1/2 md:-translate-y-1/2 max-h-[calc(100vh-2rem)] overflow-y-auto">
      <input 
        ref="quickInput"
        v-model="quickSlotTitle"
        placeholder="可預約時段"
        @keyup.enter="createQuickSlot"
        @keyup.escape="cancelQuickCreate"
        class="w-full border border-gray-200 rounded-lg outline-none text-base font-medium text-gray-900 mb-4 bg-white placeholder-gray-400 px-4 py-3 md:py-2 md:border-0 md:bg-transparent focus:border-blue-500 md:focus:border-0"
      />
      <div class="text-gray-600 text-sm mb-6 md:mb-4 font-medium p-3 bg-gray-50 rounded-lg md:p-0 md:bg-transparent">
        {{ formatTimeRange(selectedTime) }}
      </div>
      <div class="flex flex-col md:flex-row justify-end gap-3 md:gap-2">
        <button @click="createQuickSlot" class="w-full md:w-auto bg-blue-500 text-white border-0 rounded-lg md:rounded-md px-6 py-4 md:px-4 md:py-2 text-base md:text-sm font-medium cursor-pointer transition-all duration-200 hover:bg-blue-600 hover:-translate-y-0.5 hover:shadow-lg hover:shadow-blue-500/40 order-2 md:order-1">儲存</button>
        <button @click="showDetailModal" class="w-full md:w-auto bg-transparent text-blue-500 border border-blue-200 md:border-gray-300 rounded-lg md:rounded-md px-6 py-4 md:px-4 md:py-2 text-base md:text-sm font-medium cursor-pointer transition-all duration-200 hover:bg-blue-50 md:hover:bg-gray-50 hover:border-blue-500 order-1 md:order-2">更多選項</button>
      </div>
    </div>

    <!-- 手機優化的編輯 Modal -->
    <div v-if="showEditModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-start md:items-center justify-center z-[1000] p-0 md:p-4">
      <div class="bg-white rounded-t-xl md:rounded-xl shadow-2xl w-full md:w-full md:max-w-lg max-h-screen md:max-h-[90vh] overflow-hidden mt-auto md:mt-0">
        <!-- 手機優化的標題列 -->
        <div class="flex items-center justify-between px-4 md:px-6 pt-4 md:pt-6 pb-4 border-b border-gray-200 sticky top-0 bg-white z-10">
          <h3 class="text-lg md:text-lg font-semibold text-gray-900 m-0">{{ editForm.id ? '編輯' : '新增' }}可預約時段</h3>
          <button @click="closeEditModal" class="w-10 h-10 md:w-8 md:h-8 border-0 rounded-full bg-transparent text-gray-500 text-xl cursor-pointer flex items-center justify-center transition-all duration-200 hover:bg-gray-100 hover:text-gray-700">×</button>
        </div>
        
        <!-- 手機優化的表單內容 -->
        <div class="p-4 md:p-6 overflow-y-auto max-h-[calc(100vh-8rem)] md:max-h-none">
          <div class="mb-6 md:mb-5">
            <label class="block text-sm font-medium text-gray-900 mb-3 md:mb-2">時段名稱</label>
            <input 
              v-model="editForm.title" 
              placeholder="例如：諮詢服務"
              class="w-full border border-gray-300 rounded-lg md:rounded-md p-4 md:p-3 text-base md:text-sm text-gray-900 transition-all duration-200 bg-white focus:outline-none focus:border-blue-500 focus:ring-3 focus:ring-blue-500/10 min-h-[48px] md:min-h-auto"
            />
          </div>
          
          <div class="mb-6 md:mb-5">
            <label class="block text-sm font-medium text-gray-900 mb-3 md:mb-2">時間</label>
            <div class="p-4 md:p-3 bg-gray-50 rounded-lg md:rounded-md text-gray-600 md:text-gray-500 text-base md:text-sm font-medium border border-gray-200 min-h-[48px] md:min-h-auto flex items-center">
              {{ formatDateTime(editForm.start) }} - {{ formatDateTime(editForm.end) }}
            </div>
          </div>
          
          <div class="mb-6 md:mb-5">
            <label class="block text-sm font-medium text-gray-900 mb-3 md:mb-2">備註 <span class="font-normal text-gray-500">(選填)</span></label>
            <textarea 
              v-model="editForm.description"
              rows="3"
              placeholder="額外說明..."
              class="w-full border border-gray-300 rounded-lg md:rounded-md p-4 md:p-3 text-base md:text-sm text-gray-900 transition-all duration-200 bg-white resize-y min-h-[80px] md:min-h-[60px] focus:outline-none focus:border-blue-500 focus:ring-3 focus:ring-blue-500/10"
            ></textarea>
          </div>
        </div>
        
        <!-- 手機優化的操作按鈕 -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between px-4 md:px-6 pb-4 md:pb-6 pt-4 border-t border-gray-200 sticky bottom-0 bg-white gap-3 md:gap-0">
          <button 
            v-if="editForm.id" 
            @click="deleteCurrentSlot" 
            class="w-full md:w-auto bg-transparent text-red-600 border border-red-200 rounded-lg md:rounded-md px-6 py-4 md:px-4 md:py-2 text-base md:text-sm font-medium cursor-pointer transition-all duration-200 hover:bg-red-50 hover:border-red-600 order-3 md:order-1"
          >
            刪除時段
          </button>
          <div class="flex flex-col md:flex-row gap-3 md:gap-3 md:ml-auto w-full md:w-auto">
            <button @click="closeEditModal" class="w-full md:w-auto bg-transparent text-gray-500 border border-gray-300 rounded-lg md:rounded-md px-6 py-4 md:px-4 md:py-2 text-base md:text-sm font-medium cursor-pointer transition-all duration-200 hover:bg-gray-50 hover:text-gray-700 hover:border-gray-400 order-2">取消</button>
            <button @click="saveSlot" class="w-full md:w-auto bg-blue-500 text-white border-0 rounded-lg md:rounded-md px-6 py-4 md:px-4 md:py-2 text-base md:text-sm font-medium cursor-pointer transition-all duration-200 hover:bg-blue-600 hover:-translate-y-0.5 hover:shadow-lg hover:shadow-blue-500/40 order-1">{{ editForm.id ? '儲存更改' : '建立時段' }}</button>
          </div>
        </div>
      </div>
    </div>

    <!-- 手機優化的 Toast 通知 -->
    <div v-if="toast.show" :class="[
      'fixed top-4 left-4 right-4 md:top-6 md:right-6 md:left-auto px-4 py-4 md:py-3 rounded-lg text-base md:text-sm font-medium shadow-lg z-[1100] animate-slide-in',
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

/* 可預約時段樣式 - 優化 UI/UX */
:deep(.available-slot) {
  border: none !important;
  border-radius: 6px !important;
  color: #ffffff !important;
  font-size: 12px !important;
  font-weight: 500 !important;
  padding: 6px 8px !important;
  cursor: pointer !important;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1) !important;
  transition: all 0.2s ease !important;
  min-height: 44px !important;
  display: flex !important;
  align-items: flex-start !important;
  overflow: visible !important;
  word-wrap: break-word !important;
}

/* 事件內容樣式 - 優化 UI/UX */
:deep(.event-content) {
  display: flex !important;
  flex-direction: column !important;
  align-items: flex-start !important;
  gap: 1px !important;
  white-space: normal !important;
  overflow: visible !important;
  padding: 3px 6px !important;
  height: 100% !important;
  box-sizing: border-box !important;
  justify-content: flex-start !important;
  word-wrap: break-word !important;
}

:deep(.event-time) {
  font-size: 10px !important;
  opacity: 0.85 !important;
  font-weight: 500 !important;
  line-height: 1.3 !important;
  color: rgba(255, 255, 255, 0.9) !important;
  letter-spacing: 0.02em !important;
  white-space: normal !important;
  word-wrap: break-word !important;
  width: 100% !important;
}

:deep(.event-icon) {
  font-size: 12px !important;
  line-height: 1 !important;
}

:deep(.event-title) {
  font-size: 12px !important;
  font-weight: 600 !important;
  line-height: 1.3 !important;
  overflow: visible !important;
  text-overflow: unset !important;
  width: 100% !important;
  color: rgba(255, 255, 255, 1) !important;
  letter-spacing: 0.01em !important;
  margin-top: 1px !important;
  white-space: normal !important;
  word-wrap: break-word !important;
  word-break: break-word !important;
}

:deep(.event-booking) {
  font-size: 10px !important;
  opacity: 0.8 !important;
  font-weight: 600 !important;
}

/* 事件描述樣式 - 優化可讀性 */
:deep(.event-desc) {
  font-size: 10px !important;
  opacity: 0.75 !important;
  font-weight: 400 !important;
  line-height: 1.2 !important;
  white-space: normal !important;
  overflow: visible !important;
  text-overflow: unset !important;
  width: 100% !important;
  color: rgba(255, 255, 255, 0.8) !important;
  font-style: italic !important;
  margin-top: 1px !important;
  word-wrap: break-word !important;
  word-break: break-word !important;
}

/* 完全可預約（無預約） */
:deep(.available-slot.available) {
  background-color: #3b82f6 !important;
}

:deep(.available-slot.available:hover) {
  background-color: #2563eb !important;
  box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4) !important;
  transform: translateY(-1px) !important;
}

/* 部分已預約 */
:deep(.available-slot.partially-booked) {
  background-color: #f59e0b !important;
}

:deep(.available-slot.partially-booked:hover) {
  background-color: #d97706 !important;
  box-shadow: 0 4px 12px rgba(245, 158, 11, 0.4) !important;
  transform: translateY(-1px) !important;
}

/* 完全預約滿 */
:deep(.available-slot.fully-booked) {
  background-color: #ef4444 !important;
  opacity: 0.8 !important;
  cursor: not-allowed !important;
}

:deep(.available-slot.fully-booked:hover) {
  background-color: #dc2626 !important;
  box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4) !important;
  transform: none !important;
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

/* 響應式設計 - 修復手機版顯示問題 */
@media (max-width: 768px) {
  :deep(.fc) {
    min-height: 500px !important;
    height: auto !important;
  }

  :deep(.fc-timegrid-slot-label) {
    font-size: 10px;
    font-weight: 500;
    padding: 4px 2px;
    width: 45px !important;
  }

  :deep(.available-slot) {
    font-size: 12px !important;
    padding: 6px 4px !important;
    min-height: 60px !important;
    border-radius: 6px !important;
    line-height: 1.2 !important;
  }
  
  :deep(.event-time) {
    font-size: 10px !important;
    font-weight: 600 !important;
    opacity: 1 !important;
    margin-bottom: 1px !important;
    white-space: normal !important;
    word-wrap: break-word !important;
  }
  
  :deep(.event-title) {
    font-size: 11px !important;
    font-weight: 700 !important;
    line-height: 1.1 !important;
    margin-top: 1px !important;
    margin-bottom: 0px !important;
    white-space: normal !important;
    word-wrap: break-word !important;
    overflow: visible !important;
  }
  
  :deep(.event-desc) {
    font-size: 9px !important;
    opacity: 0.9 !important;
    margin-top: 1px !important;
    white-space: normal !important;
    word-wrap: break-word !important;
    overflow: visible !important;
  }

  /* 手機版日曆格子優化 */
  :deep(.fc-timegrid-slot) {
    height: 60px !important;
    border-color: #e5e7eb;
  }

  :deep(.fc-col-header-cell) {
    padding: 8px 4px !important;
    font-size: 11px !important;
  }

  :deep(.fc-scrollgrid) {
    border-radius: 8px !important;
  }

  /* 手機版事件內容優化 */
  :deep(.event-content) {
    padding: 4px 6px !important;
    gap: 1px !important;
    justify-content: flex-start !important;
    min-height: 56px !important;
    overflow: visible !important;
    white-space: normal !important;
    word-wrap: break-word !important;
  }

  /* 手機版選擇區域優化 */
  :deep(.fc-highlight) {
    background-color: rgba(59, 130, 246, 0.15) !important;
    border-radius: 6px !important;
  }

  :deep(.fc-select-mirror) {
    background-color: rgba(59, 130, 246, 0.4) !important;
    border-radius: 6px !important;
    border: 2px solid #3b82f6 !important;
  }

  /* 手機版時間軸優化 */
  :deep(.fc-timegrid-axis) {
    width: 45px !important;
  }

  /* 確保手機版正常顯示 */
  :deep(.fc-view-harness) {
    height: auto !important;
    min-height: 500px !important;
  }

  :deep(.fc-scroller-harness) {
    overflow: visible !important;
  }

  /* 確保事件文字不截斷 */
  :deep(.fc-event-title) {
    white-space: normal !important;
    word-wrap: break-word !important;
    overflow: visible !important;
  }
  
  :deep(.fc-event) {
    overflow: visible !important;
  }
  
  :deep(.fc-event-main) {
    overflow: visible !important;
  }
}

/* 極小螢幕特別優化 (320-390px) */
@media (max-width: 390px) {
  :deep(.fc-timegrid-slot-label) {
    font-size: 9px;
    width: 40px !important;
    padding: 2px 1px;
  }

  :deep(.available-slot) {
    font-size: 11px !important;
    padding: 4px 3px !important;
    min-height: 50px !important;
  }
  
  :deep(.event-time) {
    font-size: 9px !important;
  }
  
  :deep(.event-title) {
    font-size: 10px !important;
  }

  :deep(.fc-timegrid-slot) {
    height: 50px !important;
  }

  :deep(.fc-timegrid-axis) {
    width: 40px !important;
  }

  :deep(.event-content) {
    padding: 3px 4px !important;
    min-height: 46px !important;
    overflow: visible !important;
    white-space: normal !important;
    word-wrap: break-word !important;
  }
}
</style>

