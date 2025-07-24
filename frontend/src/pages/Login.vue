<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { apiPost, getCsrfCookie } from '../utils/api.js'
import GuestLayout from '../components/GuestLayout.vue'

const router = useRouter()
const username = ref('')
const password = ref('')
const loading = ref(false)
const error = ref('')

async function handleLogin() {
  if (!username.value.trim() || !password.value.trim()) {
    error.value = '請輸入帳號和密碼'
    return
  }

  loading.value = true
  error.value = ''

  try {
    // 先確保獲取 CSRF cookie
    console.log('正在獲取 CSRF cookie...')
    const csrfSuccess = await getCsrfCookie()
    console.log('CSRF cookie 獲取結果:', csrfSuccess)
    
    // 檢查是否有 CSRF token
    const csrfToken = document.cookie.includes('XSRF-TOKEN')
    console.log('CSRF token 是否存在:', csrfToken)
    
    console.log('正在嘗試登入...')
    const data = await apiPost('/auth/login', {
      email: username.value.trim(),
      password: password.value.trim()
    })

    // 檢查返回的數據結構
    if (!data.access_token && !data.token) {
      throw new Error('服務器未返回認證令牌')
    }

    if (!data.user) {
      throw new Error('服務器未返回用戶資料')
    }

    // 儲存登入資訊
    const token = data.access_token || data.token
    localStorage.setItem('token', token)
    localStorage.setItem('user', JSON.stringify(data.user))

    // 跳轉到儀表板
    router.push({ name: 'Dashboard' })
  } catch (err) {
    error.value = err.message || '登入失敗'
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