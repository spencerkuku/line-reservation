<script setup>
import { ref, computed, watch, nextTick, onUnmounted, onMounted, toRaw } from 'vue'
import FullCalendar from '@fullcalendar/vue3'
import dayGridPlugin from '@fullcalendar/daygrid'
import timeGridPlugin from '@fullcalendar/timegrid'
import interactionPlugin from '@fullcalendar/interaction'
import zhTwLocale from '@fullcalendar/core/locales/zh-tw'
import { apiGet, apiPost, apiPut, apiDelete } from '../utils/api.js'
import { 
  Dialog, 
  DialogPanel, 
  DialogTitle, 
  TransitionRoot, 
  TransitionChild 
} from '@headlessui/vue'
import {
  CalendarIcon,
  ChevronLeftIcon,
  ChevronRightIcon,
  ClockIcon,
  PlusIcon,
  XMarkIcon,
  CheckCircleIcon,
  XCircleIcon,
  UserGroupIcon,
  ViewColumnsIcon
} from '@heroicons/vue/24/outline'

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

// 視圖選單狀態
const showViewMenu = ref(false)
function toggleViewMenu() {
  showViewMenu.value = !showViewMenu.value
}

// 用於手動時間輸入的格式化函數
const formatDateTimeLocal = (date) => {
  if (!date) return ''
  const d = new Date(date)
  const year = d.getFullYear()
  const month = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  const hours = String(d.getHours()).padStart(2, '0')
  const minutes = String(d.getMinutes()).padStart(2, '0')
  return `${year}-${month}-${day}T${hours}:${minutes}`
}

const parseDateTimeLocal = (str) => {
  if (!str) return null
  return new Date(str)
}

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
  // 響應式 aspect ratio - 針對不同裝置優化
  aspectRatio: (() => {
    const width = window.innerWidth
    if (width <= 390) return 0.7  // 極小手機
    if (width <= 767) return 0.85 // 手機
    if (width <= 1024) return 1.1 // 平板
    return 1.35 // 桌面
  })(),
  
  // 簡化功能
  weekNumbers: false,
  nowIndicator: true,
  allDaySlot: false,
  
  // 核心互動
  selectable: true,
  selectMirror: true,
  editable: true,
  eventStartEditable: true,  // 允許拖動移動事件
  eventDurationEditable: false,  // 禁止調整大小
  
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
  eventDrop: handleSlotDrop
  // eventResize 已移除 - 不允許調整大小
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
  
  // 暫時移除營業時間限制,允許全天時段
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

