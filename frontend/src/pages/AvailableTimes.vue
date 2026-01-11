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
  ViewColumnsIcon,
  PencilIcon
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

// 檢視 Modal 狀態
const showViewModal = ref(false)
const viewData = ref({
  id: null,
  title: '',
  description: '',
  start: null,
  end: null,
  current_bookings: 0,
  max_capacity: 10,
  is_active: true,
  reservations: []
})

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
  reservations: [],
  // 重複選項
  enableRepeat: false,
  repeatType: 'daily', // daily, weekly, weekdays, never
  repeatEndDate: null,
  repeatDays: [] // 用於每週重複選擇星期幾
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

// 當前檢視模式（手機版預設使用日檢視）
const currentView = ref(window.innerWidth <= 767 ? 'timeGridDay' : 'timeGridWeek')
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
          is_active: item.is_active !== undefined ? item.is_active : true,
          reservations: reservations,
          isFullyBooked: isFullyBooked,
          hasReservations: hasReservations
        }
      }
      
      // 根據預約狀態設定事件類別
      if (!item.is_active) {
        // 已暫停的時段
        event.classNames = ['available-slot', 'suspended']
      } else if (isFullyBooked) {
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
    // 檢查是否啟用重複
    if (slotData.enableRepeat) {
      await createRepeatingSlots(slotData)
    } else {
      // 單次創建
      const startTime = new Date(slotData.start)
      const endTime = new Date(slotData.end)
      
      await apiPost('/available-times', {
        title: slotData.title,
        description: slotData.description || '',
        start_time: formatBackendDateTime(startTime),
        end_time: formatBackendDateTime(endTime),
        max_capacity: 10
      })
      
      showToast('success', '時段已建立')
    }
    
    // 重新獲取最新數據以確保同步
    await fetchAvailableTimes()
    nextTick(refreshCalendarSource)
  } catch (err) {
    const errorMessage = err.message || '建立失敗'
    showToast('error', errorMessage)
  }
}

