<script setup>
import { ref, onMounted } from 'vue'
import { apiGet, apiPost, apiPut, apiDelete, apiUpload } from '../utils/api.js'

const services = ref([])
const loading = ref(false)
const error = ref('')
const showForm = ref(false)
const showEditForm = ref(false)
const editingService = ref(null)
const newTitle = ref('')
const newDescription = ref('')
const newSlotDuration = ref(30)
const newPrice = ref('')
const newImage = ref(null)
const imagePreview = ref(null)

// 獲取服務列表
async function fetchServices() {
  loading.value = true
  error.value = ''
  try {
    const data = await apiGet('/services')
    services.value = data.data || data
  } catch (err) {
    error.value = err.message
    if (import.meta.env.DEV) {
      console.error('Error fetching services:', err)
    }
  } finally {
    loading.value = false
  }
}

// 新增服務
async function addService() {
  if (!newTitle.value.trim() || !newDescription.value.trim()) {
    alert('請完整輸入服務資料')
    return
  }

  try {
    const formData = new FormData()
    formData.append('name', newTitle.value.trim())
    formData.append('description', newDescription.value.trim())
    formData.append('duration', newSlotDuration.value)
    if (newPrice.value) {
      formData.append('price', parseFloat(newPrice.value))
    }
    if (newImage.value) {
      formData.append('image', newImage.value)
    }

    await apiUpload('/services', formData)
    await fetchServices()
    toggleForm()
    alert('服務新增成功')
  } catch (err) {
    alert(`新增失敗: ${err.message}`)
  }
}

// 刪除服務
async function deleteService(service) {
  if (!confirm(`確定要刪除服務 "${service.name || service.title}" 嗎？`)) return

  try {
    // 先檢查是否有相關預約
    try {
      const reservationData = await apiGet(`/services/${service.id}/reservations`)
      if (reservationData.count > 0) {
        alert(`無法刪除：此服務還有 ${reservationData.count} 個預約記錄`)
        return
      }
    } catch (checkErr) {
      // 如果檢查失敗，繼續刪除流程
      if (import.meta.env.DEV) {
        console.warn('檢查預約記錄失敗:', checkErr.message)
      }
    }

    await apiDelete(`/services/${service.id}`)
    await fetchServices()
    alert('服務已刪除')
  } catch (err) {
    alert(`刪除失敗: ${err.message}`)
  }
}

// 編輯服務
function editService(service) {
  editingService.value = { ...service }
  newTitle.value = service.name || service.title
  newDescription.value = service.description
  newSlotDuration.value = service.duration || service.slotDuration || 30
  newPrice.value = service.price || ''
  showEditForm.value = true
}

// 更新服務
async function updateService() {
  if (!newTitle.value.trim() || !newDescription.value.trim()) {
    alert('請完整輸入服務資料')
    return
  }

  try {
    const formData = new FormData()
    formData.append('name', newTitle.value.trim())
    formData.append('description', newDescription.value.trim())
    formData.append('duration', newSlotDuration.value)
    if (newPrice.value) {
      formData.append('price', parseFloat(newPrice.value))
    }
    if (newImage.value) {
      formData.append('image', newImage.value)
    }

    // 對於 Laravel，需要添加 _method 字段來支持 PUT 請求
    formData.append('_method', 'PUT')

    await apiUpload(`/services/${editingService.value.id}`, formData, 'POST')
    await fetchServices()
    toggleEditForm()
    alert('服務更新成功')
  } catch (err) {
    alert(`更新失敗: ${err.message}`)
  }
}

function resetForm() {
  newTitle.value = ''
  newDescription.value = ''
  newSlotDuration.value = 30
  newPrice.value = ''
  newImage.value = null
  imagePreview.value = null
}

function toggleForm() {
  showForm.value = !showForm.value
  if (showForm.value) resetForm()
}

function toggleEditForm() {
  showEditForm.value = !showEditForm.value
  if (showEditForm.value) {
    resetForm()
  } else {
    editingService.value = null
  }
}

function onImageChange(e) {
  const file = e.target.files[0]
  if (!file) return
  newImage.value = file
  const reader = new FileReader()
  reader.onload = () => { imagePreview.value = reader.result }
  reader.readAsDataURL(file)
}

// 清除圖片預覽
function clearImage() {
  newImage.value = null
  imagePreview.value = null
}

// 頁面載入時獲取服務列表
onMounted(() => {
  fetchServices()
})
</script>

