<template>
  <div class="min-h-screen bg-gray-50 pt-4 px-4 sm:px-6 lg:px-8 pb-6">
    <!-- 設定容器 -->
    <div class="max-w-7xl mx-auto mt-2">
      <!-- 主要設定卡片容器 - 並排顯示 -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
        <!-- Webhook URL 卡片（全寬） -->
        <div v-if="webhookUrl" class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
          <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-green-50 to-blue-50">
            <div class="flex items-center">
              <div class="flex-shrink-0">
                <svg class="h-8 w-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                </svg>
              </div>
              <div class="ml-4">
                <h3 class="text-lg font-semibold text-gray-900">您的 LINE Bot Webhook URL</h3>
                <p class="text-sm text-gray-600 mt-1">請將此 URL 設定到 LINE Developers Console</p>
              </div>
            </div>
          </div>

          <div class="p-6">
            <div class="flex items-center space-x-4">
              <div class="flex-1 bg-gray-50 border border-gray-200 rounded-lg p-4">
                <code class="text-sm font-mono text-gray-800 break-all">{{ webhookUrl }}</code>
              </div>
              <button
                @click="copyWebhookUrl"
                class="flex-shrink-0 inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors"
              >
                <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                </svg>
                複製
              </button>
            </div>

            <div class="mt-4 bg-blue-50 border border-blue-200 rounded-lg p-4">
              <div class="flex items-start">
                <div class="flex-shrink-0">
                  <svg class="h-5 w-5 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                  </svg>
                </div>
                <div class="ml-3">
                  <h4 class="text-sm font-semibold text-blue-800">設定說明</h4>
                  <p class="mt-1 text-sm text-blue-700">
                    請在 LINE Developers Console 的 Messaging API 設定中，將此 URL 貼到「Webhook URL」欄位，並啟用「Use webhook」選項。
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- LINE API 設定卡片 -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
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
                    @focus="handleSecretFocus"
                    placeholder="請輸入您的 Channel Secret"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors disabled:bg-gray-100 disabled:cursor-not-allowed pr-12"
                  />
                  <div class="absolute inset-y-0 right-0 pr-3 flex items-center">
                    <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                  </div>
                </div>
                <div v-if="currentSecret" class="mt-2 p-2 bg-gray-50 rounded border text-sm font-mono text-gray-600 overflow-hidden">
                  <div class="truncate">目前設定: {{ currentSecret }}</div>
                </div>
                <div v-if="hasExistingSecret && LineChannelSecret.includes('*')" class="mt-1 text-xs text-green-600">
                  ✓ 已儲存資料，點擊輸入框可修改
                </div>
                <p class="mt-1 text-xs text-gray-500">
                  用於驗證 webhook 請求的數位簽章
                </p>
              </div>

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
                    @focus="handleAccessTokenFocus"
                    placeholder="請輸入您的 Channel Access Token"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors disabled:bg-gray-100 disabled:cursor-not-allowed pr-12"
                  />
                  <div class="absolute inset-y-0 right-0 pr-3 flex items-center">
                    <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                  </div>
                </div>
                <div v-if="currentAccessToken" class="mt-2 p-2 bg-gray-50 rounded border text-sm font-mono text-gray-600 overflow-hidden">
                  <div class="truncate">目前設定: {{ currentAccessToken }}</div>
                </div>
                <div v-if="hasExistingAccessToken && LineChannelAccessToken.includes('*')" class="mt-1 text-xs text-green-600">
                  ✓ 已儲存資料，點擊輸入框可修改
                </div>
                <p class="mt-1 text-xs text-gray-500">
                  從 LINE Developers Console 的 Messaging API 設定中取得
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
                  :disabled="loading || ((!LineChannelAccessToken.trim() || LineChannelAccessToken.includes('*')) && (!LineChannelSecret.trim() || LineChannelSecret.includes('*')))"
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

        <!-- 預約設定卡片 -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
          <!-- 卡片標題 -->
          <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
            <div class="flex items-center">
              <div class="flex-shrink-0">
                <svg class="h-8 w-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
              </div>
              <div class="ml-4">
                <h3 class="text-lg font-semibold text-gray-900">預約管理設定</h3>
                <p class="text-sm text-gray-600 mt-1">配置預約確認模式與相關設定</p>
              </div>
            </div>
          </div>

          <!-- 預約設定表單 -->
          <div class="p-6">
            <form @submit.prevent="saveReservationSettings" class="space-y-6">
              <!-- 預約確認模式 -->
              <div>
                <label class="text-sm font-medium text-gray-700 mb-3 block">
                  預約確認模式
                </label>
                <div class="space-y-3">
                  <div class="flex items-center">
                    <input
                      id="auto_confirm"
                      v-model="reservationConfirmMode"
                      type="radio"
                      value="auto"
                      :disabled="loading"
                      class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300"
                    />
                    <label for="auto_confirm" class="ml-3 block text-sm font-medium text-gray-700">
                      自動確認
                    </label>
                  </div>
                  <div class="ml-7 text-sm text-gray-600">
                    LINE Bot 收到預約後立即自動確認，無需人工干預
                  </div>
                  
                  <div class="flex items-center">
                    <input
                      id="manual_confirm"
                      v-model="reservationConfirmMode"
                      type="radio"
                      value="manual"
                      :disabled="loading"
                      class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300"
                    />
                    <label for="manual_confirm" class="ml-3 block text-sm font-medium text-gray-700">
                      手動確認
                    </label>
                  </div>
                  <div class="ml-7 text-sm text-gray-600">
                    預約提交後保持「待確認」狀態，需要管理員手動確認
                  </div>
                </div>
              </div>

              <!-- 設定說明 -->
              <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <div class="flex items-start">
                  <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                    </svg>
                  </div>
                  <div class="ml-3">
                    <h4 class="text-sm font-semibold text-blue-800">設定說明</h4>
                    <div class="mt-1 text-sm text-blue-700">
                      <p><strong>自動確認：</strong>適合無需審核的簡單預約服務，提升用戶體驗</p>
                      <p class="mt-1"><strong>手動確認：</strong>適合需要人工審核或有特殊要求的預約服務</p>
                    </div>
                  </div>
                </div>
              </div>

              <!-- 保存按鈕 -->
              <div class="flex justify-end">
                <button
                  type="submit"
                  :disabled="loading"
                  class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-lg shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                >
                  <svg v-if="loading" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                  </svg>
                  {{ loading ? '儲存中...' : '儲存設定' }}
                </button>
              </div>
            </form>

            <!-- 成功訊息 -->
            <div v-if="reservationSuccessMessage" class="mt-4 p-3 bg-green-50 border border-green-200 rounded-lg">
              <div class="flex items-center">
                <svg class="h-5 w-5 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="ml-2 text-sm font-medium text-green-800">{{ reservationSuccessMessage }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- 報到提醒設定卡片 - 暫時停用此功能 -->
      <!--
      <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-8">
        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
          <div class="flex items-center">
            <div class="flex-shrink-0">
              <svg class="h-8 w-8 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
            <div class="ml-4">
              <h3 class="text-lg font-semibold text-gray-900">報到提醒設定</h3>
              <p class="text-sm text-gray-600 mt-1">配置預約前的自動提醒通知</p>
            </div>
          </div>
        </div>

        <div class="p-6">
          <form @submit.prevent="saveCheckInSettings" class="space-y-6">
            <div>
              <div class="flex items-center justify-between">
                <div class="flex-1">
                  <label class="text-sm font-medium text-gray-700 block mb-1">
                    啟用報到提醒
                  </label>
                  <p class="text-sm text-gray-600">
                    預約前30分鐘自動發送 LINE 提醒訊息，包含報到碼、預約資訊及服務地點
                  </p>
                </div>
                <button
                  type="button"
                  @click="checkInReminderEnabled = !checkInReminderEnabled"
                  :class="[
                    checkInReminderEnabled ? 'bg-blue-600' : 'bg-gray-200',
                    'relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2'
                  ]"
                  :disabled="loading"
                >
                  <span class="sr-only">啟用報到提醒</span>
                  <span
                    :class="[
                      checkInReminderEnabled ? 'translate-x-5' : 'translate-x-0',
                      'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out'
                    ]"
                  />
                </button>
              </div>
            </div>

            <div>
              <label for="businessAddress" class="text-sm font-medium text-gray-700 mb-2 block">
                商家地址
              </label>
              <input
                id="businessAddress"
                v-model="businessAddress"
                type="text"
                :disabled="loading"
                placeholder="請輸入商家地址（顯示在提醒訊息中）"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors disabled:bg-gray-100 disabled:cursor-not-allowed"
              />
              <p class="mt-1 text-xs text-gray-500">
                此地址將顯示在報到提醒訊息中，幫助客戶找到服務地點
              </p>
            </div>

            <div class="bg-orange-50 border border-orange-200 rounded-lg p-4">
              <div class="flex items-start">
                <div class="flex-shrink-0">
                  <svg class="h-5 w-5 text-orange-600" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                  </svg>
                </div>
                <div class="ml-3">
                  <h4 class="text-sm font-semibold text-orange-800">功能說明</h4>
                  <div class="mt-1 text-sm text-orange-700">
                    <p>• 系統會在預約時間前30分鐘自動發送 LINE 提醒訊息</p>
                    <p class="mt-1">• 訊息包含報到碼、服務項目、預約時間及服務地點</p>
                    <p class="mt-1">• 需要設定 Linux Cron Job 來執行排程任務</p>
                    <p class="mt-1 font-semibold">• 此功能預設為停用，請確認需求後再啟用</p>
                  </div>
                </div>
              </div>
            </div>

            <div v-if="checkInReminderEnabled" class="bg-gray-50 border border-gray-300 rounded-lg p-4">
              <h4 class="text-sm font-semibold text-gray-900 mb-2">Cron 設定指令</h4>
              <p class="text-xs text-gray-600 mb-2">請在伺服器執行以下指令設定排程：</p>
              <div class="bg-gray-900 text-green-400 p-3 rounded font-mono text-xs overflow-x-auto">
                <div>crontab -e</div>
                <div class="mt-2"># 添加以下行（每分鐘執行一次檢查）</div>
                <div>* * * * * cd /home/server/projects/line-reservation/backend && php artisan schedule:run >> /dev/null 2>&1</div>
              </div>
            </div>

            <div class="flex justify-end">
              <button
                type="submit"
                :disabled="loading"
                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-lg shadow-sm text-white bg-orange-600 hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
              >
                <svg v-if="loading" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                {{ loading ? '儲存中...' : '儲存設定' }}
              </button>
            </div>
          </form>

          <div v-if="checkInSuccessMessage" class="mt-4 p-3 bg-green-50 border border-green-200 rounded-lg">
            <div class="flex items-center">
              <svg class="h-5 w-5 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
              </svg>
              <span class="ml-2 text-sm font-medium text-green-800">{{ checkInSuccessMessage }}</span>
            </div>
          </div>
        </div>
      </div>
      -->

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
                <h4 class="text-sm font-medium text-gray-900">登入 LINE Developers Console</h4>
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
                <h4 class="text-sm font-medium text-gray-900">取得 Channel Secret</h4>
                <p class="text-sm text-gray-600 mt-1">
                  選擇您的 Channel → 點選「Basic settings」→ 複製「Channel secret」→ 貼到上方設定中的 Channel Secret 欄位
                </p>
              </div>
            </div>
            
            <div class="flex items-start">
              <div class="flex-shrink-0 w-6 h-6 bg-blue-100 rounded-full flex items-center justify-center mr-4 mt-0.5">
                <span class="text-xs font-semibold text-blue-600">3</span>
              </div>
              <div>
                <h4 class="text-sm font-medium text-gray-900">設定 Webhook URL</h4>
                <p class="text-sm text-gray-600 mt-1">
                  點選「Messaging API」→ 在 Webhook URL 欄位貼上您的專屬 Webhook URL（上方顯示）<br>
                  <span class="text-xs text-gray-500">每個租戶都有專屬的 Webhook URL，請確認使用正確的 URL</span>
                </p>
              </div>
            </div>

            <div class="flex items-start">
              <div class="flex-shrink-0 w-6 h-6 bg-blue-100 rounded-full flex items-center justify-center mr-4 mt-0.5">
                <span class="text-xs font-semibold text-blue-600">4</span>
              </div>
              <div>
                <h4 class="text-sm font-medium text-gray-900">開啟 Use webhook</h4>
                <p class="text-sm text-gray-600 mt-1">
                  在「Webhook URL」下方，開啟「Use webhook」（綠色顯示為正確）<br>
                </p>
              </div>
            </div>

            <div class="flex items-start">
              <div class="flex-shrink-0 w-6 h-6 bg-blue-100 rounded-full flex items-center justify-center mr-4 mt-0.5">
                <span class="text-xs font-semibold text-blue-600">5</span>
              </div>
              <div>
                <h4 class="text-sm font-medium text-gray-900">取得 Channel Access Token</h4>
                <p class="text-sm text-gray-600 mt-1">
                  在同一個「Messaging API」頁面，滑到最下面找到「Channel access token」並複製 → 貼到上方設定中的 Channel Access Token 欄位
                </p>
              </div>
            </div>
            
            <div class="flex items-start">
              <div class="flex-shrink-0 w-6 h-6 bg-blue-100 rounded-full flex items-center justify-center mr-4 mt-0.5">
                <span class="text-xs font-semibold text-blue-600">6</span>
              </div>
              <div>
                <h4 class="text-sm font-medium text-gray-900">完成設定</h4>
                <p class="text-sm text-gray-600 mt-1">
                  將取得的 Channel Secret 和 Channel Access Token 貼到上方表單中並儲存
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