// 創建重複時段（優化版：併發處理）
async function createRepeatingSlots(slotData) {
  const slots = []
  const startTime = new Date(slotData.start)
  const endTime = new Date(slotData.end)
  const duration = endTime.getTime() - startTime.getTime() // 計算單個時段的長度
  
  // ==========================================
  // 1. 邏輯修復：正確計算終止條件
  // ==========================================
  
  // 判斷是否有設定結束日期
  let maxDate = null
  if (slotData.repeatEndDate) {
    maxDate = new Date(slotData.repeatEndDate)
    maxDate.setHours(23, 59, 59, 999) // 包含結束當天的全天
  }
  
  // 安全限制：防止邏輯錯誤導致的當機 (強制上限)
  // 如果沒有結束日期，預設建立 90 天；如果有，上限 365 天
  const SAFE_LIMIT_DAYS = maxDate ? 365 : 90
  const limitDate = new Date(startTime)
  limitDate.setDate(limitDate.getDate() + SAFE_LIMIT_DAYS)
  
  // 決定最終的檢查日期 (取 maxDate 與 limitDate 較早者)
  const cutoffDate = maxDate && maxDate < limitDate ? maxDate : limitDate

  // 根據不同類型生成時段數據
  let currentDate = new Date(startTime)
  
  // 統一的迴圈生成邏輯
  while (currentDate <= cutoffDate) {
    let shouldCreate = false

    if (slotData.repeatType === 'daily') {
      shouldCreate = true
    } else if (slotData.repeatType === 'weekly') {
      // 檢查是否是同一週的同一天（雖然每次加7天必然是，但保持邏輯嚴謹）
      shouldCreate = true 
    } else if (slotData.repeatType === 'weekdays') {
      // 檢查當前日期是否在選中的星期幾之中
      const dayOfWeek = currentDate.getDay() // 0 (週日) - 6 (週六)
      if (slotData.repeatDays && slotData.repeatDays.includes(dayOfWeek)) {
        shouldCreate = true
      }
    }

    if (shouldCreate) {
      const newStart = new Date(currentDate)
      // 確保時間部分準確繼承原始設定 (避免日光節約時間等偏移)
      newStart.setHours(startTime.getHours(), startTime.getMinutes(), startTime.getSeconds())
      
      const newEnd = new Date(newStart.getTime() + duration)

      slots.push({
        title: slotData.title,
        description: slotData.description || '',
        start_time: formatBackendDateTime(newStart),
        end_time: formatBackendDateTime(newEnd),
        max_capacity: 10
      })
    }

    // 日期推進邏輯
    if (slotData.repeatType === 'weekly') {
      currentDate.setDate(currentDate.getDate() + 7)
    } else {
      currentDate.setDate(currentDate.getDate() + 1) // daily 和 weekdays 都是逐日檢查
    }
    
    // 安全中斷：防止意外死循環
    if (slots.length > 500) break 
  }

  if (slots.length === 0) {
    showToast('error', '沒有產生任何時段，請檢查日期設定')
    return
  }

  // ==========================================
  // 2. 傳輸修復：併發上傳 (Concurrent Upload)
  // ==========================================
  
  showToast('success', `準備建立 ${slots.length} 個時段，請勿關閉頁面...`)
  
  let successCount = 0
  let failCount = 0
  let conflictCount = 0

  // 設定併發數量 (一次同時發送 5 個請求，加快速度但不過載伺服器)
  const CONCURRENT_LIMIT = 5 
  
  // 將陣列切成小塊 (Chunking)
  for (let i = 0; i < slots.length; i += CONCURRENT_LIMIT) {
    const chunk = slots.slice(i, i + CONCURRENT_LIMIT)
    
    // 使用 Promise.all 同時處理這一批次的請求
    await Promise.all(chunk.map(async (slot) => {
      try {
        await apiPost('/available-times', slot)
        successCount++
      } catch (err) {
        // 簡單判斷是否為衝突 (根據你的後端回傳訊息調整)
        if (err.message && (err.message.includes('衝突') || err.message.includes('存在'))) {
          conflictCount++
        } else {
          failCount++
          console.error('建立失敗:', err)
        }
      }
    }))

    // 更新進度顯示 (每處理完一批更新一次)
    if (slots.length > 20) {
      showToast('success', `建立進度: ${Math.min(i + CONCURRENT_LIMIT, slots.length)} / ${slots.length}，請勿關閉頁面`)
    }
  }

  // ==========================================
  // 3. 結果總結
  // ==========================================
  
  // 重新獲取資料以刷新日曆
  await fetchAvailableTimes()
  
  if (successCount > 0) {
    let message = `完成！成功建立 ${successCount} 個時段`
    if (conflictCount > 0) message += ` (${conflictCount} 個重複跳過)`
    if (failCount > 0) message += `，${failCount} 個失敗`
    showToast('success', message)
  } else if (conflictCount > 0) {
    showToast('warning', '所有選擇的時段都已存在')
  } else {
    showToast('error', '建立失敗，請稍後再試')
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

// 切換時段狀態（暫停/啟用）
async function toggleSlotStatus(slotId, currentStatus) {
  try {
    const response = await apiPost(`/available-times/${slotId}/toggle-status`)
    
    if (response.success) {
      await fetchAvailableTimes()
      nextTick(refreshCalendarSource)
      showToast('success', response.message)
    }
  } catch (err) {
    if (err.redirect_to_reservations) {
      // 有未完成的預約，提示用戶先改期
      if (confirm(`${err.message}\n\n點擊確定前往預約管理頁面`)) {
        // 跳轉到預約管理頁面
        window.location.href = '/reservations'
      }
    } else {
      showToast('error', err.message || '操作失敗')
    }
  }
}

// Google Calendar 風格的 FullCalendar 配置
const calendarOptions = computed(() => ({
  plugins: [dayGridPlugin, timeGridPlugin, interactionPlugin],
  locale: zhTwLocale,
  
  // 移除預設工具列，使用自定義
  headerToolbar: false,
  
  // Google 風格檢視設定（手機版預設使用日檢視）
  initialView: window.innerWidth <= 767 ? 'timeGridDay' : 'timeGridWeek',
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
  
  // 自定義事件內容 - 母子容器設計模式
  eventContent: function(arg) {
    const props = arg.event.extendedProps
    const reservations = props.reservations || []
    
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
    
    // 母容器
    const container = document.createElement('div')
    container.className = 'slot-container'

    // 頂部：時間與標題
    const headerEl = document.createElement('div')
    headerEl.className = 'slot-header'
    
    const timeEl = document.createElement('div')
    timeEl.className = 'slot-time'
    timeEl.textContent = formatEventTime(arg.event.start, arg.event.end)
    headerEl.appendChild(timeEl)
    
    const titleEl = document.createElement('div')
    titleEl.className = 'slot-title'
    titleEl.textContent = arg.event.title || '可預約時段'
    headerEl.appendChild(titleEl)
    
    container.appendChild(headerEl)

    // 子元素：預約清單
    const reservationsEl = document.createElement('div')
    reservationsEl.className = 'reservations-list'
    
    if (reservations.length > 0) {
      // 智能顯示限制：根據裝置和時段高度調整
      const isMobileDevice = window.innerWidth <= 767
      const maxVisible = isMobileDevice ? 2 : 3 // 手機顯示2個，桌面顯示3個
      const visibleReservations = reservations.slice(0, maxVisible)
      const remainingCount = reservations.length - maxVisible
      
      // 遍歷顯示的預約
      visibleReservations.forEach(reservation => {
        const itemEl = document.createElement('div')
        itemEl.className = 'reservation-item'
        
        // 根據狀態設定顏色
        if (reservation.status === 'confirmed') {
          itemEl.classList.add('status-confirmed')
        } else if (reservation.status === 'pending') {
          itemEl.classList.add('status-pending')
        } else if (reservation.status === 'cancelled') {
          itemEl.classList.add('status-cancelled')
        }
        
        // 手機版簡化顯示：只顯示時間和名稱第一個字
        if (isMobileDevice) {
          const timeSpan = document.createElement('span')
          timeSpan.className = 'reservation-time'
          timeSpan.textContent = reservation.reservation_time ? reservation.reservation_time.substring(0, 5) : ''
          
          const nameSpan = document.createElement('span')
          nameSpan.className = 'reservation-name'
          const customerName = reservation.customer_name || '未命名'
          // 手機版縮短名稱（只保疙2-3個字）
          nameSpan.textContent = customerName.length > 3 ? customerName.substring(0, 3) : customerName
          
          itemEl.appendChild(timeSpan)
          itemEl.appendChild(nameSpan)
        } else {
          // 桌面版完整顯示
          const timeSpan = document.createElement('span')
          timeSpan.className = 'reservation-time'
          timeSpan.textContent = reservation.reservation_time ? reservation.reservation_time.substring(0, 5) : ''
          
          const nameSpan = document.createElement('span')
          nameSpan.className = 'reservation-name'
          nameSpan.textContent = reservation.customer_name || '未命名客戶'
          
          itemEl.appendChild(timeSpan)
          itemEl.appendChild(nameSpan)
        }
        
        reservationsEl.appendChild(itemEl)
      })
      
      // 如果有更多預約，顯示「+N更多」提示
      if (remainingCount > 0) {
        const moreEl = document.createElement('div')
        moreEl.className = 'reservation-more'
        moreEl.textContent = `+${remainingCount} 更多...`
        reservationsEl.appendChild(moreEl)
      }
    } else {
      // 無預約時顯示提示
      const emptyEl = document.createElement('div')
      emptyEl.className = 'empty-hint'
      emptyEl.textContent = '開放預約'
      reservationsEl.appendChild(emptyEl)
    }
    
    container.appendChild(reservationsEl)

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
    reservations: [],
    enableRepeat: false,
    repeatType: 'daily',
    repeatEndDate: null,
    repeatDays: []
  }
  showEditModal.value = true
}

// 檢視時段詳情
function handleSlotEdit(info) {
  const event = info.event
  const props = event.extendedProps
  
  viewData.value = {
    id: event.id,
    title: event.title,
    description: props.description || '',
    start: event.start,
    end: event.end,
    current_bookings: props.current_bookings || 0,
    max_capacity: props.max_capacity || 10,
    is_active: props.is_active !== undefined ? props.is_active : true,
    reservations: props.reservations || []
  }
  showViewModal.value = true
}

// 從檢視模式切換到編輯模式
function switchToEditMode() {
  editForm.value = { ...viewData.value }
  showViewModal.value = false
  showEditModal.value = true
}

// 關閉檢視 Modal
function closeViewModal() {
  showViewModal.value = false
  viewData.value = {
    id: null,
    title: '',
    description: '',
    start: null,
    end: null,
    current_bookings: 0,
    max_capacity: 10,
    is_active: true,
    reservations: []
  }
}

// 時段更新處理
async function handleSlotUpdate(info) {
  const event = info.event
  const now = new Date()
  const endTime = new Date(event.end)
  
  // 檢查時間是否在未來
  if (endTime <= now) {
    info.revert()
    showToast('error', '無法移動到過去的時間')
    return
  }
  
  const hasReservations = event.extendedProps.current_bookings > 0 || 
                          (event.extendedProps.reservations && event.extendedProps.reservations.length > 0)
  
  // 如果已有預約，禁止更新時間
  if (hasReservations) {
    info.revert()
    showToast('error', '此時段已有預約，無法修改時間')
    return
  }
  
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
  const endTime = new Date(info.event.end)
  
  // 優先檢查時間是否在未來（檢查結束時間，確保整個時段都在未來）
  if (endTime <= now) {
    info.revert()
    showToast('error', '無法移動到過去的時間')
    return
  }
  
  // 檢查是否有預約
  const hasReservations = info.event.extendedProps.current_bookings > 0 || 
                          (info.event.extendedProps.reservations && info.event.extendedProps.reservations.length > 0)
  
  // 如果已有預約，禁止移動
  if (hasReservations) {
    info.revert()
    showToast('error', '此時段已有預約，無法移動')
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
    end: selectedTime.value.end,
    enableRepeat: false,
    repeatType: 'daily',
    repeatEndDate: null,
    repeatDays: []
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
  
  // 檢查時間是否為未來時間
  const now = new Date()
  const startTime = new Date(editForm.value.start)
  
  if (startTime <= now) {
    showToast('error', '開始時間必須在未來')
    return
  }
  
  if (editForm.value.id) {
    // 更新現有時段 - 如果有預約則不允許修改時間
    const hasReservations = editForm.value.current_bookings > 0 || 
                            (editForm.value.reservations && editForm.value.reservations.length > 0)
    
    if (hasReservations) {
      // 只允許修改標題和描述，不允許修改時間
      const originalEvent = calendarEvents.value.find(e => String(e.id) === String(editForm.value.id))
      if (originalEvent) {
        const originalStart = new Date(originalEvent.start).getTime()
        const originalEnd = new Date(originalEvent.end).getTime()
        const newStart = new Date(editForm.value.start).getTime()
        const newEnd = new Date(editForm.value.end).getTime()
        
        if (originalStart !== newStart || originalEnd !== newEnd) {
          showToast('error', '此時段已有預約，無法修改時間')
          return
        }
      }
    }
    
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
    reservations: [],
    enableRepeat: false,
    repeatType: 'daily',
    repeatEndDate: null,
    repeatDays: []
  }
}

// 切換重複日期選擇
function toggleRepeatDay(dayIndex) {
  const index = editForm.value.repeatDays.indexOf(dayIndex)
  if (index > -1) {
    editForm.value.repeatDays.splice(index, 1)
  } else {
    editForm.value.repeatDays.push(dayIndex)
  }
}

// 啟用結束日期選項
function enableEndDate() {
  if (!editForm.value.repeatEndDate) {
    const defaultDate = new Date(Date.now() + 30 * 24 * 60 * 60 * 1000)
    editForm.value.repeatEndDate = formatDateTimeLocal(defaultDate).split('T')[0]
  }
}

// 設定預設結束日期
function setDefaultEndDate() {
  if (!editForm.value.repeatEndDate) {
    const defaultDate = new Date(Date.now() + 30 * 24 * 60 * 60 * 1000)
    editForm.value.repeatEndDate = formatDateTimeLocal(defaultDate).split('T')[0]
  }
}

// 獲取重複摘要
function getRepeatSummary() {
  const dayNames = ['星期日', '星期一', '星期二', '星期三', '星期四', '星期五', '星期六']
  
  if (editForm.value.repeatType === 'never') {
    return '不重複'
  }
  
  let summary = ''
  
  if (editForm.value.repeatType === 'daily') {
    summary = '每天重複'
  } else if (editForm.value.repeatType === 'weekly') {
    summary = '每週重複'
  } else if (editForm.value.repeatType === 'weekdays') {
    if (editForm.value.repeatDays.length === 0) {
      return '請選擇至少一個星期'
    }
    const selectedDays = editForm.value.repeatDays
      .sort((a, b) => a - b)
      .map(i => dayNames[i])
      .join('、')
    summary = `每週 ${selectedDays} 重複`
  }
  
  if (editForm.value.repeatEndDate) {
    const endDate = new Date(editForm.value.repeatEndDate)
    summary += `，直到 ${endDate.toLocaleDateString('zh-TW', { year: 'numeric', month: 'long', day: 'numeric' })}`
  } else {
    summary += '（預設建立 90 天內的時段）'
  }
  
  return summary
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
  if (!date) return ''
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
    if (showViewModal.value) closeViewModal()
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

    <!-- 檢視 Modal - 詳細資訊展示 -->
    <TransitionRoot appear :show="showViewModal" as="template">
      <Dialog as="div" @close="closeViewModal" class="relative z-40">
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
                    時段詳情
                    <span 
                      v-if="!viewData.is_active"
                      class="ml-3 px-3 py-1 text-xs font-bold bg-gray-200 text-gray-600 rounded-full"
                    >
                      已暫停
                    </span>
                  </DialogTitle>
                  <div class="flex items-center gap-2">
                    <!-- 暫停/啟用按鈕 -->
                    <button 
                      @click="toggleSlotStatus(viewData.id, viewData.is_active)" 
                      :class="[
                        'px-4 py-2 rounded-lg text-sm font-semibold transition-all duration-200',
                        viewData.is_active 
                          ? 'text-gray-700 bg-gray-100 hover:bg-gray-200' 
                          : 'text-green-700 bg-green-100 hover:bg-green-200'
                      ]"
                      :title="viewData.is_active ? '暫停此時段' : '啟用此時段'"
                    >
                      {{ viewData.is_active ? '暫停' : '啟用' }}
                    </button>
                    <!-- 編輯按鈕 -->
                    <button 
                      @click="switchToEditMode" 
                      class="p-2 rounded-lg text-indigo-600 hover:bg-indigo-50 transition-all duration-200"
                      title="編輯時段"
                    >
                      <PencilIcon class="w-5 h-5" />
                    </button>
                    <!-- 關閉按鈕 -->
                    <button 
                      @click="closeViewModal" 
                      class="p-2 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-all duration-200"
                    >
                      <XMarkIcon class="w-6 h-6" />
                    </button>
                  </div>
                </div>
                
                <!-- 內容區域 -->
                <div class="p-6 max-h-[70vh] md:max-h-[600px] overflow-y-auto">
                  <div class="space-y-6">
                    <!-- 時段資訊 -->
                    <div class="bg-gradient-to-br from-indigo-50 to-blue-50 rounded-xl p-5 border border-indigo-100">
                      <h3 class="text-lg font-bold text-gray-900 mb-3">{{ viewData.title }}</h3>
                      
                      <div class="space-y-2">
                        <!-- 時間 -->
                        <div class="flex items-center gap-3 text-gray-700">
                          <ClockIcon class="w-5 h-5 text-indigo-600" />
                          <span class="font-medium">{{ formatDateTime(viewData.start) }} - {{ formatTime(viewData.end) }}</span>
                        </div>
                        
                        <!-- 備註 -->
                        <div v-if="viewData.description" class="mt-3 pt-3 border-t border-indigo-200">
                          <p class="text-sm text-gray-600 leading-relaxed">{{ viewData.description }}</p>
                        </div>
                      </div>
                    </div>
                    
                    <!-- 預約列表 -->
                    <div>
                      <h4 class="text-base font-bold text-gray-900 mb-3 flex items-center gap-2">
                        <UserGroupIcon class="w-5 h-5 text-gray-600" />
                        預約清單 ({{ viewData.reservations.length }})
                      </h4>
                      
                      <div v-if="viewData.reservations.length > 0" class="space-y-3 max-h-[400px] overflow-y-auto scrollbar-hidden pr-1">
                        <div 
                          v-for="(reservation, index) in viewData.reservations" 
                          :key="index"
                          class="bg-white border-2 rounded-xl p-4 hover:shadow-md transition-all duration-200"
                          :class="{
                            'border-blue-200 bg-blue-50': reservation.status === 'confirmed',
                            'border-amber-200 bg-amber-50': reservation.status === 'pending',
                            'border-gray-200 bg-gray-50': reservation.status === 'cancelled'
                          }"
                        >
                          <div class="flex items-start justify-between">
                            <div class="flex-1">
                              <div class="flex items-center gap-2 mb-2">
                                <span class="font-bold text-gray-900">{{ reservation.customer_name || '未命名客戶' }}</span>
                                <span 
                                  class="px-2 py-1 rounded-full text-xs font-bold"
                                  :class="{
                                    'bg-blue-100 text-blue-700': reservation.status === 'confirmed',
                                    'bg-amber-100 text-amber-700': reservation.status === 'pending',
                                    'bg-gray-100 text-gray-700': reservation.status === 'cancelled'
                                  }"
                                >
                                  {{ reservation.status === 'confirmed' ? '已確認' : reservation.status === 'pending' ? '待確認' : '已取消' }}
                                </span>
                              </div>
                              
                              <div class="space-y-1 text-sm text-gray-600">
                                <div v-if="reservation.customer_phone">
                                  <span class="font-medium">電話:</span> {{ reservation.customer_phone }}
                                </div>
                                <div v-if="reservation.reservation_time">
                                  <span class="font-medium">預約時間:</span> {{ reservation.reservation_time }}
                                </div>
                                <div v-if="reservation.service_name">
                                  <span class="font-medium">服務項目:</span> {{ reservation.service_name }}
                                </div>
                                <div v-if="reservation.notes" class="mt-2 p-2 bg-white rounded border border-gray-200">
                                  <span class="font-medium">備註:</span> {{ reservation.notes }}
                                </div>
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>
                      
                      <div v-else class="text-center py-12 bg-gray-50 rounded-xl border-2 border-dashed border-gray-200">
                        <UserGroupIcon class="w-12 h-12 text-gray-400 mx-auto mb-3" />
                        <p class="text-gray-500 font-medium">目前沒有預約</p>
                        <p class="text-sm text-gray-400 mt-1">此時段開放預約中</p>
                      </div>
                    </div>
                  </div>
                </div>
              </DialogPanel>
            </TransitionChild>
          </div>
        </div>
      </Dialog>
    </TransitionRoot>

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
                <div class="p-6 max-h-[70vh] md:max-h-[600px] overflow-y-auto scrollbar-hidden">
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
                    
                    <!-- 重複設定（僅新建時顯示） -->
                    <div v-if="!editForm.id" class="border-t-2 border-gray-200 pt-6">
                      <!-- 重複按鈕 -->
                      <button
                        v-if="!editForm.enableRepeat"
                        type="button"
                        @click="editForm.enableRepeat = true; editForm.repeatType = 'daily'"
                        class="w-full px-4 py-3 text-left text-sm font-medium text-gray-700 bg-white border-2 border-gray-200 rounded-xl hover:bg-gray-50 hover:border-indigo-300 transition-all duration-200 flex items-center gap-2"
                      >
                        <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        <span>新增重複</span>
                      </button>
                      
                      <!-- 重複設定區域 -->
                      <div v-if="editForm.enableRepeat" class="space-y-4">
                        <!-- 重複類型選擇 -->
                        <div class="flex items-center gap-3">
                          <label class="text-sm font-bold text-gray-900 flex-shrink-0">
                            重複
                          </label>
                          <select 
                            v-model="editForm.repeatType"
                            class="flex-1 px-4 py-2 text-sm font-medium text-gray-900 bg-gray-50 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-indigo-600 focus:bg-white focus:ring-2 focus:ring-indigo-100 transition-all duration-200"
                          >
                            <option value="daily">每天</option>
                            <option value="weekly">每週</option>
                            <option value="weekdays">自訂（選擇星期）</option>
                          </select>
                          <button
                            type="button"
                            @click="editForm.enableRepeat = false; editForm.repeatType = 'never'; editForm.repeatEndDate = null; editForm.repeatDays = []"
                            class="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-all"
                            title="移除重複"
                          >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                          </button>
                        </div>
                      
                      <!-- 重複詳細設定 -->
                      <div v-if="editForm.repeatType !== 'never'" class="space-y-4">
                        <!-- 每週指定日期選擇 -->
                        <div v-if="editForm.repeatType === 'weekdays'" class="bg-gray-50 rounded-xl p-4 border-2 border-gray-200">
                          <label class="block text-sm font-medium text-gray-700 mb-3">重複於</label>
                          <div class="grid grid-cols-7 gap-2">
                            <button
                              v-for="(day, index) in ['日', '一', '二', '三', '四', '五', '六']"
                              :key="index"
                              type="button"
                              @click="toggleRepeatDay(index)"
                              :class="[
                                'aspect-square flex items-center justify-center text-sm font-bold rounded-lg transition-all',
                                editForm.repeatDays.includes(index)
                                  ? 'bg-indigo-600 text-white shadow-md'
                                  : 'bg-white text-gray-700 border-2 border-gray-300 hover:border-indigo-400 hover:bg-indigo-50'
                              ]"
                            >
                              {{ day }}
                            </button>
                          </div>
                        </div>
                        
                        <!-- 結束日期 -->
                        <div class="bg-gray-50 rounded-xl p-4 border-2 border-gray-200">
                          <label class="block text-sm font-medium text-gray-700 mb-3">結束</label>
                          <div class="space-y-3">
                            <!-- 永不結束選項 -->
                            <div class="flex items-center">
                              <input
                                type="radio"
                                id="never-end"
                                :checked="!editForm.repeatEndDate"
                                @change="editForm.repeatEndDate = null"
                                class="w-4 h-4 text-indigo-600 border-gray-300 focus:ring-indigo-500"
                              />
                              <label for="never-end" class="ml-3 text-sm font-medium text-gray-700">
                                永不結束
                              </label>
                            </div>
                            
                            <!-- 選擇結束日期 -->
                            <div class="flex items-start">
                              <input
                                type="radio"
                                id="end-date"
                                :checked="!!editForm.repeatEndDate"
                                @change="enableEndDate"
                                class="w-4 h-4 text-indigo-600 border-gray-300 focus:ring-indigo-500 mt-2"
                              />
                              <label for="end-date" class="ml-3 flex-1">
                                <span class="block text-sm font-medium text-gray-700 mb-2">結束於</span>
                                <input
                                  type="date"
                                  v-model="editForm.repeatEndDate"
                                  @focus="setDefaultEndDate"
                                  :min="editForm.start ? formatDateTimeLocal(editForm.start).split('T')[0] : ''"
                                  class="w-full px-3 py-2 text-sm border-2 border-gray-300 rounded-lg focus:outline-none focus:border-indigo-600 focus:ring-2 focus:ring-indigo-100 disabled:bg-gray-100"
                                  :disabled="!editForm.repeatEndDate"
                                />
                              </label>
                            </div>
                          </div>
                        </div>
                        
                        <!-- 預覽提示 -->
                        <div class="bg-blue-50 border-2 border-blue-200 rounded-xl p-4">
                          <div class="flex items-start gap-3">
                            <svg class="w-5 h-5 text-blue-600 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                              <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                            </svg>
                            <div class="flex-1">
                              <p class="text-sm font-medium text-blue-900">
                                {{ getRepeatSummary() }}
                              </p>
                            </div>
                          </div>
                        </div>
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
          'fixed top-4 right-4 md:top-6 md:right-6 px-5 py-4 rounded-xl text-sm font-semibold shadow-2xl z-[9999] max-w-sm flex items-center gap-3',
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

/* 可預約時段樣式 - 母子容器設計模式 */
:deep(.available-slot) {
  background-color: transparent !important;
  border-radius: 6px !important;
  color: #374151 !important;
  font-size: 12px !important;
  font-weight: 500 !important;
  padding: 8px !important;
  cursor: pointer !important;
  transition: all 0.2s ease !important;
  min-height: 60px !important;
  display: flex !important;
  align-items: flex-start !important;
  overflow: visible !important;
  word-wrap: break-word !important;
}

/* 母子容器樣式 */
:deep(.slot-container) {
  display: flex !important;
  flex-direction: column !important;
  gap: 6px !important;
  width: 100% !important;
  height: 100% !important;
  padding: 0 !important;
}

:deep(.slot-header) {
  display: flex !important;
  flex-direction: column !important;
  gap: 2px !important;
  padding-bottom: 6px !important;
  border-bottom: 1px solid rgba(107, 114, 128, 0.2) !important;
}

:deep(.slot-time) {
  font-size: 10px !important;
  font-weight: 600 !important;
  color: #6b7280 !important;
  line-height: 1.2 !important;
}

:deep(.slot-title) {
  font-size: 12px !important;
  font-weight: 700 !important;
  color: #111827 !important;
  line-height: 1.3 !important;
}

/* 預約清單容器 */
:deep(.reservations-list) {
  display: flex !important;
  flex-direction: column !important;
  gap: 4px !important;
  flex: 1 !important;
  overflow: visible !important;
}

/* 預約項目小方塊 */
:deep(.reservation-item) {
  display: flex !important;
  align-items: center !important;
  gap: 6px !important;
  padding: 4px 8px !important;
  border-radius: 4px !important;
  font-size: 11px !important;
  font-weight: 600 !important;
  color: #ffffff !important;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1) !important;
}

:deep(.reservation-time) {
  font-size: 10px !important;
  font-weight: 700 !important;
  opacity: 0.9 !important;
  white-space: nowrap !important;
  flex-shrink: 0 !important;
}

:deep(.reservation-name) {
  font-size: 11px !important;
  font-weight: 600 !important;
  white-space: nowrap !important;
  overflow: hidden !important;
  text-overflow: ellipsis !important;
  flex: 1 !important;
}

/* 預約狀態顏色 */
:deep(.reservation-item.status-confirmed) {
  background-color: #3b82f6 !important;
}

:deep(.reservation-item.status-pending) {
  background-color: #f59e0b !important;
}

:deep(.reservation-item.status-cancelled) {
  background-color: #9ca3af !important;
  text-decoration: line-through !important;
}

/* 空狀態提示 */
:deep(.empty-hint) {
  text-align: center !important;
  color: #9ca3af !important;
  font-size: 11px !important;
  font-style: italic !important;
  padding: 8px 0 !important;
}

/* 「+N更多」提示 */
:deep(.reservation-more) {
  text-align: center !important;
  color: #6b7280 !important;
  font-size: 10px !important;
  font-weight: 600 !important;
  font-style: italic !important;
  padding: 4px 8px !important;
  background-color: rgba(107, 114, 128, 0.1) !important;
  border-radius: 4px !important;
  cursor: pointer !important;
  transition: all 0.2s ease !important;
}

:deep(.reservation-more:hover) {
  background-color: rgba(107, 114, 128, 0.2) !important;
  color: #374151 !important;
}

/* 完全可預約（無預約）- 虛線灰色邊框 */
:deep(.available-slot.available) {
  border: 2px dashed #d1d5db !important;
  background-color: #f9fafb !important;
}

:deep(.available-slot.available:hover) {
  border-color: #9ca3af !important;
  background-color: #f3f4f6 !important;
  box-shadow: 0 2px 8px rgba(156, 163, 175, 0.2) !important;
}

/* 部分已預約 - 實線藍色/橙色邊框 */
:deep(.available-slot.partially-booked) {
  border: 2px solid #3b82f6 !important;
  background-color: #eff6ff !important;
}

:deep(.available-slot.partially-booked:hover) {
  border-color: #2563eb !important;
  background-color: #dbeafe !important;
  box-shadow: 0 2px 8px rgba(59, 130, 246, 0.3) !important;
}

/* 完全預約滿 - 實線紅色邊框 */
:deep(.available-slot.fully-booked) {
  border: 2px solid #ef4444 !important;
  background-color: #fef2f2 !important;
  cursor: not-allowed !important;
}

:deep(.available-slot.fully-booked:hover) {
  border-color: #dc2626 !important;
  background-color: #fee2e2 !important;
  box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3) !important;
}

/* 已暫停時段 - 灰色虛線邊框 */
:deep(.available-slot.suspended) {
  border: 2px dashed #9ca3af !important;
  background-color: #f3f4f6 !important;
  opacity: 0.6 !important;
}

:deep(.available-slot.suspended:hover) {
  border-color: #6b7280 !important;
  background-color: #e5e7eb !important;
  box-shadow: 0 2px 8px rgba(156, 163, 175, 0.2) !important;
  opacity: 0.8 !important;
}

:deep(.available-slot.suspended .slot-title) {
  text-decoration: line-through !important;
  color: #6b7280 !important;
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
    height: 70px !important;
  }

  :deep(.available-slot) {
    min-height: 65px !important;
    padding: 8px !important;
  }

  :deep(.slot-time) {
    font-size: 10px !important;
  }

  :deep(.slot-title) {
    font-size: 12px !important;
  }

  :deep(.reservation-item) {
    font-size: 10px !important;
    padding: 3px 6px !important;
    gap: 4px !important;
  }
  
  :deep(.reservation-time) {
    font-size: 9px !important;
  }
  
  :deep(.reservation-name) {
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
    padding: 10px !important;
    min-height: 90px !important;
    border-radius: 8px !important;
    line-height: 1.3 !important;
    cursor: pointer !important;
    /* 加大點擊區域 */
    touch-action: manipulation !important;
  }
  
  /* 母子容器 - 更清晰的層次 */
  :deep(.slot-container) {
    gap: 8px !important;
  }
  
  :deep(.slot-header) {
    padding-bottom: 8px !important;
  }
  
  :deep(.slot-time) {
    font-size: 12px !important;  /* 從 11px 提升至 12px */
    font-weight: 700 !important;
  }
  
  :deep(.slot-title) {
    font-size: 14px !important;  /* 從 13px 提升至 14px */
    font-weight: 700 !important;
  }
  
  :deep(.reservation-item) {
    font-size: 12px !important;  /* 從 11px 提升至 12px */
    padding: 6px 8px !important; /* 增加內邊距 */
    gap: 5px !important;
  }
  
  :deep(.reservation-time) {
    font-size: 11px !important;
  }
  
  :deep(.reservation-name) {
    font-size: 12px !important;  /* 從 11px 提升至 12px */
  }
  
  :deep(.reservation-more) {
    font-size: 11px !important;
    padding: 6px 8px !important;
  }
  
  :deep(.empty-hint) {
    font-size: 12px !important;  /* 從 11px 提升至 12px */
    padding: 10px 0 !important;
  }

  /* 日曆格子 - 加大高度便於操作 */
  :deep(.fc-timegrid-slot) {
    height: 90px !important;
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
  :deep(.slot-container) {
    min-height: 85px !important;
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
    padding: 8px !important;
    min-height: 80px !important;
  }
  
  :deep(.slot-time) {
    font-size: 10px !important;
    font-weight: 700 !important;
  }
  
  :deep(.slot-title) {
    font-size: 11px !important;
    font-weight: 700 !important;
  }

  :deep(.reservation-item) {
    font-size: 10px !important;
    padding: 4px 6px !important;
    gap: 4px !important;
  }
  
  :deep(.reservation-time) {
    font-size: 9px !important;
  }
  
  :deep(.reservation-name) {
    font-size: 10px !important;
  }

  /* 日曆格子 */
  :deep(.fc-timegrid-slot) {
    height: 80px !important;
  }

  :deep(.fc-timegrid-axis) {
    width: 45px !important;
  }

  /* 事件內容 */
  :deep(.slot-container) {
    min-height: 75px !important;
    gap: 6px !important;
  }

  /* 日曆頭部 */
  :deep(.fc-col-header-cell) {
    padding: 8px 2px !important;
    font-size: 11px !important;
  }
}

/* 隱藏滾動條但保持滾動功能 */
.scrollbar-hidden {
  scrollbar-width: none; /* Firefox */
  -ms-overflow-style: none; /* IE and Edge */
}

.scrollbar-hidden::-webkit-scrollbar {
  display: none; /* Chrome, Safari, Opera */
}
</style>

