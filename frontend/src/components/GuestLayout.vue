<script setup>
const props = defineProps({
  loading: Boolean,
  error: String,
  username: String,
  password: String
})

const emit = defineEmits(['submit', 'update:username', 'update:password'])

function handleSubmit(event) {
  event.preventDefault()
  emit('submit')
}
</script>

<template>
  <div class="min-h-screen bg-gradient-to-br from-blue-50 via-white to-indigo-50 flex flex-col justify-center py-12 sm:px-6 lg:px-8">
    <!-- 裝飾性背景元素 -->
    <div class="absolute inset-0 overflow-hidden">
      <div class="absolute top-0 left-0 w-40 h-40 bg-blue-100 rounded-full mix-blend-multiply filter blur-xl opacity-70 animate-blob"></div>
      <div class="absolute top-0 right-0 w-40 h-40 bg-purple-100 rounded-full mix-blend-multiply filter blur-xl opacity-70 animate-blob animation-delay-2000"></div>
      <div class="absolute bottom-0 left-20 w-40 h-40 bg-pink-100 rounded-full mix-blend-multiply filter blur-xl opacity-70 animate-blob animation-delay-4000"></div>
    </div>

    <div class="relative z-10">
      <!-- Logo 和標題區域 -->
      <div class="sm:mx-auto sm:w-full sm:max-w-md text-center">
        <!-- Logo -->
        <div class="mx-auto h-16 w-16 bg-gradient-to-r from-green-500 to-blue-600 rounded-xl flex items-center justify-center shadow-lg">
          <svg class="h-10 w-10 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <!-- 預約管理系統 Logo - 日曆 + 時鐘 -->
            <g>
              <!-- 日曆框架 -->
              <rect x="3" y="4" width="18" height="16" rx="2" stroke-width="1.5"/>
              <path d="M3 10h18" stroke-width="1.5"/>
              <path d="M8 2v4" stroke-width="1.5" stroke-linecap="round"/>
              <path d="M16 2v4" stroke-width="1.5" stroke-linecap="round"/>
              <!-- 時鐘指針 -->
              <circle cx="12" cy="15" r="3" stroke-width="1.2"/>
              <path d="M12 13v2l1 1" stroke-width="1.2" stroke-linecap="round"/>
            </g>
          </svg>
        </div>
        
        <!-- 標題 -->
        <h1 class="mt-6 text-3xl font-bold text-gray-900">LINE 預約管理系統</h1>
        <p class="mt-2 text-sm text-gray-600">歡迎回來，請登入您的帳號</p>
      </div>

      <!-- 登入表單卡片 -->
      <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
        <div class="bg-white py-8 px-6 shadow-xl rounded-xl border border-gray-100">
          <form class="space-y-6" @submit="handleSubmit">
            <!-- 錯誤訊息 -->
            <div v-if="error" class="rounded-lg bg-red-50 border border-red-200 p-4">
              <div class="flex items-center">
                <div class="flex-shrink-0">
                  <svg class="h-5 w-5 text-red-600" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                  </svg>
                </div>
                <div class="ml-3">
                  <p class="text-sm font-medium text-red-800">{{ error }}</p>
                </div>
              </div>
            </div>

            <!-- 帳號輸入 -->
            <div>
              <label for="username" class="block text-sm font-medium text-gray-700 mb-2">
                電子信箱
              </label>
              <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                  </svg>
                </div>
                <input 
                  type="email" 
                  name="username" 
                  id="username" 
                  autocomplete="email" 
                  required 
                  :disabled="loading"
                  :value="username"
                  @input="emit('update:username', $event.target.value)"
                  class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors disabled:bg-gray-100 disabled:cursor-not-allowed"
                  placeholder="請輸入您的電子信箱"
                />
              </div>
            </div>

            <!-- 密碼輸入 -->
            <div>
              <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                密碼
              </label>
              <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                  </svg>
                </div>
                <input 
                  type="password" 
                  name="password" 
                  id="password" 
                  required 
                  :disabled="loading"
                  :value="password"
                  @input="emit('update:password', $event.target.value)"
                  class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors disabled:bg-gray-100 disabled:cursor-not-allowed"
                  placeholder="請輸入您的密碼"
                />
              </div>
            </div>

            <!-- 登入按鈕 -->
            <div>
              <button 
                type="submit" 
                :disabled="loading || !username.trim() || !password.trim()"
                class="w-full flex justify-center items-center py-2.5 px-4 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-200"
              >
                <svg v-if="loading" class="animate-spin -ml-1 mr-3 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                {{ loading ? '登入中...' : '登入系統' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* 裝飾性動畫 */
@keyframes blob {
  0% {
    transform: translate(0px, 0px) scale(1);
  }
  33% {
    transform: translate(30px, -50px) scale(1.1);
  }
  66% {
    transform: translate(-20px, 20px) scale(0.9);
  }
  100% {
    transform: translate(0px, 0px) scale(1);
  }
}

.animate-blob {
  animation: blob 7s infinite;
}

.animation-delay-2000 {
  animation-delay: 2s;
}

.animation-delay-4000 {
  animation-delay: 4s;
}

/* 確保中文字型正確顯示 */
* {
  font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", "Noto Color Emoji";
}
</style>
