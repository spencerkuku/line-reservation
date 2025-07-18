<template>
  <div class="min-h-screen bg-gray-50 p-6">
    <!-- 頁面標題 -->
    <div class="mb-8">
      <h1 class="text-3xl font-bold text-gray-900">系統設定</h1>
      <p class="text-gray-600 mt-2">管理 LINE Bot 連接設定與系統配置</p>
    </div>

    <!-- 設定容器 -->
    <div class="max-w-4xl mx-auto">
      <!-- LINE API 設定卡片 -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-8">
        <!-- 卡片標題 -->
        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
          <div class="flex items-center">
            <div class="flex-shrink-0">
              <svg class="h-8 w-8 text-green-600" fill="currentColor" viewBox="0 0 24 24">
                <path d="M19.365 9.863c.349 0 .63.285.63.631 0 .345-.281.63-.63.63H17.61v1.125h1.755c.349 0 .63.283.63.63 0 .344-.281.629-.63.629h-2.386c-.345 0-.627-.285-.627-.629V8.108c0-.345.282-.63.63-.63h2.386c.346 0 .627.285.627.63 0 .349-.281.63-.63.63H17.61v1.125h1.755zm-3.855 3.016c0 .27-.174.51-.432.596-.064.021-.133.031-.199.031-.211 0-.391-.09-.51-.25l-2.443-3.317v2.94c0 .344-.279.629-.631.629-.346 0-.626-.285-.626-.629V8.108c0-.27.173-.51.43-.595.06-.023.136-.033.194-.033.195 0 .375.104.495.254l2.462 3.33V8.108c0-.345.282-.63.63-.63.345 0 .63.285.63.63v4.771zm-5.741 0c0 .344-.282.629-.631.629-.345 0-.627-.285-.627-.629V8.108c0-.345.282-.63.63-.63.346 0 .628.285.628.63v4.771zm-2.466.629H4.917c-.345 0-.63-.285-.63-.629V8.108c0-.345.285-.63.63-.63.348 0 .63.285.63.63v4.141h1.756c.348 0 .629.283.629.63 0 .344-.282.629-.629.629M24 10.314C24 4.943 18.615.572 12 .572S0 4.943 0 10.314c0 4.811 4.27 8.842 10.035 9.608.391.082.923.258 1.058.59.12.301.079.766.038 1.08l-.164 1.02c-.045.301-.24 1.186 1.049.645 1.291-.539 6.916-4.078 9.436-6.975C23.176 14.393 24 12.458 24 10.314"/>
              </svg>
            </div>
            <div class="ml-4">
              <h3 class="text-lg font-semibold text-gray-900">LINE Message API 設定</h3>
              <p class="text-sm text-gray-600 mt-1">配置 LINE Bot 連接資訊</p>
            </div>
          </div>
        </div>

        <!-- 設定表單 -->
        <div class="p-6">

          <form @submit.prevent="submitToken" class="space-y-6">
            <!-- Channel Access Token -->
            <div>
              <label for="LineChannelAccessToken" class="text-sm font-medium text-gray-700 mb-2 block">
                Channel Access Token *
              </label>
              <div class="relative">
                <input
                  id="LineChannelAccessToken"
                  v-model="LineChannelAccessToken"
                  type="password"
                  :disabled="loading"
                  placeholder="請輸入您的 Channel Access Token"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors disabled:bg-gray-100 disabled:cursor-not-allowed"
                />
                <div class="absolute inset-y-0 right-0 pr-3 flex items-center">
                  <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                  </svg>
                </div>
              </div>
              <p class="mt-1 text-xs text-gray-500">
                從 LINE Developers Console 的 Messaging API 設定中取得
              </p>
            </div>

            <!-- Channel Secret -->
            <div>
              <label for="LineChannelSecret" class="text-sm font-medium text-gray-700 mb-2 block">
                Channel Secret *
              </label>
              <div class="relative">
                <input
                  id="LineChannelSecret"
                  v-model="LineChannelSecret"
                  type="password"
                  :disabled="loading"
                  placeholder="請輸入您的 Channel Secret"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors disabled:bg-gray-100 disabled:cursor-not-allowed"
                />
                <div class="absolute inset-y-0 right-0 pr-3 flex items-center">
                  <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                  </svg>
                </div>
              </div>
              <p class="mt-1 text-xs text-gray-500">
                用於驗證 webhook 請求的數位簽章
              </p>
            </div>

            <!-- 安全警告 -->
            <div class="bg-red-50 border border-red-200 rounded-lg p-4">
              <div class="flex items-start">
                <div class="flex-shrink-0">
                  <svg class="h-5 w-5 text-red-600" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                  </svg>
                </div>
                <div class="ml-3">
                  <h4 class="text-sm font-semibold text-red-800">重要提醒</h4>
                  <p class="mt-1 text-sm text-red-700">
                    請妥善保管您的 API 憑證，切勿分享給他人或在不安全的環境中使用。
                  </p>
                </div>
              </div>
            </div>

            <!-- 提交按鈕 -->
            <div class="flex justify-end pt-4">
              <button
                type="submit"
                :disabled="loading || (!LineChannelAccessToken.trim() || !LineChannelSecret.trim())"
                class="inline-flex items-center px-6 py-2 bg-gradient-to-r from-blue-600 to-blue-700 text-white text-sm font-medium rounded-lg hover:from-blue-700 hover:to-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-200"
              >
                <svg v-if="loading" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                {{ loading ? '儲存中...' : '儲存設定' }}
              </button>
            </div>

            <!-- 成功/錯誤訊息 -->
            <div v-if="successMessage" class="mt-4">
              <div class="rounded-lg p-4" :class="successMessage.includes('失敗') ? 'bg-red-50 border border-red-200' : 'bg-green-50 border border-green-200'">
                <div class="flex items-center">
                  <div class="flex-shrink-0">
                    <svg v-if="successMessage.includes('失敗')" class="h-5 w-5 text-red-600" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                    </svg>
                    <svg v-else class="h-5 w-5 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                    </svg>
                  </div>
                  <div class="ml-3">
                    <p class="text-sm font-medium" :class="successMessage.includes('失敗') ? 'text-red-800' : 'text-green-800'">
                      {{ successMessage }}
                    </p>
                  </div>
                </div>
              </div>
            </div>
          </form>
        </div>
      </div>

      <!-- LINE Bot 設定指南 -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
          <h3 class="text-lg font-semibold text-gray-900">設定指南</h3>
          <p class="text-sm text-gray-600 mt-1">如何取得 LINE Bot API 憑證</p>
        </div>
        
        <div class="p-6">
          <div class="space-y-4">
            <div class="flex items-start">
              <div class="flex-shrink-0 w-6 h-6 bg-blue-100 rounded-full flex items-center justify-center mr-4 mt-0.5">
                <span class="text-xs font-semibold text-blue-600">1</span>
              </div>
              <div>
                <h4 class="text-sm font-medium text-gray-900">前往 LINE Developers</h4>
                <p class="text-sm text-gray-600 mt-1">
                  訪問 <a href="https://developers.line.biz/" target="_blank" class="text-blue-600 hover:text-blue-700 underline">LINE Developers Console</a> 並登入您的帳號
                </p>
              </div>
            </div>
            
            <div class="flex items-start">
              <div class="flex-shrink-0 w-6 h-6 bg-blue-100 rounded-full flex items-center justify-center mr-4 mt-0.5">
                <span class="text-xs font-semibold text-blue-600">2</span>
              </div>
              <div>
                <h4 class="text-sm font-medium text-gray-900">建立 Provider 和 Channel</h4>
                <p class="text-sm text-gray-600 mt-1">
                  建立新的 Provider 或選擇現有 Provider，然後建立 Messaging API Channel
                </p>
              </div>
            </div>
            
            <div class="flex items-start">
              <div class="flex-shrink-0 w-6 h-6 bg-blue-100 rounded-full flex items-center justify-center mr-4 mt-0.5">
                <span class="text-xs font-semibold text-blue-600">3</span>
              </div>
              <div>
                <h4 class="text-sm font-medium text-gray-900">取得 API 憑證</h4>
                <p class="text-sm text-gray-600 mt-1">
                  在 Channel 設定頁面中，找到「Channel access token」和「Channel secret」並複製到上方表單
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { apiGet, apiPost } from '../utils/api.js'