<template>
  <div class="min-h-screen bg-gray-50 p-6">
    <!-- 頁面標題區域 -->
    <div class="mb-8">
      <div class="flex justify-between items-center">
        <div>
          <h1 class="text-3xl font-bold text-gray-900">服務項目</h1>
          <p class="text-gray-600 mt-2">管理系統提供的服務類型與設定</p>
        </div>
        <button
          @click="toggleForm"
          class="inline-flex items-center px-4 py-2.5 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors"
        >
          <svg v-if="!showForm" class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
          </svg>
          <svg v-else class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
          {{ showForm ? '取消新增' : '新增服務' }}
        </button>
      </div>
    </div>

    <!-- 載入狀態 -->
    <div v-if="loading" class="bg-white rounded-xl shadow-sm border border-gray-200 p-12 text-center">
      <div class="inline-flex items-center justify-center w-16 h-16 bg-blue-100 rounded-full mb-4">
        <svg class="animate-spin w-8 h-8 text-blue-600" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
      </div>
      <p class="text-gray-600 font-medium">載入中...</p>
    </div>

    <!-- 錯誤狀態 -->
    <div v-else-if="error" class="bg-white rounded-xl shadow-sm border border-gray-200 p-12 text-center">
      <div class="inline-flex items-center justify-center w-16 h-16 bg-red-100 rounded-full mb-4">
        <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.732 15.5c-.77.833.192 2.5 1.732 2.5z" />
        </svg>
      </div>
      <p class="text-red-600 font-medium mb-2">{{ error }}</p>
      <button 
        @click="fetchServices" 
        class="text-blue-600 hover:text-blue-800 font-medium"
      >
        重新載入
      </button>
    </div>

    <!-- 服務列表 -->
    <div v-else class="space-y-6">
      <!-- 空狀態 -->
      <div v-if="services.length === 0" class="bg-white rounded-xl shadow-sm border border-gray-200 p-12 text-center">
        <div class="inline-flex items-center justify-center w-16 h-16 bg-gray-100 rounded-full mb-4">
          <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
          </svg>
        </div>
        <p class="text-gray-600 font-medium">尚無服務項目</p>
        <p class="text-gray-500 text-sm mt-1">點擊上方按鈕新增第一個服務</p>
      </div>

      <!-- 服務卡片網格 -->
      <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <div
          v-for="service in services"
          :key="service.id"
          class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden hover:shadow-md transition-shadow group"
        >
          <!-- 服務圖片 -->
          <div class="relative h-48 bg-gradient-to-br from-blue-400 to-blue-600">
            <img 
              v-if="service.full_image_url" 
              :src="service.full_image_url" 
              :alt="service.name || service.title"
              class="w-full h-full object-cover"
              @error="handleImageError($event)"
              @load="handleImageLoad($event)"
            />
            <div v-else class="w-full h-full flex items-center justify-center">
              <svg class="w-16 h-16 text-white opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
              </svg>
            </div>
            
            <!-- 操作按鈕浮層 -->
            <div class="absolute top-3 right-3 flex space-x-2 opacity-0 group-hover:opacity-100 transition-opacity">
              <button
                @click="editService(service)"
                class="w-8 h-8 bg-white bg-opacity-90 text-blue-600 rounded-full hover:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 flex items-center justify-center transition-colors"
                title="編輯服務"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
              </button>
              <button
                @click="deleteService(service)"
                class="w-8 h-8 bg-white bg-opacity-90 text-red-600 rounded-full hover:bg-white focus:outline-none focus:ring-2 focus:ring-red-500 flex items-center justify-center transition-colors"
                title="刪除服務"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
              </button>
            </div>
          </div>

          <!-- 服務資訊 -->
          <div class="p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-2">{{ service.name || service.title }}</h3>
            <p class="text-gray-600 text-sm mb-4 line-clamp-3">{{ service.description }}</p>
            
            <!-- 服務詳情 -->
            <div class="flex items-center justify-between text-sm mb-4">
              <div class="flex items-center text-gray-500">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                {{ service.duration || service.slotDuration || 30 }} 分鐘
              </div>
              <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                啟用中
              </span>
            </div>

            <!-- 價格顯示 -->
            <div v-if="service.price" class="flex items-center justify-between pt-3 border-t border-gray-100">
              <span class="text-sm text-gray-600">服務價格</span>
              <span class="text-lg font-semibold text-blue-600">NT$ {{ Number(service.price).toLocaleString() }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- 新增服務表單 -->
    <div v-if="showForm" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-md transform transition-all">
        <div class="p-6">
          <!-- 模態框標題 -->
          <div class="flex items-center justify-between mb-6">
            <h3 class="text-xl font-semibold text-gray-900">新增服務</h3>
            <button @click="toggleForm" class="text-gray-400 hover:text-gray-600 transition-colors">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
          
          <!-- 表單 -->
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">服務名稱</label>
              <input
                v-model="newTitle"
                type="text"
                placeholder="請輸入服務名稱"
                class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors"
              />
            </div>
            
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">服務描述</label>
              <textarea
                v-model="newDescription"
                placeholder="請輸入服務描述"
                rows="3"
                class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors resize-none"
              ></textarea>
            </div>
            
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">預約時長</label>
              <div class="relative">
                <input
                  v-model="newSlotDuration"
                  type="number"
                  min="5"
                  max="480"
                  step="5"
                  placeholder="30"
                  class="w-full pr-12 px-3 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors"
                />
                <span class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-500">分鐘</span>
              </div>
              <p class="text-xs text-gray-500 mt-1">建議：5-480分鐘，5分鐘為一個單位</p>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">服務價格</label>
              <div class="relative">
                <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500">NT$</span>
                <input
                  v-model="newPrice"
                  type="number"
                  min="0"
                  step="1"
                  placeholder="0"
                  class="w-full pl-12 pr-3 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors"
                />
              </div>
              <p class="text-xs text-gray-500 mt-1">選填，不填寫表示免費服務</p>
            </div>
            
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">服務圖片</label>
              <input
                type="file"
                accept="image/*"
                @change="onImageChange"
                class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors"
              />
              
              <!-- 圖片預覽 -->
              <div v-if="imagePreview" class="mt-3">
                <p class="text-sm text-gray-600 mb-2">圖片預覽：</p>
                <div class="relative">
                  <img :src="imagePreview" class="w-full h-32 object-cover rounded-lg border" />
                  <button
                    @click="clearImage"
                    class="absolute top-2 right-2 w-6 h-6 bg-red-500 text-white rounded-full text-xs hover:bg-red-600 flex items-center justify-center"
                  >
                    ×
                  </button>
                </div>
              </div>
            </div>
          </div>
          
          <!-- 按鈕 -->
          <div class="flex justify-end space-x-3 mt-8">
            <button 
              @click="toggleForm" 
              class="px-4 py-2.5 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-500 transition-colors"
            >
              取消
            </button>
            <button 
              @click="addService" 
              class="px-4 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors"
            >
              新增服務
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- 編輯服務表單 -->
    <div v-if="showEditForm" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-md transform transition-all">
        <div class="p-6">
          <!-- 模態框標題 -->
          <div class="flex items-center justify-between mb-6">
            <h3 class="text-xl font-semibold text-gray-900">編輯服務</h3>
            <button @click="toggleEditForm" class="text-gray-400 hover:text-gray-600 transition-colors">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
          
          <!-- 表單 -->
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">服務名稱</label>
              <input
                v-model="newTitle"
                type="text"
                placeholder="請輸入服務名稱"
                class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors"
              />
            </div>
            
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">服務描述</label>
              <textarea
                v-model="newDescription"
                placeholder="請輸入服務描述"
                rows="3"
                class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors resize-none"
              ></textarea>
            </div>
            
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">預約時長</label>
              <div class="relative">
                <input
                  v-model="newSlotDuration"
                  type="number"
                  min="5"
                  max="480"
                  step="5"
                  placeholder="30"
                  class="w-full pr-12 px-3 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors"
                />
                <span class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-500">分鐘</span>
              </div>
              <p class="text-xs text-gray-500 mt-1">建議：5-480分鐘，5分鐘為一個單位</p>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">服務價格</label>
              <div class="relative">
                <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500">NT$</span>
                <input
                  v-model="newPrice"
                  type="number"
                  min="0"
                  step="1"
                  placeholder="0"
                  class="w-full pl-12 pr-3 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors"
                />
              </div>
              <p class="text-xs text-gray-500 mt-1">選填，不填寫表示免費服務</p>
            </div>
            
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">服務圖片</label>
              <input
                type="file"
                accept="image/*"
                @change="onImageChange"
                class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors"
              />
              
              <!-- 圖片預覽 -->
              <div v-if="imagePreview" class="mt-3">
                <p class="text-sm text-gray-600 mb-2">圖片預覽：</p>
                <div class="relative">
                  <img :src="imagePreview" class="w-full h-32 object-cover rounded-lg border" />
                  <button
                    @click="clearImage"
                    class="absolute top-2 right-2 w-6 h-6 bg-red-500 text-white rounded-full text-xs hover:bg-red-600 flex items-center justify-center"
                  >
                    ×
                  </button>
                </div>
              </div>
            </div>
          </div>
          
          <!-- 按鈕 -->
          <div class="flex justify-end space-x-3 mt-8">
            <button 
              @click="toggleEditForm" 
              class="px-4 py-2.5 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-500 transition-colors"
            >
              取消
            </button>
            <button 
              @click="updateService" 
              class="px-4 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors"
            >
              更新服務
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