// Webhook URL
const webhookUrl = ref('')

// 新增狀態來追蹤是否有現有資料
const hasExistingAccessToken = ref(false)
const hasExistingSecret = ref(false)

// 預約設定
const reservationConfirmMode = ref('auto') // 預設為自動確認
const reservationSuccessMessage = ref('')

// 報到提醒設定 - 暫時停用此功能
/*
const checkInReminderEnabled = ref(false)
const businessAddress = ref('')
const checkInSuccessMessage = ref('')
*/

// 顯示後端返回的遮蔽版本
const currentAccessToken = ref('')
const currentSecret = ref('')

// 獲取當前設定
async function fetchSettings() {
  loading.value = true
  try {
    const response = await apiGet('/settings/line')
    console.log('Fetched settings response:', response) // 調試用
    
    // 檢查回應格式並正確取得數據
    const data = response.data || response
    console.log('Extracted data:', data) // 調試用
    
    // 後端已經返回遮蔽版本，直接顯示
    currentAccessToken.value = data.channel_access_token || ''
    currentSecret.value = data.channel_secret || ''
    
    // 設定是否有現有資料的狀態
    hasExistingAccessToken.value = !!(data.channel_access_token && data.channel_access_token.trim() !== '')
    hasExistingSecret.value = !!(data.channel_secret && data.channel_secret.trim() !== '')
    
    console.log('Has existing token:', hasExistingAccessToken.value) // 調試用
    console.log('Has existing secret:', hasExistingSecret.value) // 調試用
    
    // 如果有現有設定，在輸入框中顯示星號表示已有資料
    if (hasExistingAccessToken.value) {
      LineChannelAccessToken.value = '****************************************'
    } else {
      LineChannelAccessToken.value = ''
    }
    
    if (hasExistingSecret.value) {
      LineChannelSecret.value = '********************************'
    } else {
      LineChannelSecret.value = ''
    }

    // 獲取 Webhook URL
    await fetchWebhookUrl()

    // 獲取預約設定
    await fetchReservationSettings()
    
    // 獲取報到提醒設定 - 暫時停用此功能
    // await fetchCheckInSettings()
  } catch (err) {
    console.error('Error fetching settings:', err) // 顯示所有錯誤
  } finally {
    loading.value = false
  }
}

