<template>
  <div class="min-h-screen bg-gradient-to-br from-blue-50 via-white to-indigo-50 flex flex-col justify-center items-center px-6 py-24">
    <!-- 裝飾性背景元素 -->
    <div class="absolute inset-0 overflow-hidden">
      <div class="absolute top-20 left-10 w-32 h-32 bg-blue-100 rounded-full mix-blend-multiply filter blur-xl opacity-70 animate-float"></div>
      <div class="absolute top-40 right-20 w-24 h-24 bg-purple-100 rounded-full mix-blend-multiply filter blur-xl opacity-70 animate-float animation-delay-2000"></div>
      <div class="absolute bottom-32 left-32 w-28 h-28 bg-pink-100 rounded-full mix-blend-multiply filter blur-xl opacity-70 animate-float animation-delay-4000"></div>
    </div>

    <div class="relative z-10 text-center max-w-2xl mx-auto">
      <!-- 404 大數字 -->
      <div class="relative">
        <h1 class="text-9xl sm:text-[12rem] font-bold text-transparent bg-clip-text bg-gradient-to-r from-blue-600 via-purple-600 to-indigo-600 leading-none select-none">
          404
        </h1>
        <!-- 裝飾性元素 -->
        <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2">
          <svg class="w-32 h-32 text-gray-200 animate-spin-slow" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
          </svg>
        </div>
      </div>

      <!-- 主要訊息 -->
      <div class="mt-8">
        <h2 class="text-3xl sm:text-4xl font-bold text-gray-900 mb-4">
          Oops! 找不到頁面
        </h2>
        <p class="text-lg text-gray-600 mb-2">
          很抱歉，您要尋找的頁面似乎不存在或已被移除。
        </p>
        <p class="text-base text-gray-500">
          請檢查網址是否正確，或使用下方按鈕返回安全的地方。
        </p>
      </div>

      <!-- 可能的原因卡片 -->
      <div class="mt-12 grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
        <div class="bg-white/60 backdrop-blur-sm rounded-lg p-4 border border-gray-200">
          <div class="w-8 h-8 mx-auto mb-2 text-blue-500">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
            </svg>
          </div>
          <p class="text-gray-700 font-medium">連結錯誤</p>
          <p class="text-gray-500 text-xs mt-1">網址可能已過期或輸入錯誤</p>
        </div>

        <div class="bg-white/60 backdrop-blur-sm rounded-lg p-4 border border-gray-200">
          <div class="w-8 h-8 mx-auto mb-2 text-purple-500">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
            </svg>
          </div>
          <p class="text-gray-700 font-medium">頁面已移除</p>
          <p class="text-gray-500 text-xs mt-1">內容可能已被刪除或重新整理</p>
        </div>

        <div class="bg-white/60 backdrop-blur-sm rounded-lg p-4 border border-gray-200">
          <div class="w-8 h-8 mx-auto mb-2 text-indigo-500">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
            </svg>
          </div>
          <p class="text-gray-700 font-medium">權限不足</p>
          <p class="text-gray-500 text-xs mt-1">可能需要重新登入系統</p>
        </div>
      </div>

      <!-- 操作按鈕 -->
      <div class="mt-12 flex flex-col sm:flex-row items-center justify-center gap-4">
        <button
          @click="$router.go(-1)"
          class="inline-flex items-center px-6 py-3 border border-gray-300 rounded-lg text-base font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200 shadow-sm"
        >
          <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
          </svg>
          返回上一頁
        </button>

        <RouterLink
          :to="{ name: 'Login' }"
          class="inline-flex items-center px-6 py-3 border border-transparent rounded-lg text-base font-medium text-white bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200 shadow-lg transform hover:scale-105"
        >
          <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
          </svg>
          回到首頁
        </RouterLink>
      </div>

      <!-- 幫助資訊 -->
      <div class="mt-16 p-6 bg-white/40 backdrop-blur-sm rounded-xl border border-gray-200">
        <h3 class="text-lg font-semibold text-gray-900 mb-3">需要幫助？</h3>
        <p class="text-gray-600 text-sm mb-4">
          如果您持續遇到問題，請嘗試以下解決方案：
        </p>
        <ul class="text-sm text-gray-500 space-y-2 text-left max-w-md mx-auto">
          <li class="flex items-start">
            <span class="inline-block w-2 h-2 bg-blue-400 rounded-full mt-2 mr-3 flex-shrink-0"></span>
            清除瀏覽器快取和 Cookie
          </li>
          <li class="flex items-start">
            <span class="inline-block w-2 h-2 bg-blue-400 rounded-full mt-2 mr-3 flex-shrink-0"></span>
            重新登入您的帳號
          </li>
          <li class="flex items-start">
            <span class="inline-block w-2 h-2 bg-blue-400 rounded-full mt-2 mr-3 flex-shrink-0"></span>
            聯絡系統管理員尋求協助
          </li>
        </ul>
      </div>
    </div>
  </div>
</template>

<style scoped>
@keyframes float {
  0%, 100% {
    transform: translateY(0px);
  }
  50% {
    transform: translateY(-20px);
  }
}

@keyframes spin-slow {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
}

.animate-float {
  animation: float 6s ease-in-out infinite;
}

.animate-spin-slow {
  animation: spin-slow 20s linear infinite;
}

.animation-delay-2000 {
  animation-delay: 2s;
}

.animation-delay-4000 {
  animation-delay: 4s;
}
</style>