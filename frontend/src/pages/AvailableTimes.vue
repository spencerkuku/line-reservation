<script setup>
import { ref, computed, watch, nextTick, onUnmounted, onMounted } from 'vue'
import FullCalendar from '@fullcalendar/vue3'
import dayGridPlugin from '@fullcalendar/daygrid'
import timeGridPlugin from '@fullcalendar/timegrid'
import interactionPlugin from '@fullcalendar/interaction'
import zhTwLocale from '@fullcalendar/core/locales/zh-tw'
import { apiGet, apiPost, apiPut, apiDelete } from '../utils/api.js'

// API 基礎設定


// 狀態
const calendarEvents = ref([])
const isLoading = ref(false)
const modals = ref({ add: false, edit: false, delete: false })
const currentEvent = ref(null)
const selectInfo = ref(null)
const form = ref({ title: '', description: '', type: 'work', color: '#3b82f6' })
const errors = ref({})
const defaultEventColor = '#3b82f6'

// 獲取可預約時段
async function fetchAvailableTimes() {
  isLoading.value = true
  try {
    const data = await apiGet('/available-times')
    
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
    
    const events = timeData.map(item => ({
      id: item.id,
      title: item.title || `可預約時段`,
      start: item.start_time || item.start,
      end: item.end_time || item.end,
      description: item.description || '',
      type: item.type || 'work',
      color: item.color || defaultEventColor
    }))
    
    calendarEvents.value = events
  } catch (err) {
    showError(`載入失敗: ${err.message}`)
  } finally {
    isLoading.value = false
  }
}

// 新增可預約時段
async function createAvailableTime(timeData) {
  try {
    await apiPost('/available-times', {
      title: timeData.title,
      description: timeData.description,
      start_time: timeData.start_time,
      end_time: timeData.end_time,
      max_capacity: 10, // 默認容量
      type: timeData.type,
      color: timeData.color
    })
    
    await fetchAvailableTimes()
    showSuccess('時段新增成功')
  } catch (err) {
    showError(`新增失敗: ${err.message}`)
  }
}

// 更新可預約時段
async function updateAvailableTime(eventId, updateData) {
  try {
    await apiPut(`/available-times/${eventId}`, {
      title: updateData.title,
      description: updateData.description,
      start_time: updateData.start_time,
      end_time: updateData.end_time,
      max_capacity: 10,
      type: updateData.type,
      color: updateData.color
    })
    await fetchAvailableTimes()
    showSuccess('時段更新成功')
  } catch (err) {
    showError(`更新失敗: ${err.message}`)
  }
}

// 刪除可預約時段
async function deleteAvailableTime(eventId) {
  try {
    await apiDelete(`/available-times/${eventId}`)
    await fetchAvailableTimes()
    showSuccess('時段刪除成功')
  } catch (err) {
    showError(`刪除失敗: ${err.message}`)
  }
}

// FullCalendar 配置
const calendarOptions = ref({
  plugins: [dayGridPlugin, timeGridPlugin, interactionPlugin],
  initialView: 'timeGridWeek',
  locale: zhTwLocale,
  height: 'auto',
  headerToolbar: {
    left: 'prev,next today',
    center: 'title',
    right: 'timeGridWeek,timeGridDay,dayGridMonth'
  },
  selectable: true,
  selectMirror: true,
  editable: true,
  eventResizableFromStart: true,
  slotMinTime: '06:00:00',
  slotMaxTime: '24:00:00',
  slotDuration: '00:30:00',
  snapDuration: '00:15:00',
  allDaySlot: false,
  nowIndicator: true,
  weekNumbers: true,
  events: calendarEvents.value,
  eventDisplay: 'block',
  eventClassNames: () => ['event-work'],
  select: handleSelect,
  eventClick: handleEventClick,
  eventChange: handleEventChange,
  eventDrop: handleEventDrop,
  eventResize: handleEventResize
})

watch(calendarEvents, newEvents => {
  calendarOptions.value.events = [...newEvents]
}, { deep: true })

