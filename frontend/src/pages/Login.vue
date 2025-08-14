<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { apiPost, getCsrfCookie } from '../utils/api.js'
import { useLogger, usePerformanceLogger } from '../composables/useLogger.js'
import GuestLayout from '../components/GuestLayout.vue'

const router = useRouter()
const username = ref('')
const password = ref('')
const loading = ref(false)
const error = ref('')

// 日誌功能
const { logInfo, logError, logUserAction, logFormSubmit } = useLogger()
const { measureAsync } = usePerformanceLogger()

// 記錄登入頁面載入
logInfo('Login page loaded')

async function handleLogin() {
  if (!username.value.trim() || !password.value.trim()) {
    error.value = '請輸入帳號和密碼'
    logUserAction('login_validation_failed', {
      reason: 'empty_credentials',
      has_username: !!username.value.trim(),
      has_password: !!password.value.trim()
    })
    return
  }

  loading.value = true
  error.value = ''

  try {
    // 記錄登入嘗試開始
    logUserAction('login_attempt_start', {
      username: username.value
    })

    // 使用性能監控包裝登入流程
    await measureAsync('user_login', async () => {
      // 先確保獲取 CSRF cookie
      logInfo('Getting CSRF cookie')
      
      const csrfSuccess = await getCsrfCookie()
      logInfo('CSRF cookie result', { success: csrfSuccess })
      
      // 檢查是否有 CSRF token
      const csrfToken = document.cookie.includes('XSRF-TOKEN')
      logInfo('CSRF token check', { exists: csrfToken })
      
      logInfo('Attempting login API call')
      
      const data = await apiPost('/auth/login', {
        email: username.value.trim(),
        password: password.value.trim()
      })

      // 檢查返回的數據結構
      if (!data.access_token && !data.token) {
        logError('Login failed', new Error('No access token received'), { data })
        throw new Error('服務器未返回認證令牌')
      }

      if (!data.user) {
        logError('Login failed', new Error('No user data received'), { data })
        throw new Error('服務器未返回用戶資料')
      }

      // 儲存登入資訊
      const token = data.access_token || data.token
      localStorage.setItem('token', token)
      localStorage.setItem('user', JSON.stringify(data.user))

      // 記錄成功登入
      logUserAction('login_success', {
        username: username.value,
        user_id: data.user.id,
        user_name: data.user.name
      })
      
      logInfo('Login successful, redirecting to dashboard')
    })

    // 跳轉到儀表板
    router.push({ name: 'Dashboard' })
    
  } catch (err) {
    error.value = err.message || '登入失敗'
    
    // 記錄登入失敗
    logError('Login failed', err, {
      username: username.value,
      error_message: err.message
    })
    
    logUserAction('login_failed', {
      username: username.value,
      error_message: err.message,
      error_type: err.name
    })
    
    if (import.meta.env.DEV) {
      console.error('Login error:', err)
    }
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <GuestLayout @submit="handleLogin" :loading="loading" :error="error" v-model:username="username" v-model:password="password" />
</template>