// 獲取 Webhook URL
async function fetchWebhookUrl() {
  try {
    const response = await apiGet('/settings/webhook-url')
    if (response.success && response.data && response.data.webhook_url) {
      webhookUrl.value = response.data.webhook_url
    }
  } catch (err) {
    console.error('Error fetching webhook URL:', err)
  }
}

// 複製 Webhook URL 到剪貼簿
async function copyWebhookUrl() {
  try {
    await navigator.clipboard.writeText(webhookUrl.value)
    successMessage.value = 'Webhook URL 已複製到剪貼簿'
    setTimeout(() => { successMessage.value = '' }, 3000)
  } catch (err) {
    console.error('Failed to copy:', err)
  }
}

// 處理輸入框焦點事件 - 清除星號讓使用者輸入新值
function handleAccessTokenFocus() {
  if (hasExistingAccessToken.value && LineChannelAccessToken.value.includes('*')) {
    LineChannelAccessToken.value = ''
  }
}

function handleSecretFocus() {
  if (hasExistingSecret.value && LineChannelSecret.value.includes('*')) {
    LineChannelSecret.value = ''
  }
}

// 儲存設定
async function submitToken() {
  // 如果使用者沒有修改星號內容，就不要送出請求
  if (LineChannelAccessToken.value.includes('*') && LineChannelSecret.value.includes('*')) {
    successMessage.value = '設定未變更'
    setTimeout(() => { successMessage.value = '' }, 3000)
    return
  }
  
  // 檢查是否有實際的新值
  const newAccessToken = LineChannelAccessToken.value.includes('*') ? '' : LineChannelAccessToken.value.trim()
  const newSecret = LineChannelSecret.value.includes('*') ? '' : LineChannelSecret.value.trim()
  
  if (!newAccessToken && !newSecret && !hasExistingAccessToken.value && !hasExistingSecret.value) {
    successMessage.value = '請輸入有效的 LINE Token'
    setTimeout(() => { successMessage.value = '' }, 3000)
    return
  }
  
  loading.value = true
  try {
    const payload = {}
    if (newAccessToken) payload.channel_access_token = newAccessToken
    if (newSecret) payload.channel_secret = newSecret
    
    await apiPost('/settings/line', payload)
    
    successMessage.value = '設定已儲存'
    
    // 儲存成功後重新獲取設定以更新顯示
    setTimeout(async () => {
      await fetchSettings()
    }, 1000)
    
  } catch (err) {
    successMessage.value = `儲存失敗: ${err.message}`
  } finally {
    loading.value = false
    setTimeout(() => { successMessage.value = '' }, 3000)
  }
}

