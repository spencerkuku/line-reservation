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
    
    // 如果有事件且不在當前檢視範圍內，自動導航到第一個事件的日期
    if (events.length > 0 && calendarRef.value) {
      const firstEventDate = new Date(events[0].start)
      const calendar = calendarRef.value.getApi()
      const currentCalendarDate = calendar.getDate()
      
      // 檢查第一個事件是否在當前週/月的檢視範圍內
      const timeDiff = Math.abs(firstEventDate.getTime() - currentCalendarDate.getTime())
      const daysDiff = Math.ceil(timeDiff / (1000 * 3600 * 24))
      
      console.log('First event date:', firstEventDate)
      console.log('Current calendar date:', currentCalendarDate)
      console.log('Days difference:', daysDiff)
      
      // 如果相差超過3天，自動導航到事件日期
      if (daysDiff > 3) {
        console.log('Navigating to first event date')
        calendar.gotoDate(firstEventDate)
        currentDate.value = firstEventDate
      }
    }
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
  slotMinTime: '00:00:00',
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

// 組件載入時獲取數據
onMounted(() => {
  fetchAvailableTimes()
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
    <div class="calendar-header">
      <!-- 日曆導航 -->
      <div class="calendar-nav">
        <button @click="goToToday" class="nav-btn today-btn">今天</button>
        <button @click="navigatePrev" class="nav-btn prev-btn">‹</button>
        <button @click="navigateNext" class="nav-btn next-btn">›</button>
        <h2 class="current-period">{{ currentPeriod }}</h2>
      </div>
      
      <!-- 檢視切換 -->
      <div class="view-switcher">
        <button 
          @click="changeView('timeGridWeek')"
          :class="['view-btn', { active: currentView === 'timeGridWeek' }]"
        >
          週
        </button>
        <button 
          @click="changeView('timeGridDay')"
          :class="['view-btn', { active: currentView === 'timeGridDay' }]"
        >
          日
        </button>
        <button 
          @click="changeView('dayGridMonth')"
          :class="['view-btn', { active: currentView === 'dayGridMonth' }]"
        >
          月
        </button>
      </div>
    </div>

    <!-- 主要日曆區域 -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
      <div v-if="isLoading" class="loading-overlay">
        <div class="loading-spinner"></div>
        <p class="loading-text">載入中...</p>
      </div>
      
      <FullCalendar 
        ref="calendarRef"
        :options="calendarOptions" 
        class="google-calendar"
      />
    </div>

    <!-- 快速建立氣泡 -->
    <div v-if="showQuickCreate" class="quick-create-bubble">
      <input 
        ref="quickInput"
        v-model="quickSlotTitle"
        placeholder="可預約時段"
        @keyup.enter="createQuickSlot"
        @keyup.escape="cancelQuickCreate"
        class="quick-title-input"
      />
      <div class="quick-time-display">
        {{ formatTimeRange(selectedTime) }}
      </div>
      <div class="quick-actions">
        <button @click="createQuickSlot" class="save-btn">儲存</button>
        <button @click="showDetailModal" class="more-btn">更多選項</button>
      </div>
    </div>

    <!-- 編輯 Modal -->
    <div v-if="showEditModal" class="edit-modal-overlay">
      <div class="edit-modal">
        <!-- 簡潔的標題列 -->
        <div class="modal-header">
          <h3>{{ editForm.id ? '編輯' : '新增' }}可預約時段</h3>
          <button @click="closeEditModal" class="close-btn">×</button>
        </div>
        
        <!-- 最少必要欄位 -->
        <div class="modal-content">
          <div class="field-group">
            <label>時段名稱</label>
            <input 
              v-model="editForm.title" 
              placeholder="例如：諮詢服務"
              class="form-input"
            />
          </div>
          
          <div class="field-group">
            <label>時間</label>
            <div class="time-display">
              {{ formatDateTime(editForm.start) }} - {{ formatDateTime(editForm.end) }}
            </div>
          </div>
          
          <div class="field-group">
            <label>備註 <span class="optional">(選填)</span></label>
            <textarea 
              v-model="editForm.description"
              rows="2"
              placeholder="額外說明..."
              class="form-textarea"
            ></textarea>
          </div>
        </div>
        
        <!-- 簡單的操作按鈕 -->
        <div class="modal-footer">
          <button 
            v-if="editForm.id" 
            @click="deleteCurrentSlot" 
            class="delete-btn"
          >
            刪除
          </button>
          <div class="action-buttons">
            <button @click="closeEditModal" class="cancel-btn">取消</button>
            <button @click="saveSlot" class="save-btn">儲存</button>
          </div>
        </div>
      </div>
    </div>

    <!-- 簡單的 Toast 通知 -->
    <div v-if="toast.show" :class="['toast', toast.type]">
      {{ toast.message }}
    </div>
  </div>
</template>

<style scoped>
/* Google Calendar 風格的工具列 */
.calendar-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 24px;
  border-bottom: 1px solid #e5e7eb;
  background-color: #ffffff;
  border-radius: 12px 12px 0 0;
  margin-bottom: 24px;
}

.calendar-nav {
  display: flex;
  align-items: center;
  gap: 8px;
}

.nav-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 36px;
  border: none;
  border-radius: 50%;
  background-color: transparent;
  color: #6b7280;
  cursor: pointer;
  transition: background-color 0.2s;
  font-size: 14px;
}

