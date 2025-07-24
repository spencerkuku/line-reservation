<template>
  <div class="min-h-screen bg-gray-50 p-6">
    <!-- 頁面標題 -->
    <div class="mb-8">
      <h1 class="text-3xl font-bold text-gray-900">個人資訊</h1>
      <p class="text-gray-600 mt-2">管理您的個人資料、頭像和帳號安全設定</p>
    </div>

    <!-- 主要內容區域 -->
    <div class="max-w-4xl mx-auto space-y-8">
      <!-- 個人資料卡片 -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
          <h3 class="text-lg font-semibold text-gray-900">個人資料</h3>
          <p class="text-sm text-gray-600 mt-1">更新您的個人資訊和聯絡方式</p>
        </div>
        
        <div class="p-6">
          <form @submit.prevent="updateProfile" class="space-y-6">
            <!-- 頭像上傳 -->
            <div class="flex items-center space-x-6">
              <div class="flex-shrink-0">
                <img 
                  :src="profileForm.avatar || currentUser.imageUrl || currentUser.avatar ? `http://127.0.0.1:8000/storage/${currentUser.avatar}` : 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?ixlib=rb-1.2.1&ixid=eyJhcHBfaWQiOjEyMDd9&auto=format&fit=facearea&facepad=2&w=256&h=256&q=80'" 
                  alt="用戶頭像"
                  class="w-20 h-20 rounded-full object-cover border-4 border-gray-200"
                  @error="handleImageError"
                />
              </div>
              <div class="flex-1">
                <h4 class="text-sm font-medium text-gray-900 mb-2">個人頭像</h4>
                <div class="flex items-center space-x-4">
                  <label class="cursor-pointer inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    上傳新頭像
                    <input 
                      type="file" 
                      accept="image/*" 
                      @change="onAvatarChange" 
                      class="hidden"
                    />
                  </label>
                  <button 
                    v-if="profileForm.avatar"
                    @click="removeAvatar" 
                    type="button"
                    class="px-4 py-2 text-sm font-medium text-red-600 hover:text-red-800 transition-colors"
                  >
                    移除
                  </button>
                </div>
                <p class="text-xs text-gray-500 mt-2">建議使用 JPG、PNG 格式，檔案大小不超過 2MB</p>
              </div>
            </div>

            <!-- 基本資訊 -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">姓名</label>
                <input
                  v-model="profileForm.name"
                  type="text"
                  required
                  class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors"
                  placeholder="請輸入您的姓名"
                />
              </div>
              
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">電子信箱</label>
                <input
                  v-model="profileForm.email"
                  type="email"
                  required
                  class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors"
                  placeholder="請輸入您的電子信箱"
                />
              </div>
            </div>

            <!-- 保存按鈕 -->
            <div class="flex justify-end pt-4">
              <button
                type="submit"
                :disabled="profileLoading"
                class="inline-flex items-center px-6 py-2.5 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
              >
                <svg v-if="profileLoading" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                {{ profileLoading ? '保存中...' : '保存更改' }}
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- 密碼修改卡片 -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
          <h3 class="text-lg font-semibold text-gray-900">密碼安全</h3>
          <p class="text-sm text-gray-600 mt-1">更改您的登入密碼以保護帳號安全</p>
        </div>
        
        <div class="p-6">
          <form @submit.prevent="updatePassword" class="space-y-6">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">目前密碼</label>
              <input
                v-model="passwordForm.current_password"
                type="password"
                required
                class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors"
                placeholder="請輸入目前的密碼"
              />
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">新密碼</label>
                <input
                  v-model="passwordForm.new_password"
                  type="password"
                  required
                  minlength="8"
                  class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors"
                  placeholder="請輸入新密碼"
                />
                <p class="text-xs text-gray-500 mt-1">密碼長度至少 8 個字元</p>
              </div>
              
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">確認新密碼</label>
                <input
                  v-model="passwordForm.new_password_confirmation"
                  type="password"
                  required
                  class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors"
                  placeholder="請再次輸入新密碼"
                />
              </div>
            </div>

            <!-- 密碼強度指示器 -->
            <div v-if="passwordForm.new_password" class="space-y-2">
              <div class="text-sm font-medium text-gray-700">密碼強度</div>
              <div class="flex space-x-1">
                <div 
                  v-for="i in 4" 
                  :key="i"
                  :class="[
                    'h-2 rounded-full flex-1',
                    passwordStrength >= i ? 
                      (passwordStrength <= 1 ? 'bg-red-400' : 
                       passwordStrength <= 2 ? 'bg-yellow-400' : 
                       passwordStrength <= 3 ? 'bg-blue-400' : 'bg-green-400') : 
                      'bg-gray-200'
                  ]"
                ></div>
              </div>
              <p 
                :class="[
                  'text-xs',
                  passwordStrength <= 1 ? 'text-red-600' : 
                  passwordStrength <= 2 ? 'text-yellow-600' : 
                  passwordStrength <= 3 ? 'text-blue-600' : 'text-green-600'
                ]"
              >
                {{ passwordStrengthText }}
              </p>
            </div>

            <!-- 保存按鈕 -->
            <div class="flex justify-end pt-4">
              <button
                type="submit"
                :disabled="passwordLoading || passwordForm.new_password !== passwordForm.new_password_confirmation"
                class="inline-flex items-center px-6 py-2.5 bg-red-600 text-white font-medium rounded-lg hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
              >
                <svg v-if="passwordLoading" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                {{ passwordLoading ? '更新中...' : '更新密碼' }}
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- 系統設定卡片（管理員專用） -->
      <div v-if="currentUser.role === 'admin'" class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
          <h3 class="text-lg font-semibold text-gray-900">系統設定</h3>
          <p class="text-sm text-gray-600 mt-1">LINE Bot 和系統相關設定</p>
        </div>
        
        <div class="p-6">
          <RouterLink 
            :to="{ name: 'Settings' }"
            class="inline-flex items-center px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors"
          >
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
            前往系統設定
          </RouterLink>
        </div>
      </div>
    </div>

    <!-- 成功提示 -->
    <div
      v-if="successMessage"
      class="fixed top-4 right-4 z-50 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg transform transition-all duration-300"
    >
      <div class="flex items-center">
        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
        </svg>
        {{ successMessage }}
      </div>
    </div>

    <!-- 錯誤提示 -->
    <div
      v-if="errorMessage"
      class="fixed top-4 right-4 z-50 bg-red-500 text-white px-6 py-3 rounded-lg shadow-lg transform transition-all duration-300"
    >
      <div class="flex items-center">
        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
        </svg>
        {{ errorMessage }}
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { apiGet, apiPost, apiUpload } from '../utils/api.js'