// 手機版新增時段按鈕功能
function openManualCreateModal() {
  // 設定預設時間為現在時間往後1小時
  const now = new Date()
  const startTime = new Date(now.getTime() + 60 * 60 * 1000) // 1小時後
  startTime.setMinutes(0, 0, 0) // 整點
  
  const endTime = new Date(startTime.getTime() + 60 * 60 * 1000) // 再1小時後
  
  editForm.value = {
    id: null,
    title: '可預約時段',
    description: '',
    start: startTime,
    end: endTime,
    current_bookings: 0,
    max_capacity: 1,
    reservations: []
  }
  showEditModal.value = true
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

// 調整大小處理 - 已停用,僅允許拖動移動
// async function handleSlotResize(info) {
//   const start = new Date(info.event.start)
//   const end = new Date(info.event.end)
//   const durationMinutes = Math.floor((end.getTime() - start.getTime()) / (1000 * 60))
//   
//   if (durationMinutes < 30) {
//     info.revert()
//     showToast('error', '時段不得少於30分鐘')
//     return
//   }
//   
//   if (durationMinutes > 480) {
//     info.revert()
//     showToast('error', '時段不得超過8小時')
//     return
//   }
//   
//   await handleSlotUpdate(info)
// }

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

// 偵測裝置類型
const isMobile = computed(() => window.innerWidth <= 767)
const isTablet = computed(() => window.innerWidth > 767 && window.innerWidth <= 1024)
const isTouchDevice = computed(() => 'ontouchstart' in window || navigator.maxTouchPoints > 0)

// 組件載入時獲取數據並導航到今天
onMounted(() => {
  fetchAvailableTimes()
  
  // 等待 FullCalendar 初始化完成後導航到今天
  nextTick(() => {
    setTimeout(() => {
      goToToday()
    }, 100)
  })

  // 針對觸控裝置優化
  if (isTouchDevice.value) {
    // 禁用雙擊縮放
    document.addEventListener('touchstart', (e) => {
      if (e.touches.length > 1) {
        e.preventDefault()
      }
    }, { passive: false })

    // 改善觸控滾動
    const scrollElements = document.querySelectorAll('.fc-scroller')
    scrollElements.forEach(el => {
      el.style.webkitOverflowScrolling = 'touch'
      el.style.overflowY = 'auto'
    })
  }
})
</script>

<template>
  <div class="min-h-screen bg-gradient-to-br from-gray-50 to-gray-100 p-0 md:p-6 overflow-hidden">
    <!-- 專業級工具列 - Google Calendar 風格 -->
    <div class="sticky top-0 z-[5] bg-white border-b border-gray-200 shadow-sm">
      <div class="max-w-[1800px] mx-auto px-4 md:px-6 py-3 md:py-4">
        <div class="flex items-center justify-between gap-4">
          <!-- 左側：Logo + 導航控制 -->
          <div class="flex items-center gap-3 md:gap-4">
            <!-- Logo/選單圖示 -->
            <div class="flex items-center gap-3">
              <h1 class="hidden md:block text-xl font-bold text-gray-900">可預約時段</h1>
            </div>
            
            <!-- 導航按鈕組 -->
            <div class="flex items-center gap-2">
              <button 
                @click="goToToday" 
                class="px-3 py-2 md:px-4 md:py-2 text-sm font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 hover:border-indigo-400 hover:text-indigo-600 transition-all duration-200 active:scale-95 touch-manipulation shadow-sm"
              >
                今天
              </button>
              <button 
                @click="navigatePrev" 
                class="p-2 rounded-lg text-gray-600 hover:bg-gray-100 hover:text-gray-900 transition-all duration-200 active:scale-95 touch-manipulation"
              >
                <ChevronLeftIcon class="w-5 h-5" />
              </button>
              <button 
                @click="navigateNext" 
                class="p-2 rounded-lg text-gray-600 hover:bg-gray-100 hover:text-gray-900 transition-all duration-200 active:scale-95 touch-manipulation"
              >
                <ChevronRightIcon class="w-5 h-5" />
              </button>
            </div>
            
            <!-- 當前日期顯示 -->
            <h2 class="text-lg md:text-xl font-bold text-gray-900">{{ currentPeriod }}</h2>
          </div>
          
          <!-- 右側：視圖切換 + 設定 -->
          <div class="flex items-center gap-3">
            <!-- 視圖切換 - Segmented Control -->
            <div class="hidden md:flex bg-gray-100 rounded-lg p-1 gap-1">
              <button 
                @click="changeView('timeGridDay')"
                :class="[
                  'px-4 py-2 text-sm font-semibold rounded-md transition-all duration-200',
                  currentView === 'timeGridDay' 
                    ? 'bg-white text-gray-900 shadow-sm' 
                    : 'text-gray-600 hover:text-gray-900'
                ]"
              >
                日
              </button>
              <button 
                @click="changeView('timeGridWeek')"
                :class="[
                  'px-4 py-2 text-sm font-semibold rounded-md transition-all duration-200',
                  currentView === 'timeGridWeek' 
                    ? 'bg-white text-gray-900 shadow-sm' 
                    : 'text-gray-600 hover:text-gray-900'
                ]"
              >
                週
              </button>
              <button 
                @click="changeView('dayGridMonth')"
                :class="[
                  'px-4 py-2 text-sm font-semibold rounded-md transition-all duration-200',
                  currentView === 'dayGridMonth' 
                    ? 'bg-white text-gray-900 shadow-sm' 
                    : 'text-gray-600 hover:text-gray-900'
                ]"
              >
                月
              </button>
            </div>
            
            <!-- 手機版視圖選擇器 -->
            <button 
              @click="toggleViewMenu"
              class="md:hidden p-2 rounded-lg text-gray-600 hover:bg-gray-100 transition-all duration-200"
            >
              <ViewColumnsIcon class="w-6 h-6" />
            </button>
          </div>
        </div>
      </div>
    </div>
    
    <!-- 手機版視圖選擇 Menu -->
    <Transition
      enter-active-class="transition ease-out duration-200"
      enter-from-class="opacity-0 -translate-y-2"
      enter-to-class="opacity-100 translate-y-0"
      leave-active-class="transition ease-in duration-150"
      leave-from-class="opacity-100 translate-y-0"
      leave-to-class="opacity-0 -translate-y-2"
    >
      <div v-if="showViewMenu" class="md:hidden absolute top-[60px] right-4 bg-white rounded-xl shadow-xl border border-gray-200 overflow-hidden z-10 min-w-[160px]">
        <button 
          @click="changeView('timeGridDay'); toggleViewMenu()"
          :class="[
            'w-full px-4 py-3 text-left text-sm font-medium transition-colors',
            currentView === 'timeGridDay' ? 'bg-indigo-50 text-indigo-600' : 'text-gray-700 hover:bg-gray-50'
          ]"
        >
          日檢視
        </button>
        <button 
          @click="changeView('timeGridWeek'); toggleViewMenu()"
          :class="[
            'w-full px-4 py-3 text-left text-sm font-medium transition-colors border-t border-gray-100',
            currentView === 'timeGridWeek' ? 'bg-indigo-50 text-indigo-600' : 'text-gray-700 hover:bg-gray-50'
          ]"
        >
          週檢視
        </button>
        <button 
          @click="changeView('dayGridMonth'); toggleViewMenu()"
          :class="[
            'w-full px-4 py-3 text-left text-sm font-medium transition-colors border-t border-gray-100',
            currentView === 'dayGridMonth' ? 'bg-indigo-50 text-indigo-600' : 'text-gray-700 hover:bg-gray-50'
          ]"
        >
          月檢視
        </button>
      </div>
    </Transition>

    <!-- 主要日曆區域 -->
    <div class="max-w-[1800px] mx-auto px-0 md:px-6 py-4">
      <div class="bg-white rounded-none md:rounded-2xl shadow-lg border-0 md:border border-gray-200 overflow-hidden relative">
        <!-- 骨架屏 Loading -->
        <div v-if="isLoading" class="absolute inset-0 bg-white bg-opacity-95 z-10 backdrop-blur-sm">
          <div class="flex flex-col items-center justify-center h-full">
            <div class="w-12 h-12 border-4 border-gray-200 border-t-indigo-600 rounded-full animate-spin"></div>
            <p class="mt-4 text-gray-600 text-sm font-medium">載入中...</p>
          </div>
        </div>
        
        <FullCalendar 
          ref="calendarRef"
          :options="calendarOptions" 
          class="p-4 md:p-6 calendar-container"
        />
      </div>
    </div>

    <!-- 精緻的快速建立對話框 - Apple 風格 -->
    <TransitionRoot appear :show="showQuickCreate" as="template">
      <Dialog as="div" @close="cancelQuickCreate" class="relative z-50">
        <TransitionChild
          as="template"
          enter="duration-300 ease-out"
          enter-from="opacity-0"
          enter-to="opacity-100"
          leave="duration-200 ease-in"
          leave-from="opacity-100"
          leave-to="opacity-0"
        >
          <div class="fixed inset-0 bg-black/40 backdrop-blur-sm" />
        </TransitionChild>

        <div class="fixed inset-0 overflow-y-auto">
          <div class="flex min-h-full items-center justify-center p-4">
            <TransitionChild
              as="template"
              enter="duration-300 ease-out"
              enter-from="opacity-0 scale-95"
              enter-to="opacity-100 scale-100"
              leave="duration-200 ease-in"
              leave-from="opacity-100 scale-100"
              leave-to="opacity-0 scale-95"
            >
              <DialogPanel class="w-full max-w-md transform overflow-hidden rounded-2xl bg-white shadow-2xl transition-all">
                <div class="p-6">
                  <DialogTitle class="text-xl font-bold text-gray-900 mb-4">
                    新增可預約時段
                  </DialogTitle>
                  
                  <div class="space-y-4">
                    <input 
                      ref="quickInput"
                      v-model="quickSlotTitle"
                      placeholder="時段名稱"
                      @keyup.enter="createQuickSlot"
                      class="w-full px-4 py-3 text-base font-medium text-gray-900 bg-gray-50 border-0 border-b-2 border-gray-200 rounded-lg focus:outline-none focus:border-indigo-600 focus:bg-white focus:ring-4 focus:ring-indigo-50 transition-all duration-200"
                    />
                    
                    <div class="flex items-center gap-3 px-4 py-3 bg-indigo-50 rounded-xl">
                      <ClockIcon class="w-5 h-5 text-indigo-600" />
                      <span class="text-sm font-medium text-gray-700">{{ formatTimeRange(selectedTime) }}</span>
                    </div>
                  </div>
                  
                  <div class="flex gap-3 mt-6">
                    <button 
                      @click="createQuickSlot" 
                      class="flex-1 bg-gradient-to-r from-indigo-600 to-indigo-500 text-white px-6 py-3 rounded-xl font-semibold hover:from-indigo-700 hover:to-indigo-600 hover:shadow-lg hover:shadow-indigo-500/30 transition-all duration-200 active:scale-95"
                    >
                      儲存
                    </button>
                    <button 
                      @click="showDetailModal" 
                      class="px-6 py-3 text-indigo-600 font-semibold border-2 border-indigo-200 rounded-xl hover:bg-indigo-50 hover:border-indigo-300 transition-all duration-200 active:scale-95"
                    >
                      更多選項
                    </button>
                  </div>
                </div>
              </DialogPanel>
            </TransitionChild>
          </div>
        </div>
      </Dialog>
    </TransitionRoot>

    <!-- iOS 風格的浮動新增按鈕 -->
    <button 
      @click="openManualCreateModal"
      class="lg:hidden fixed bottom-8 right-6 w-16 h-16 bg-gradient-to-br from-indigo-600 to-indigo-500 text-white rounded-2xl shadow-2xl flex items-center justify-center z-20 cursor-pointer transition-all duration-300 hover:shadow-indigo-500/50 hover:scale-105 active:scale-95 touch-manipulation group"
    >
      <PlusIcon class="w-8 h-8 group-hover:rotate-90 transition-transform duration-300" />
    </button>

    <!-- 精緻的編輯 Modal - 全新設計 -->
    <TransitionRoot appear :show="showEditModal" as="template">
      <Dialog as="div" @close="closeEditModal" class="relative z-40">
        <TransitionChild
          as="template"
          enter="duration-300 ease-out"
          enter-from="opacity-0"
          enter-to="opacity-100"
          leave="duration-200 ease-in"
          leave-from="opacity-100"
          leave-to="opacity-0"
        >
          <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" />
        </TransitionChild>

        <div class="fixed inset-0 overflow-y-auto">
          <div class="flex min-h-full items-end md:items-center justify-center">
            <TransitionChild
              as="template"
              enter="duration-300 ease-out"
              enter-from="opacity-0 translate-y-full md:translate-y-0 md:scale-95"
              enter-to="opacity-100 translate-y-0 md:scale-100"
              leave="duration-200 ease-in"
              leave-from="opacity-100 translate-y-0 md:scale-100"
              leave-to="opacity-0 translate-y-full md:translate-y-0 md:scale-95"
            >
              <DialogPanel class="w-full md:max-w-2xl transform overflow-hidden rounded-t-3xl md:rounded-2xl bg-white shadow-2xl transition-all">
                <!-- 頂部拖動指示器 (僅手機版) -->
                <div class="md:hidden w-12 h-1.5 bg-gray-300 rounded-full mx-auto mt-3 mb-2"></div>
                
                <!-- 標題列 -->
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                  <DialogTitle class="text-xl font-bold text-gray-900">
                    {{ editForm.id ? '編輯時段' : '新增時段' }}
                  </DialogTitle>
                  <button 
                    @click="closeEditModal" 
                    class="p-2 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-all duration-200"
                  >
                    <XMarkIcon class="w-6 h-6" />
                  </button>
                </div>
                
                <!-- 表單內容 -->
                <div class="p-6 max-h-[70vh] md:max-h-[600px] overflow-y-auto">
                  <div class="space-y-6">
                    <!-- 時段名稱 -->
                    <div>
                      <label class="block text-sm font-bold text-gray-900 mb-2">
                        時段名稱 <span class="text-red-500">*</span>
                      </label>
                      <div class="relative">
                        <input 
                          v-model="editForm.title" 
                          placeholder="例如：諮詢服務"
                          class="w-full px-4 py-3 text-base font-medium text-gray-900 bg-gray-50 border-2 border-gray-200 rounded-xl focus:outline-none focus:border-indigo-600 focus:bg-white focus:ring-4 focus:ring-indigo-50 transition-all duration-200 placeholder:text-gray-400"
                        />
                      </div>
                    </div>
                    
                    <!-- 時間設定 -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                      <div>
                        <label class="block text-sm font-bold text-gray-900 mb-2">
                          開始時間 <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                          <input 
                            type="datetime-local" 
                            :value="formatDateTimeLocal(editForm.start)"
                            @input="editForm.start = parseDateTimeLocal($event.target.value)"
                            class="w-full px-4 py-3 text-sm font-medium text-gray-900 bg-gray-50 border-2 border-gray-200 rounded-xl focus:outline-none focus:border-indigo-600 focus:bg-white focus:ring-4 focus:ring-indigo-50 transition-all duration-200"
                          />
                        </div>
                      </div>
                      
                      <div>
                        <label class="block text-sm font-bold text-gray-900 mb-2">
                          結束時間 <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                          <input 
                            type="datetime-local" 
                            :value="formatDateTimeLocal(editForm.end)"
                            @input="editForm.end = parseDateTimeLocal($event.target.value)"
                            class="w-full px-4 py-3 text-sm font-medium text-gray-900 bg-gray-50 border-2 border-gray-200 rounded-xl focus:outline-none focus:border-indigo-600 focus:bg-white focus:ring-4 focus:ring-indigo-50 transition-all duration-200"
                          />
                        </div>
                      </div>
                    </div>
                    
                    <!-- 備註 -->
                    <div>
                      <label class="block text-sm font-bold text-gray-900 mb-2">
                        備註 <span class="text-xs font-normal text-gray-500">(選填)</span>
                      </label>
                      <textarea 
                        v-model="editForm.description"
                        rows="4"
                        placeholder="新增額外說明或注意事項..."
                        class="w-full px-4 py-3 text-sm font-medium text-gray-900 bg-gray-50 border-2 border-gray-200 rounded-xl focus:outline-none focus:border-indigo-600 focus:bg-white focus:ring-4 focus:ring-indigo-50 transition-all duration-200 resize-none placeholder:text-gray-400"
                      ></textarea>
                    </div>
                    
                    <!-- 預約資訊（編輯時顯示） -->
                    <div v-if="editForm.id && editForm.reservations?.length > 0" class="p-4 bg-indigo-50 rounded-xl border-2 border-indigo-100">
                      <h4 class="text-sm font-bold text-indigo-900 mb-3 flex items-center gap-2">
                        <UserGroupIcon class="w-5 h-5" />
                        目前預約 ({{ editForm.current_bookings }}/{{ editForm.max_capacity }})
                      </h4>
                      <div class="space-y-2 max-h-40 overflow-y-auto">
                        <div 
                          v-for="reservation in editForm.reservations" 
                          :key="reservation.id"
                          class="px-3 py-2 bg-white rounded-lg text-sm"
                        >
                          <div class="font-medium text-gray-900">{{ reservation.customer_name }}</div>
                          <div class="text-xs text-gray-600">{{ reservation.customer_phone }}</div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
                
                <!-- 底部操作按鈕 -->
                <div class="flex items-center justify-between px-6 py-4 border-t border-gray-100 bg-gray-50">
                  <button 
                    v-if="editForm.id" 
                    @click="deleteCurrentSlot" 
                    class="px-6 py-3 text-red-600 font-semibold border-2 border-red-200 bg-white rounded-xl hover:bg-red-50 hover:border-red-300 transition-all duration-200 active:scale-95"
                  >
                    刪除時段
                  </button>
                  <div class="flex gap-3 ml-auto">
                    <button 
                      @click="closeEditModal" 
                      class="px-6 py-3 text-gray-700 font-semibold border-2 border-gray-200 bg-white rounded-xl hover:bg-gray-100 hover:border-gray-300 transition-all duration-200 active:scale-95"
                    >
                      取消
                    </button>
                    <button 
                      @click="saveSlot" 
                      class="px-6 py-3 bg-gradient-to-r from-indigo-600 to-indigo-500 text-white font-semibold rounded-xl hover:from-indigo-700 hover:to-indigo-600 hover:shadow-lg hover:shadow-indigo-500/30 transition-all duration-200 active:scale-95"
                    >
                      {{ editForm.id ? '儲存更改' : '建立時段' }}
                    </button>
                  </div>
                </div>
              </DialogPanel>
            </TransitionChild>
          </div>
        </div>
      </Dialog>
    </TransitionRoot>

    <!-- 精緻的 Toast 通知 - 從右側滑入 -->
    <Transition
      enter-active-class="transition ease-out duration-300 transform"
      enter-from-class="translate-x-full opacity-0"
      enter-to-class="translate-x-0 opacity-100"
      leave-active-class="transition ease-in duration-200 transform"
      leave-from-class="translate-x-0 opacity-100"
      leave-to-class="translate-x-full opacity-0"
    >
      <div 
        v-if="toast.show" 
        :class="[
          'fixed top-4 right-4 md:top-6 md:right-6 px-5 py-4 rounded-xl text-sm font-semibold shadow-2xl z-20 max-w-sm flex items-center gap-3',
          toast.type === 'success' 
            ? 'bg-emerald-50 text-emerald-800 border-2 border-emerald-200' 
            : 'bg-red-50 text-red-800 border-2 border-red-200'
        ]"
      >
        <component 
          :is="toast.type === 'success' ? CheckCircleIcon : XCircleIcon" 
          class="w-6 h-6 flex-shrink-0"
        />
        <span>{{ toast.message }}</span>
      </div>
    </Transition>
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

