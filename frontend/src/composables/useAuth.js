// 身份驗證相關功能
import { ref } from 'vue'
import { useRouter } from 'vue-router'

// 檢查用戶是否已登入
export function useAuth() {
  const router = useRouter()
  const isAuthenticated = ref(false)
  const user = ref(null)

  // 檢查登入狀態
  function checkAuth() {
    const token = localStorage.getItem('token')
    const userData = localStorage.getItem('user')
    
    if (token && userData) {
      isAuthenticated.value = true
      try {
        user.value = JSON.parse(userData)
      } catch (e) {
        console.error('解析用戶資料失敗:', e)
        // 只清除無效數據，不強制登出
        localStorage.removeItem('token')
        localStorage.removeItem('user')
        isAuthenticated.value = false
        user.value = null
      }
    } else {
      // 沒有token時只設置狀態，不強制重定向
      isAuthenticated.value = false
      user.value = null
    }
  }

  // 手動登出 (用於登出按鈕)
  function logout() {
    localStorage.removeItem('token')
    localStorage.removeItem('user')
    isAuthenticated.value = false
    user.value = null
    router.push({ name: 'Login' })
  }

  // 檢查是否需要認證 (用於需要登入的頁面)
  function requireAuth() {
    if (!isAuthenticated.value) {
      router.push({ name: 'Login' })
      return false
    }
    return true
  }

  // 獲取認證標頭
  function getAuthHeaders() {
    const token = localStorage.getItem('token')
    return {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json'
    }
  }

  // 初始化時檢查
  checkAuth()

  return {
    isAuthenticated,
    user,
    checkAuth,
    logout,
    requireAuth,
    getAuthHeaders
  }
}

import { API_BASE_URL } from '../utils/api.js'

// 統一的 API 請求函數
export async function apiRequest(url, options = {}) {
  const token = localStorage.getItem('token')
  
  const defaultHeaders = {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  }

  // 只有當token存在時才添加Authorization頭
  if (token) {
    defaultHeaders['Authorization'] = `Bearer ${token}`
  }

  const requestOptions = {
    method: 'GET',
    headers: {
      ...defaultHeaders,
      ...options.headers
    },
    ...options
  }

  try {
    const response = await fetch(`${API_BASE_URL}${url}`, requestOptions)

    // 如果是 401 未授權，只在需要認證的情況下處理
    if (response.status === 401) {
      // 如果是需要認證的請求，清除token並重定向
      if (token) {
        localStorage.removeItem('token')
        localStorage.removeItem('user')
        // 只在不是公開頁面時重定向
        if (window.location.hash !== '#/available-times' && 
            window.location.hash !== '#/' && 
            window.location.hash !== '#/login') {
          window.location.href = '/#/login'
        }
      }
      throw new Error('需要登入才能執行此操作')
    }

    // 檢查是否有其他錯誤
    if (!response.ok) {
      const errorText = await response.text()
      console.error('API Error:', response.status, errorText)
      throw new Error(`API請求失敗: ${response.status}`)
    }

    return response
  } catch (error) {
    console.error('API請求錯誤:', error)
    throw error
  }
}