// 獲取預約設定
async function fetchReservationSettings() {
  try {
    const response = await apiGet('/settings')
    if (response.success && response.data && response.data.reservation_confirm_mode) {
      reservationConfirmMode.value = response.data.reservation_confirm_mode
    }
  } catch (err) {
    if (import.meta.env.DEV) {
      console.error('Error fetching reservation settings:', err)
    }
  }
}

// 儲存預約設定
async function saveReservationSettings() {
  loading.value = true
  try {
    await apiPost('/settings', {
      key: 'reservation_confirm_mode',
      value: reservationConfirmMode.value,
      type: 'string'
    })
    
    reservationSuccessMessage.value = '預約設定已儲存'
    
    setTimeout(() => {
      reservationSuccessMessage.value = ''
    }, 3000)
    
  } catch (err) {
    reservationSuccessMessage.value = `儲存失敗: ${err.message}`
    setTimeout(() => {
      reservationSuccessMessage.value = ''
    }, 3000)
  } finally {
    loading.value = false
  }
}

// 獲取報到提醒設定 - 暫時停用此功能
/*
async function fetchCheckInSettings() {
  try {
    const response = await apiGet('/settings')
    if (response.success && response.data) {
      // 獲取報到提醒開關狀態
      if (response.data.check_in_reminder_enabled !== undefined) {
        checkInReminderEnabled.value = response.data.check_in_reminder_enabled === '1' || response.data.check_in_reminder_enabled === true
      }
      
      // 獲取商家地址
      if (response.data.business_address !== undefined) {
        businessAddress.value = response.data.business_address || ''
      }
    }
  } catch (err) {
    if (import.meta.env.DEV) {
      console.error('Error fetching check-in settings:', err)
    }
  }
}

// 儲存報到提醒設定
async function saveCheckInSettings() {
  loading.value = true
  try {
    // 儲存報到提醒開關
    await apiPost('/settings', {
      key: 'check_in_reminder_enabled',
      value: checkInReminderEnabled.value ? '1' : '0',
      type: 'boolean'
    })
    
    // 儲存商家地址
    await apiPost('/settings', {
      key: 'business_address',
      value: businessAddress.value,
      type: 'string'
    })
    
    checkInSuccessMessage.value = '報到提醒設定已儲存'
    
    setTimeout(() => {
      checkInSuccessMessage.value = ''
    }, 3000)
    
  } catch (err) {
    checkInSuccessMessage.value = `儲存失敗: ${err.message}`
    setTimeout(() => {
      checkInSuccessMessage.value = ''
    }, 3000)
  } finally {
    loading.value = false
  }
}
*/


// 頁面載入時獲取設定
onMounted(() => {
  fetchSettings()
})
</script>


<style scoped>
/* Tailwind 已涵蓋商業級樣式，無需額外樣式 */
</style>