@keyframes slide-up {
  from {
    transform: translateY(100%);
    opacity: 0;
  }
  to {
    transform: translateY(0);
    opacity: 1;
  }
}

.animate-slide-up {
  animation: slide-up 0.3s cubic-bezier(0.32, 0.72, 0, 1);
}

/* 觸控優化 - 全局 */
@media (max-width: 1024px) {
  /* 所有按鈕最小觸控區域 44x44px (Apple HIG 建議) */
  button {
    min-height: 44px !important;
    min-width: 44px !important;
  }

  /* 輸入框最小高度 */
  input[type="text"],
  input[type="datetime-local"],
  input[type="date"],
  input[type="time"],
  textarea {
    min-height: 48px !important;
    font-size: 16px !important; /* 防止 iOS Safari 自動縮放 */
  }

  /* 改善觸控回饋 */
  button:active,
  .cursor-pointer:active {
    opacity: 0.7 !important;
    transform: scale(0.98) !important;
    transition: all 0.1s ease !important;
  }

  /* Modal 底部固定操作欄 */
  .sticky.bottom-0 {
    box-shadow: 0 -4px 12px rgba(0, 0, 0, 0.1) !important;
  }

  /* 改善滾動體驗 */
  * {
    -webkit-overflow-scrolling: touch !important;
  }

  /* 滾動條樣式（Webkit） */
  ::-webkit-scrollbar {
    width: 8px !important;
    height: 8px !important;
  }

  ::-webkit-scrollbar-thumb {
    background-color: rgba(0, 0, 0, 0.2) !important;
    border-radius: 4px !important;
  }

  ::-webkit-scrollbar-track {
    background-color: transparent !important;
  }
}

