<template>
  <div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-gray-100 to-gray-200 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full">
      <div class="bg-white rounded-2xl shadow-xl p-8">
        <!-- 標題 -->
        <div class="text-center mb-8">
          <div class="inline-flex items-center justify-center w-16 h-16 bg-yellow-100 rounded-full mb-4">
            <svg class="w-8 h-8 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
            </svg>
          </div>
          <h2 class="text-2xl font-bold text-gray-900">設定新密碼</h2>
          <p class="text-gray-600 mt-2">首次登入請設定您的專屬密碼</p>
        </div>

        <!-- 表單 -->
        <form @submit.prevent="handleSubmit" class="space-y-6">
          <!-- 新密碼 -->
          <div>
            <label for="new_password" class="block text-sm font-medium text-gray-700 mb-1">
              新密碼
            </label>
            <div class="relative">
              <input
                id="new_password"
                v-model="form.new_password"
                :type="showPassword ? 'text' : 'password'"
                required
                minlength="8"
                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 pr-10"
                placeholder="請輸入新密碼（至少 8 個字元）"
              />
              <button
                type="button"
                @click="showPassword = !showPassword"
                class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600"
              >
                <svg v-if="showPassword" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                </svg>
                <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                </svg>
              </button>
            </div>
          </div>

          <!-- 確認密碼 -->
          <div>
            <label for="new_password_confirmation" class="block text-sm font-medium text-gray-700 mb-1">
              確認新密碼
            </label>
            <div class="relative">
              <input
                id="new_password_confirmation"
                v-model="form.new_password_confirmation"
                :type="showConfirmPassword ? 'text' : 'password'"
                required
                minlength="8"
                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 pr-10"
                placeholder="請再次輸入新密碼"
              />
              <button
                type="button"
                @click="showConfirmPassword = !showConfirmPassword"
                class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600"
              >
                <svg v-if="showConfirmPassword" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                </svg>
                <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                </svg>
              </button>
            </div>
          </div>

          <!-- 密碼不一致提示 -->
          <div v-if="form.new_password && form.new_password_confirmation && form.new_password !== form.new_password_confirmation" class="text-red-600 text-sm">
            兩次輸入的密碼不一致
          </div>

          <!-- 密碼強度提示 -->
          <div class="bg-gray-50 p-4 rounded-lg">
            <p class="text-sm font-medium text-gray-700 mb-2">密碼要求：</p>
            <ul class="text-sm text-gray-600 space-y-1">
              <li :class="form.new_password.length >= 8 ? 'text-green-600' : 'text-gray-400'">
                <span class="mr-2">{{ form.new_password.length >= 8 ? '✓' : '○' }}</span>
                至少 8 個字元
              </li>
              <li :class="hasNumber ? 'text-green-600' : 'text-gray-400'">
                <span class="mr-2">{{ hasNumber ? '✓' : '○' }}</span>
                包含數字
              </li>
              <li :class="hasLetter ? 'text-green-600' : 'text-gray-400'">
                <span class="mr-2">{{ hasLetter ? '✓' : '○' }}</span>
                包含英文字母
              </li>
            </ul>
          </div>

          <!-- 錯誤訊息 -->
          <div v-if="error" class="bg-red-50 border border-red-200 rounded-lg p-4">
            <p class="text-sm text-red-600">{{ error }}</p>
          </div>

          <!-- 提交按鈕 -->
          <button
            type="submit"
            :disabled="loading || !isFormValid"
            class="w-full py-3 px-4 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
          >
            <span v-if="loading" class="inline-flex items-center">
              <svg class="animate-spin -ml-1 mr-2 h-5 w-5 text-white" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
              處理中...
            </span>
            <span v-else>設定密碼並繼續</span>
          </button>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { useRouter } from 'vue-router'
import { apiPost } from '../utils/api.js'

const router = useRouter()

const loading = ref(false)
const error = ref('')
const showPassword = ref(false)
const showConfirmPassword = ref(false)

const form = reactive({
  new_password: '',
  new_password_confirmation: ''
})

// 檢查密碼是否包含數字
const hasNumber = computed(() => /\d/.test(form.new_password))

// 檢查密碼是否包含英文字母
const hasLetter = computed(() => /[a-zA-Z]/.test(form.new_password))

// 檢查表單是否有效
const isFormValid = computed(() => {
  return form.new_password.length >= 8 &&
         form.new_password === form.new_password_confirmation &&
         hasNumber.value &&
         hasLetter.value
})

const handleSubmit = async () => {
  if (!isFormValid.value) return
  
  loading.value = true
  error.value = ''
  
  try {
    await apiPost('/auth/force-change-password', form)
    
    // 更新本地用戶資料
    const userStr = localStorage.getItem('user')
    if (userStr) {
      const user = JSON.parse(userStr)
      user.must_change_password = false
      localStorage.setItem('user', JSON.stringify(user))
    }
    
    // 導向首頁
    router.push({ name: 'Dashboard' })
  } catch (err) {
    error.value = err.response?.data?.message || '設定密碼失敗，請稍後再試'
  } finally {
    loading.value = false
  }
}
</script>