// 事件處理
function handleSelect(info) {
  // 檢查選擇的時間是否為過去時間
  const now = new Date()
  const startTime = new Date(info.start)
  
  if (startTime <= now) {
    return showError('無法選擇過去的時間，請選擇未來的時間段')
  }
  
  const startHour = startTime.getHours()
  if (startHour < 6) return showError('營業時間不得早於早上6點')
  if (startHour >= 24) return showError('營業時間不得晚於晚上12點')
  
  selectInfo.value = info
  resetForm()
  modals.value.add = true
  nextTick(() => document.querySelector('#event-title-input')?.focus())
}

function handleEventClick(info) {
  currentEvent.value = info.event
  form.value.title = info.event.title
  form.value.description = info.event.extendedProps.description || ''
  modals.value.edit = true
}

async function handleEventChange(info) {
  // 當事件被拖拽或調整大小時，更新到後端
  const eventData = {
    title: info.event.title,
    description: info.event.extendedProps.description || '',
    start_time: formatLocalDateTime(info.event.start),
    end_time: formatLocalDateTime(info.event.end),
    type: info.event.extendedProps.type || 'work',
    color: info.event.backgroundColor || defaultEventColor
  }
  
  await updateAvailableTime(info.event.id, eventData)
}

async function handleEventDrop(info) {
  // 檢查拖拽後的時間是否為過去時間
  const now = new Date()
  const startTime = new Date(info.event.start)
  
  if (startTime <= now) {
    info.revert()
    return showError('無法移動到過去的時間')
  }
  
  const startHour = startTime.getHours()
  if (startHour < 6 || startHour >= 23) {
    info.revert()
    return showError('營業時間必須在早上6點到晚上11點之間')
  }
  
  // 更新事件到後端
  await handleEventChange(info)
}

async function handleEventResize(info) {
  const start = new Date(info.event.start)
  const end = new Date(info.event.end)
  const durationMs = end.getTime() - start.getTime()
  const durationMinutes = Math.floor(durationMs / (1000 * 60)) // 正確計算分鐘數
  
  console.log('事件調整大小:', {
    start: start.toISOString(),
    end: end.toISOString(),
    durationMs: durationMs,
    durationMinutes: durationMinutes
  })
  
  if (durationMinutes < 30) {
    info.revert()
    return showError('事件持續時間不得少於30分鐘')
  }
  if (durationMinutes > 720) {
    info.revert()
    return showError('單次排班不得超過12小時')
  }
  
  // 更新事件到後端
  await handleEventChange(info)
}

// 表單
function resetForm() {
  form.value = { title: '', description: '', type: 'work', color: defaultEventColor }
  errors.value = {}
}

function validateForm() {
  errors.value = {}
  if (!form.value.title.trim()) errors.value.title = '請輸入事件名稱'
  if (form.value.title.length > 50) errors.value.title = '事件名稱不得超過50個字元'
  return Object.keys(errors.value).length === 0
}