/* 平板橫向模式優化 */
@media (min-width: 768px) and (max-width: 1024px) and (orientation: landscape) {
  :deep(.fc-timegrid-slot) {
    height: 50px !important;
  }

  :deep(.available-slot) {
    min-height: 48px !important;
  }

  :deep(.event-time),
  :deep(.event-title) {
    line-height: 1.2 !important;
  }

  /* 充分利用橫向空間 */
  .min-h-screen {
    min-height: 100vh !important;
  }
}

/* 手機橫向模式優化 */
@media (max-width: 767px) and (orientation: landscape) {
  :deep(.fc-timegrid-slot) {
    height: 55px !important;
  }

  :deep(.available-slot) {
    min-height: 52px !important;
    padding: 5px 6px !important;
  }

  :deep(.event-time) {
    font-size: 10px !important;
  }

  :deep(.event-title) {
    font-size: 11px !important;
  }

  /* 減少上下邊距以充分利用空間 */
  .p-2 {
    padding: 0.25rem !important;
  }

  .mb-2 {
    margin-bottom: 0.25rem !important;
  }
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

/* 平板版優化 (768px - 1024px) */
@media (min-width: 768px) and (max-width: 1024px) {
  :deep(.fc-timegrid-slot) {
    height: 55px !important;
  }

  :deep(.available-slot) {
    min-height: 52px !important;
    padding: 7px 6px !important;
  }

  :deep(.event-time) {
    font-size: 11px !important;
  }

  :deep(.event-title) {
    font-size: 12px !important;
  }

  :deep(.event-desc) {
    font-size: 10px !important;
  }

  /* 平板版日曆頭部 */
  :deep(.fc-col-header-cell) {
    padding: 10px 6px !important;
    font-size: 12px !important;
  }

  /* 平板版時間軸 */
  :deep(.fc-timegrid-slot-label) {
    font-size: 11px;
    width: 50px !important;
  }

  :deep(.fc-timegrid-axis) {
    width: 50px !important;
  }
}

/* 響應式設計 - 手機版優化 (< 768px) */
@media (max-width: 767px) {
  /* 日曆基礎設定 */
  :deep(.fc) {
    min-height: 500px !important;
    height: auto !important;
    font-size: 14px !important;
  }

  /* 時間軸標籤 - 加大可讀性 */
  :deep(.fc-timegrid-slot-label) {
    font-size: 11px !important;
    font-weight: 600 !important;
    padding: 6px 2px !important;
    width: 50px !important;
  }

  /* 事件卡片 - 加大觸控區域 */
  :deep(.available-slot) {
    font-size: 13px !important;
    padding: 8px 6px !important;
    min-height: 70px !important;
    border-radius: 8px !important;
    line-height: 1.3 !important;
    cursor: pointer !important;
    /* 加大點擊區域 */
    touch-action: manipulation !important;
  }
  
  /* 事件內容 - 更清晰的層次 */
  :deep(.event-time) {
    font-size: 11px !important;
    font-weight: 700 !important;
    opacity: 1 !important;
    margin-bottom: 2px !important;
    white-space: normal !important;
    word-wrap: break-word !important;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1) !important;
  }
  
  :deep(.event-title) {
    font-size: 12px !important;
    font-weight: 700 !important;
    line-height: 1.2 !important;
    margin-top: 2px !important;
    margin-bottom: 2px !important;
    white-space: normal !important;
    word-wrap: break-word !important;
    overflow: visible !important;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1) !important;
  }
  
  :deep(.event-desc) {
    font-size: 10px !important;
    opacity: 0.95 !important;
    margin-top: 2px !important;
    white-space: normal !important;
    word-wrap: break-word !important;
    overflow: visible !important;
    line-height: 1.3 !important;
  }

  /* 日曆格子 - 加大高度便於操作 */
  :deep(.fc-timegrid-slot) {
    height: 70px !important;
    border-color: #e5e7eb;
  }

  /* 日曆頭部 - 更清晰 */
  :deep(.fc-col-header-cell) {
    padding: 10px 4px !important;
    font-size: 12px !important;
    font-weight: 700 !important;
  }

  :deep(.fc-scrollgrid) {
    border-radius: 12px !important;
  }

  /* 事件內容容器 - 優化排版 */
  :deep(.event-content) {
    padding: 6px 7px !important;
    gap: 2px !important;
    justify-content: flex-start !important;
    min-height: 66px !important;
    overflow: visible !important;
    white-space: normal !important;
    word-wrap: break-word !important;
  }

  /* 選擇區域 - 更明顯的視覺回饋 */
  :deep(.fc-highlight) {
    background-color: rgba(59, 130, 246, 0.2) !important;
    border-radius: 8px !important;
  }

  :deep(.fc-select-mirror) {
    background-color: rgba(59, 130, 246, 0.5) !important;
    border-radius: 8px !important;
    border: 3px solid #3b82f6 !important;
  }

  /* 時間軸 */
  :deep(.fc-timegrid-axis) {
    width: 50px !important;
  }

  /* 確保手機版滾動流暢 */
  :deep(.fc-view-harness) {
    height: auto !important;
    min-height: 500px !important;
  }

  :deep(.fc-scroller) {
    overflow-y: auto !important;
    -webkit-overflow-scrolling: touch !important;
  }

  :deep(.fc-scroller-harness) {
    overflow: visible !important;
  }

  /* 事件容器 - 防止截斷 */
  :deep(.fc-event-title) {
    white-space: normal !important;
    word-wrap: break-word !important;
    overflow: visible !important;
  }
  
  :deep(.fc-event) {
    overflow: visible !important;
    margin-bottom: 2px !important;
  }
  
  :deep(.fc-event-main) {
    overflow: visible !important;
  }

  /* 觸控優化 - 避免誤觸 */
  :deep(.fc-daygrid-event) {
    margin-top: 2px !important;
    margin-bottom: 2px !important;
  }
}