// 響應式數據
const currentUser = ref({
  name: 'Loading...',
  email: '',
  role: 'user',
  imageUrl: 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?ixlib=rb-1.2.1&ixid=eyJhcHBfaWQiOjEyMDd9&auto=format&fit=facearea&facepad=2&w=256&h=256&q=80'
})

const profileForm = ref({
  name: '',
  email: '',
  avatar: null
})

const passwordForm = ref({
  current_password: '',
  new_password: '',
  new_password_confirmation: ''
})

const profileLoading = ref(false)
const passwordLoading = ref(false)
const successMessage = ref('')
const errorMessage = ref('')

// 計算屬性
const passwordStrength = computed(() => {
  const password = passwordForm.value.new_password
  if (!password) return 0
  
  let strength = 0
  if (password.length >= 8) strength++
  if (/[a-z]/.test(password)) strength++
  if (/[A-Z]/.test(password)) strength++
  if (/[0-9]/.test(password) || /[^A-Za-z0-9]/.test(password)) strength++
  
  return strength
})

const passwordStrengthText = computed(() => {
  const strength = passwordStrength.value
  if (strength <= 1) return '密碼強度：弱'
  if (strength <= 2) return '密碼強度：普通'
  if (strength <= 3) return '密碼強度：良好'
  return '密碼強度：強'
})

// 方法
const showMessage = (message, isError = false) => {
  if (isError) {
    errorMessage.value = message
    setTimeout(() => errorMessage.value = '', 5000)
  } else {
    successMessage.value = message
    setTimeout(() => successMessage.value = '', 3000)
  }
}

const loadUserProfile = async () => {
  try {
    const userData = await apiGet('/auth/user')
    currentUser.value = userData.user
    profileForm.value = {
      name: userData.user.name,
      email: userData.user.email,
      avatar: null
    }
  } catch (error) {
    showMessage('載入用戶資料失敗：' + error.message, true)
  }
}

const onAvatarChange = (event) => {
  const file = event.target.files[0]
  if (file) {
    // 檢查檔案大小 (2MB)
    if (file.size > 2 * 1024 * 1024) {
      showMessage('檔案大小不能超過 2MB', true)
      return
    }
    
    // 檢查檔案類型
    if (!file.type.startsWith('image/')) {
      showMessage('請選擇圖片檔案', true)
      return
    }
    
    // 創建預覽
    const reader = new FileReader()
    reader.onload = (e) => {
      profileForm.value.avatar = e.target.result
    }
    reader.readAsDataURL(file)
    profileForm.value.avatarFile = file
  }
}

const removeAvatar = () => {
  profileForm.value.avatar = null
  profileForm.value.avatarFile = null
}

const handleImageError = (event) => {
  event.target.src = 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?ixlib=rb-1.2.1&ixid=eyJhcHBfaWQiOjEyMDd9&auto=format&fit=facearea&facepad=2&w=256&h=256&q=80'
}

const updateProfile = async () => {
  profileLoading.value = true
  try {
    if (profileForm.value.avatarFile) {
      // 如果有上傳新頭像，使用 FormData
      const formData = new FormData()
      formData.append('name', profileForm.value.name)
      formData.append('email', profileForm.value.email)
      formData.append('avatar', profileForm.value.avatarFile)
      
      await apiUpload('/auth/profile', formData, 'POST')
    } else {
      // 只更新基本資料
      await apiPost('/auth/profile', {
        name: profileForm.value.name,
        email: profileForm.value.email
      })
    }
    
    showMessage('個人資料更新成功')
    await loadUserProfile() // 重新載入資料
  } catch (error) {
    showMessage('更新個人資料失敗：' + error.message, true)
  } finally {
    profileLoading.value = false
  }
}

const updatePassword = async () => {
  if (passwordForm.value.new_password !== passwordForm.value.new_password_confirmation) {
    showMessage('新密碼與確認密碼不相符', true)
    return
  }
  
  passwordLoading.value = true
  try {
    await apiPost('/auth/password', {
      current_password: passwordForm.value.current_password,
      new_password: passwordForm.value.new_password,
      new_password_confirmation: passwordForm.value.new_password_confirmation
    })
    
    // 清空表單
    passwordForm.value = {
      current_password: '',
      new_password: '',
      new_password_confirmation: ''
    }
    
    showMessage('密碼更新成功')
  } catch (error) {
    showMessage('更新密碼失敗：' + error.message, true)
  } finally {
    passwordLoading.value = false
  }
}

// 生命週期
onMounted(() => {
  loadUserProfile()
})
</script>