.nav-btn:hover {
  background-color: #f3f4f6;
}

.today-btn {
  width: auto;
  padding: 0 16px;
  border-radius: 6px;
  font-size: 14px;
  font-weight: 500;
  color: #374151;
}

.today-btn:hover {
  background-color: #f3f4f6;
  color: #1f2937;
}

.current-period {
  font-size: 18px;
  font-weight: 600;
  color: #1f2937;
  margin: 0 16px;
}

.view-switcher {
  display: flex;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  overflow: hidden;
}

.view-btn {
  padding: 8px 16px;
  border: none;
  background-color: #ffffff;
  color: #6b7280;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
}

.view-btn:hover {
  background-color: #f9fafb;
  color: #374151;
}

.view-btn.active {
  background-color: #3b82f6;
  color: #ffffff;
}

/* 載入狀態 */
.loading-overlay {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  background-color: rgba(255, 255, 255, 0.9);
  z-index: 10;
}

.loading-spinner {
  width: 32px;
  height: 32px;
  border: 3px solid #e5e7eb;
  border-top: 3px solid #3b82f6;
  border-radius: 50%;
  animation: spin 1s linear infinite;
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

.loading-text {
  margin-top: 12px;
  color: #6b7280;
  font-size: 14px;
}

/* Google Calendar 樣式 */
.google-calendar {
  font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif;
  padding: 24px;
}

/* FullCalendar 客製化 - 與其他頁面一致的風格 */
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

/* 可預約時段樣式 - 與其他頁面一致 */
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

/* 快速建立氣泡 */
.quick-create-bubble {
  position: fixed;
  background-color: #ffffff;
  border: 1px solid #d1d5db;
  border-radius: 12px;
  box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
  padding: 20px;
  min-width: 320px;
  z-index: 1000;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
}

.quick-title-input {
  width: 100%;
  border: none;
  outline: none;
  font-size: 16px;
  font-weight: 500;
  color: #1f2937;
  margin-bottom: 8px;
  background: transparent;
}

.quick-title-input::placeholder {
  color: #9ca3af;
}

.quick-time-display {
  color: #6b7280;
  font-size: 14px;
  margin-bottom: 16px;
  font-weight: 500;
}

.quick-actions {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
}

.save-btn {
  background-color: #3b82f6;
  color: #ffffff;
  border: none;
  border-radius: 6px;
  padding: 8px 16px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
}

.save-btn:hover {
  background-color: #2563eb;
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
}

.more-btn {
  background-color: transparent;
  color: #3b82f6;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  padding: 8px 16px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
}

.more-btn:hover {
  background-color: #f9fafb;
  border-color: #3b82f6;
}

/* 編輯 Modal */
.edit-modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 16px;
}