/* 極小螢幕特別優化 (< 390px) */
@media (max-width: 389px) {
  /* 時間軸 - 縮小但保持可讀 */
  :deep(.fc-timegrid-slot-label) {
    font-size: 10px !important;
    width: 45px !important;
    padding: 5px 2px !important;
    font-weight: 700 !important;
  }

  /* 事件卡片 - 保持足夠大小便於點擊 */
  :deep(.available-slot) {
    font-size: 12px !important;
    padding: 6px 5px !important;
    min-height: 65px !important;
  }
  
  :deep(.event-time) {
    font-size: 10px !important;
    font-weight: 700 !important;
  }
  
  :deep(.event-title) {
    font-size: 11px !important;
    font-weight: 700 !important;
  }

  :deep(.event-desc) {
    font-size: 9px !important;
  }

  /* 日曆格子 */
  :deep(.fc-timegrid-slot) {
    height: 65px !important;
  }

  :deep(.fc-timegrid-axis) {
    width: 45px !important;
  }

  /* 事件內容 */
  :deep(.event-content) {
    padding: 5px 5px !important;
    min-height: 61px !important;
    gap: 1px !important;
  }

  /* 日曆頭部 */
  :deep(.fc-col-header-cell) {
    padding: 8px 2px !important;
    font-size: 11px !important;
  }
}
</style>