// 格式化本地時間為 ISO 字符串但保持本地時區
function formatLocalDateTime(date) {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  const hours = String(date.getHours()).padStart(2, '0')
  const minutes = String(date.getMinutes()).padStart(2, '0')
  const seconds = String(date.getSeconds()).padStart(2, '0')
  
  return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`
}

// CRUD
async function addEvent() {
  if (!validateForm()) return
  
  // 驗證時間長度
  const start = new Date(selectInfo.value.start)
  const end = new Date(selectInfo.value.end)
  const durationMs = end.getTime() - start.getTime()
  const durationMinutes = Math.floor(durationMs / (1000 * 60))
  
  console.log('新增事件時間驗證:', {
    selectInfo: selectInfo.value,
    start: start.toISOString(),
    end: end.toISOString(),
    startLocal: start.toString(),
    endLocal: end.toString(),
    durationMs: durationMs,
    durationMinutes: durationMinutes
  })
  
  if (durationMinutes < 30) {
    return showError('事件持續時間不得少於30分鐘')
  }
  if (durationMinutes > 720) {
    return showError('單次排班不得超過12小時')
  }
  
  isLoading.value = true
  
  const eventData = {
    title: form.value.title.trim(),
    start_time: formatLocalDateTime(start),
    end_time: formatLocalDateTime(end),
    description: form.value.description.trim(),
    type: form.value.type,
    color: form.value.color
  }
  
  console.log('發送事件數據:', eventData)
  
  await createAvailableTime(eventData)
  
  modals.value.add = false
  resetForm()
  isLoading.value = false
}

async function updateEvent() {
  if (!validateForm()) return
  isLoading.value = true
  
  const eventData = {
    title: form.value.title.trim(),
    description: form.value.description.trim(),
    start_time: formatLocalDateTime(currentEvent.value.start),
    end_time: formatLocalDateTime(currentEvent.value.end),
    type: form.value.type,
    color: form.value.color
  }
  
  await updateAvailableTime(currentEvent.value.id, eventData)
  
  modals.value.edit = false
  resetForm()
  isLoading.value = false
}

function confirmDelete() {
  modals.value.edit = false
  modals.value.delete = true
}

async function deleteEvent() {
  isLoading.value = true
  
  await deleteAvailableTime(currentEvent.value.id)
  
  modals.value.delete = false
  currentEvent.value = null
  resetForm()
  isLoading.value = false
}

// 通知
const notification = ref({ show: false, type: 'success', message: '' })
import { defineComponent, h } from 'vue'

const SuccessIcon = defineComponent({
  name: 'SuccessIcon',
  render() {
    return h('svg', {
      xmlns: 'http://www.w3.org/2000/svg',
      fill: 'currentColor',
      class: 'text-green-500',
      viewBox: '0 0 20 20'
    }, [
      h('path', {
        'fill-rule': 'evenodd',
        d: 'M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.707a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z',
        'clip-rule': 'evenodd'
      })
    ])
  }
})

const ErrorIcon = defineComponent({
  name: 'ErrorIcon',
  render() {
    return h('svg', {
      xmlns: 'http://www.w3.org/2000/svg',
      fill: 'none',
      class: 'text-red-500',
      viewBox: '0 0 24 24',
      stroke: 'currentColor'
    }, [
      h('path', {
        'stroke-linecap': 'round',
        'stroke-linejoin': 'round',
        'stroke-width': '2',
        d: 'M6 18L18 6M6 6l12 12'
      })
    ])
  }
})

const WarningIcon = defineComponent({
  name: 'WarningIcon',
  render() {
    return h('svg', {
      xmlns: 'http://www.w3.org/2000/svg',
      fill: 'currentColor',
      class: 'text-yellow-500',
      viewBox: '0 0 20 20'
    }, [
      h('path', {
        'fill-rule': 'evenodd',
        d: 'M8.257 3.099c.765-1.36 2.72-1.36 3.485 0l6.518 11.597c.75 1.335-.213 2.998-1.742 2.998H3.48c-1.53 0-2.492-1.663-1.743-2.998L8.257 3.1zM11 13a1 1 0 10-2 0 1 1 0 002 0zm-1-2a1 1 0 01-1-1V7a1 1 0 112 0v3a1 1 0 01-1 1z',
        'clip-rule': 'evenodd'
      })
    ])
  }
})
const notificationIconComponent = computed(() => {
  switch (notification.value.type) {
    case 'success': return SuccessIcon
    case 'error': return ErrorIcon
    case 'warning': return WarningIcon
    default: return null
  }
})
function showSuccess(msg) { showNotification('success', msg) }
function showError(msg) { showNotification('error', msg) }
function showNotification(type, message) {
  notification.value = { show: true, type, message }
  setTimeout(() => { notification.value.show = false }, 3000)
}

// 快捷鍵
function handleKeydown(e) {
  if (e.key === 'Escape') closeAllModals()
  if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
    if (modals.value.add) addEvent()
    if (modals.value.edit) updateEvent()
  }
}
function closeAllModals() {
  modals.value.add = false
  modals.value.edit = false
  modals.value.delete = false
  resetForm()
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
    <!-- 頁面標題 -->
    <div class="mb-8">
      <h1 class="text-3xl font-bold text-gray-900">可預約時段管理</h1>
      <p class="text-gray-600 mt-2">管理您的營業時間與可預約時段，拖拽即可調整時間</p>
    </div>

    <!-- 行事曆容器 -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
      <!-- 行事曆標題列 -->
      <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
        <div class="flex items-center justify-between">
          <div>
            <h3 class="text-lg font-semibold text-gray-900">時段排程</h3>
            <p class="text-sm text-gray-600 mt-1">使用拖拽功能快速安排可預約時段</p>
          </div>
          <div class="flex items-center space-x-2">
            <div class="flex items-center text-sm text-gray-600">
              <div class="w-3 h-3 bg-blue-500 rounded mr-2"></div>
              可預約時段
            </div>
          </div>
        </div>
      </div>
      
      <!-- 載入狀態 -->
      <div v-if="isLoading" class="p-12 text-center">
        <div class="inline-flex items-center justify-center w-8 h-8">
          <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
        </div>
        <p class="mt-4 text-sm text-gray-500">載入行事曆中...</p>
      </div>

      <!-- 行事曆組件 -->
      <div v-else class="calendar-wrapper">
        <FullCalendar :options="calendarOptions" class="professional-calendar" />
      </div>
    </div>

    

    <!-- 新增時段 Modal -->
    <div v-if="modals.add" class="fixed inset-0 bg-black bg-opacity-50 flex justify-center items-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-xl max-w-md w-full">
        <!-- Modal 標題 -->
        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50 rounded-t-xl">
          <div class="flex items-center justify-between">
            <div>
              <h3 class="text-lg font-semibold text-gray-900">新增可預約時段</h3>
              <p class="text-sm text-gray-600 mt-1">建立新的可預約時間段</p>
            </div>
            <button
              @click="modals.add = false"
              class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-colors"
            >
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
        </div>

        <!-- Modal 內容 -->
        <form @submit.prevent="addEvent" class="p-6">
          <div class="space-y-4">
            <div>
              <label class="text-sm font-medium text-gray-700 mb-2 block">時段名稱 *</label>
              <input
                id="event-title-input"
                v-model="form.title"
                type="text"
                placeholder="例如：美髮服務、諮詢時段"
                autocomplete="off"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                :class="{ 'border-red-300 focus:ring-red-500 focus:border-red-500': errors.title }"
              />
              <p v-if="errors.title" class="text-sm text-red-600 mt-1 flex items-center">
                <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                </svg>
                {{ errors.title }}
              </p>
            </div>

            <div>
              <label class="text-sm font-medium text-gray-700 mb-2 block">備註說明</label>
              <textarea
                v-model="form.description"
                rows="3"
                placeholder="選填：服務內容、特殊要求或其他說明..."
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors resize-none"
              ></textarea>
            </div>
          </div>

          <!-- Modal 操作按鈕 -->
          <div class="flex justify-end space-x-3 pt-6 mt-6 border-t border-gray-200">
            <button
              type="button"
              @click="modals.add = false"
              :disabled="isLoading"
              class="px-4 py-2 text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-gray-500 transition-colors disabled:opacity-50"
            >
              取消
            </button>
            <button
              type="submit"
              :disabled="isLoading"
              class="px-6 py-2 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-lg hover:from-blue-700 hover:to-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 transition-all duration-200 flex items-center"
            >
              <svg v-if="isLoading" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
              {{ isLoading ? '新增中...' : '新增時段' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- 編輯時段 Modal -->
    <div v-if="modals.edit" class="fixed inset-0 bg-black bg-opacity-50 flex justify-center items-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-xl max-w-md w-full">
        <!-- Modal 標題 -->
        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50 rounded-t-xl">
          <div class="flex items-center justify-between">
            <div>
              <h3 class="text-lg font-semibold text-gray-900">編輯可預約時段</h3>
              <p class="text-sm text-gray-600 mt-1">修改時段資訊與設定</p>
            </div>
            <button
              @click="confirmDelete"
              class="inline-flex items-center p-2 text-red-600 hover:text-red-700 hover:bg-red-50 rounded-lg transition-colors"
              title="刪除時段"
            >
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
              </svg>
            </button>
          </div>
        </div>

        <!-- Modal 內容 -->
        <form @submit.prevent="updateEvent" class="p-6">
          <div class="space-y-4">
            <div>
              <label class="text-sm font-medium text-gray-700 mb-2 block">時段名稱 *</label>
              <input
                v-model="form.title"
                type="text"
                placeholder="例如：美髮服務、諮詢時段"
                autocomplete="off"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                :class="{ 'border-red-300 focus:ring-red-500 focus:border-red-500': errors.title }"
              />
              <p v-if="errors.title" class="text-sm text-red-600 mt-1 flex items-center">
                <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                </svg>
                {{ errors.title }}
              </p>
            </div>

            <div>
              <label class="text-sm font-medium text-gray-700 mb-2 block">備註說明</label>
              <textarea
                v-model="form.description"
                rows="3"
                placeholder="選填：服務內容、特殊要求或其他說明..."
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors resize-none"
              ></textarea>
            </div>
          </div>

          <!-- Modal 操作按鈕 -->
          <div class="flex justify-end space-x-3 pt-6 mt-6 border-t border-gray-200">
            <button
              type="button"
              @click="modals.edit = false"
              :disabled="isLoading"
              class="px-4 py-2 text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-gray-500 transition-colors disabled:opacity-50"
            >
              取消
            </button>
            <button
              type="submit"
              :disabled="isLoading"
              class="px-6 py-2 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-lg hover:from-blue-700 hover:to-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 transition-all duration-200 flex items-center"
            >
              <svg v-if="isLoading" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
              {{ isLoading ? '更新中...' : '儲存變更' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- 刪除確認 Modal -->
    <div v-if="modals.delete" class="fixed inset-0 bg-black bg-opacity-50 flex justify-center items-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-xl max-w-md w-full">
        <!-- Modal 標題 -->
        <div class="px-6 py-4 border-b border-gray-200 bg-red-50 rounded-t-xl">
          <div class="flex items-center">
            <div class="flex-shrink-0">
              <svg class="h-8 w-8 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.5 0L4.304 16.5c-.77.833.192 2.5 1.732 2.5z" />
              </svg>
            </div>
            <div class="ml-4">
              <h3 class="text-lg font-semibold text-red-900">確認刪除時段</h3>
              <p class="text-sm text-red-700 mt-1">此操作無法復原</p>
            </div>
          </div>
        </div>

        <!-- Modal 內容 -->
        <div class="p-6">
          <p class="text-gray-700 mb-2">
            確定要刪除時段「<strong class="text-gray-900">{{ currentEvent?.title }}</strong>」嗎？
          </p>
          <p class="text-sm text-gray-500">
            刪除後，此時段的所有相關預約資料也將一併移除，且無法恢復。
          </p>

          <!-- Modal 操作按鈕 -->
          <div class="flex justify-end space-x-3 pt-6 mt-6 border-t border-gray-200">
            <button
              @click="modals.delete = false"
              :disabled="isLoading"
              class="px-4 py-2 text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-gray-500 transition-colors disabled:opacity-50"
            >
              取消
            </button>
            <button
              @click="deleteEvent"
              :disabled="isLoading"
              class="px-6 py-2 bg-gradient-to-r from-red-600 to-red-700 text-white rounded-lg hover:from-red-700 hover:to-red-800 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 disabled:opacity-50 transition-all duration-200 flex items-center"
            >
              <svg v-if="isLoading" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
              {{ isLoading ? '刪除中...' : '確認刪除' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- 通知系統 -->
    <transition name="fade" appear>
      <div v-if="notification.show" class="fixed top-4 right-4 z-50 w-full max-w-sm" role="alert">
        <div class="rounded-xl p-4 shadow-lg flex items-start space-x-3 relative border"
          :class="{
            'bg-green-50 text-green-800 border-green-200': notification.type === 'success',
            'bg-red-50 text-red-800 border-red-200': notification.type === 'error',
            'bg-yellow-50 text-yellow-800 border-yellow-200': notification.type === 'warning'
          }">
          <div class="flex-shrink-0">
            <component :is="notificationIconComponent" class="h-5 w-5" aria-hidden="true" />
          </div>
          <div class="ml-2 text-sm font-medium flex-1">{{ notification.message }}</div>
          <button type="button" class="absolute top-2 right-2 text-gray-400 hover:text-gray-600 p-1 rounded-lg hover:bg-white hover:bg-opacity-50"
            @click="notification.show = false" aria-label="關閉通知">
            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none"
              viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
      </div>
    </transition>
  </div>
</template>

<style scoped>
/* 淡入淡出動畫 */
.fade-enter-active, .fade-leave-active {
  transition: opacity 0.3s ease;
}
.fade-enter-from, .fade-leave-to {
  opacity: 0;
}

/* 專業化的行事曆樣式 */
.calendar-wrapper {
  padding: 1.5rem;
}

.professional-calendar {
  min-height: 600px;
}

/* FullCalendar 客製化樣式 */
:deep(.fc) {
  font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif;
}

:deep(.fc-toolbar-title) {
  font-size: 1.5rem !important;
  font-weight: 700 !important;
  color: #1f2937 !important;
}

:deep(.fc-button-primary) {
  background-color: #3b82f6 !important;
  border-color: #3b82f6 !important;
  color: white !important;
  font-weight: 500 !important;
  border-radius: 0.5rem !important;
  padding: 0.5rem 1rem !important;
  transition: all 0.2s ease !important;
}

:deep(.fc-button-primary:hover) {
  background-color: #2563eb !important;
  border-color: #2563eb !important;
  transform: translateY(-1px) !important;
  box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4) !important;
}

:deep(.fc-button-primary:focus) {
  outline: none !important;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.5) !important;
}

:deep(.fc-button-primary:disabled) {
  opacity: 0.5 !important;
  cursor: not-allowed !important;
}

:deep(.fc-daygrid-event) {
  border-radius: 0.375rem !important;
  border: none !important;
  padding: 0.25rem 0.5rem !important;
  font-weight: 500 !important;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1) !important;
}

:deep(.fc-timegrid-event) {
  border-radius: 0.375rem !important;
  border: none !important;
  padding: 0.25rem 0.5rem !important;
  font-weight: 500 !important;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1) !important;
}

:deep(.fc-event:hover) {
  transform: translateY(-1px) !important;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15) !important;
  transition: all 0.2s ease !important;
}

:deep(.fc-col-header-cell) {
  background-color: #f9fafb !important;
  font-weight: 600 !important;
  color: #374151 !important;
  border-color: #e5e7eb !important;
}

:deep(.fc-daygrid-day) {
  transition: background-color 0.2s ease !important;
}

:deep(.fc-daygrid-day:hover) {
  background-color: #f3f4f6 !important;
}

:deep(.fc-highlight) {
  background-color: rgba(59, 130, 246, 0.1) !important;
}

:deep(.fc-select-mirror) {
  background-color: rgba(59, 130, 246, 0.3) !important;
  border-radius: 0.375rem !important;
}

:deep(.fc-timegrid-slot) {
  border-color: #f3f4f6 !important;
}

:deep(.fc-timegrid-axis) {
  border-color: #e5e7eb !important;
}

:deep(.fc-scrollgrid) {
  border-color: #e5e7eb !important;
  border-radius: 0.5rem !important;
  overflow: hidden !important;
}

:deep(.fc-now-indicator-line) {
  border-color: #ef4444 !important;
  border-width: 2px !important;
}

:deep(.fc-now-indicator-arrow) {
  border-top-color: #ef4444 !important;
}
</style>