.edit-modal {
  background-color: #ffffff;
  border-radius: 12px;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
  width: 100%;
  max-width: 480px;
  max-height: 90vh;
  overflow: hidden;
}

.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 24px 24px 16px;
  border-bottom: 1px solid #e5e7eb;
}

.modal-header h3 {
  font-size: 18px;
  font-weight: 600;
  color: #1f2937;
  margin: 0;
}

.close-btn {
  width: 32px;
  height: 32px;
  border: none;
  border-radius: 50%;
  background-color: transparent;
  color: #6b7280;
  font-size: 20px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s;
}

.close-btn:hover {
  background-color: #f3f4f6;
  color: #374151;
}

.modal-content {
  padding: 24px;
}

.field-group {
  margin-bottom: 20px;
}

.field-group label {
  display: block;
  font-size: 14px;
  font-weight: 500;
  color: #1f2937;
  margin-bottom: 8px;
}

.optional {
  font-weight: 400;
  color: #6b7280;
}

.form-input, .form-textarea {
  width: 100%;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  padding: 12px;
  font-size: 14px;
  color: #1f2937;
  transition: all 0.2s;
  background-color: #ffffff;
}

.form-input:focus, .form-textarea:focus {
  outline: none;
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.form-textarea {
  resize: vertical;
  min-height: 60px;
}

.time-display {
  padding: 12px;
  background-color: #f9fafb;
  border-radius: 6px;
  color: #6b7280;
  font-size: 14px;
  font-weight: 500;
  border: 1px solid #e5e7eb;
}

.modal-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 24px 24px;
  border-top: 1px solid #e5e7eb;
}

.action-buttons {
  display: flex;
  gap: 12px;
}

.cancel-btn {
  background-color: transparent;
  color: #6b7280;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  padding: 8px 16px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
}

.cancel-btn:hover {
  background-color: #f9fafb;
  color: #374151;
  border-color: #9ca3af;
}

.delete-btn {
  background-color: transparent;
  color: #dc2626;
  border: 1px solid #fecaca;
  border-radius: 6px;
  padding: 8px 16px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
}

.delete-btn:hover {
  background-color: #fef2f2;
  border-color: #dc2626;
}

/* Toast 通知 - 與其他頁面一致 */
.toast {
  position: fixed;
  top: 24px;
  right: 24px;
  padding: 12px 16px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
  z-index: 1100;
  animation: slideIn 0.3s ease-out;
}

.toast.success {
  background-color: #d1fae5;
  color: #065f46;
  border: 1px solid #a7f3d0;
}

.toast.error {
  background-color: #fee2e2;
  color: #991b1b;
  border: 1px solid #fca5a5;
}

@keyframes slideIn {
  from {
    transform: translateX(100%);
    opacity: 0;
  }
  to {
    transform: translateX(0);
    opacity: 1;
  }
}

/* 響應式設計 */
@media (max-width: 768px) {
  .calendar-header {
    flex-direction: column;
    gap: 16px;
    padding: 16px;
  }

  .calendar-nav {
    order: 2;
  }

  .view-switcher {
    order: 3;
    width: 100%;
    justify-content: center;
  }

  .current-period {
    font-size: 16px;
    margin: 0 8px;
  }

  .quick-create-bubble {
    left: 16px;
    right: 16px;
    transform: none;
    top: 50%;
    margin-top: -100px;
    min-width: auto;
  }

  .edit-modal {
    margin: 16px;
    width: auto;
  }

  :deep(.fc-timegrid-slot-label) {
    font-size: 10px;
  }

  :deep(.available-slot) {
    font-size: 10px !important;
    padding: 2px 4px !important;
  }

  .google-calendar {
    padding: 16px;
  }
}

@media (max-width: 480px) {
  .current-period {
    font-size: 14px;
  }

  .view-btn {
    padding: 6px 12px;
    font-size: 12px;
  }

  .modal-header h3 {
    font-size: 16px;
  }

  .google-calendar {
    padding: 12px;
  }
}
</style>