const LineChannelAccessToken = ref('')
const LineChannelSecret = ref('')
const successMessage = ref('')
const loading = ref(false)



// 獲取當前設定
async function fetchSettings() {
  loading.value = true
  try {
    const data = await apiGet('/settings/line')
    LineChannelAccessToken.value = data.channel_access_token || ''
    LineChannelSecret.value = data.channel_secret || ''
  } catch (err) {
    console.error('Error fetching settings:', err)
  } finally {
    loading.value = false
  }
}

// 儲存設定
async function submitToken() {
  if (!LineChannelAccessToken.value.trim() || !LineChannelSecret.value.trim()) {
    successMessage.value = '請輸入有效的 LINE Token'
    setTimeout(() => { successMessage.value = '' }, 3000)
    return
  }
  
  loading.value = true
  try {
    await apiPost('/settings/line', {
      channel_access_token: LineChannelAccessToken.value.trim(),
      channel_secret: LineChannelSecret.value.trim()
    })
    
    successMessage.value = '設定已儲存'
  } catch (err) {
    successMessage.value = `儲存失敗: ${err.message}`
  } finally {
    loading.value = false
    setTimeout(() => { successMessage.value = '' }, 3000)
  }
}

// 頁面載入時獲取設定
onMounted(() => {
  fetchSettings()
})
</script>


<style scoped>
/* Tailwind 已涵蓋商業級樣式，無需額外樣式 */
</style>
