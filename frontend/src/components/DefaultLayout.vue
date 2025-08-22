<template>
  <div class="min-h-screen bg-gray-50">
    <!-- 頂部導航欄 -->
    <Disclosure as="nav" class="bg-white shadow-sm border-b border-gray-200" v-slot="{ open }">
      <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex h-16 items-center justify-between">
          <!-- Logo 和主導航 -->
          <div class="flex items-center">
            <!-- Logo -->
            <div class="flex-shrink-0">
              <div class="h-8 w-8 bg-gradient-to-r from-green-500 to-blue-600 rounded-lg flex items-center justify-center shadow-sm">
                <svg class="h-5 w-5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor">
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
            </div>
            
            <!-- 桌面版導航選單 -->
            <div class="hidden md:block">
              <div class="ml-10 flex items-baseline space-x-1">
                <RouterLink 
                  v-for="item in navigation" 
                  :key="item.name" 
                  :to="item.to" 
                  :class="[
                    route.name === item.to.name 
                      ? 'bg-blue-50 text-blue-700 border-blue-200' 
                      : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50 border-transparent',
                    'px-3 py-2 rounded-lg text-sm font-medium transition-all duration-200 border'
                  ]" 
                  :aria-current="route.name === item.to.name ? 'page' : undefined"
                >
                  {{ item.name }}
                </RouterLink>
              </div>
            </div>
          </div>
          
          <!-- 右側用戶選單 -->
          <div class="hidden md:block">
            <div class="ml-4 flex items-center md:ml-6">
              <!-- 用戶選單 -->
              <Menu as="div" class="relative ml-3">
                <div>
                  <MenuButton class="relative flex items-center rounded-full bg-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 hover:ring-2 hover:ring-gray-300 transition-all">
                    <span class="absolute -inset-1.5" />
                    <span class="sr-only">開啟用戶選單</span>
                    <div class="flex items-center space-x-3 px-3 py-2">
                      <img class="h-8 w-8 rounded-full ring-2 ring-gray-200" :src="userAvatarUrl" alt="用戶頭像" />
                      <div class="text-left">
                        <div class="text-sm font-medium text-gray-900">{{ user.name }}</div>
                        <div class="text-xs text-gray-500">{{ user.role === 'admin' ? '管理員' : '一般用戶' }}</div>
                      </div>
                      <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                      </svg>
                    </div>
                  </MenuButton>
                </div>
                <transition 
                  enter-active-class="transition ease-out duration-100" 
                  enter-from-class="transform opacity-0 scale-95" 
                  enter-to-class="transform opacity-100 scale-100" 
                  leave-active-class="transition ease-in duration-75" 
                  leave-from-class="transform opacity-100 scale-100" 
                  leave-to-class="transform opacity-0 scale-95"
                >
                  <MenuItems class="absolute right-0 z-10 mt-2 w-56 origin-top-right rounded-xl bg-white py-2 shadow-lg ring-1 ring-gray-200 focus:outline-none">
                    <!-- 用戶資訊 -->
                    <div class="px-4 py-3 border-b border-gray-100">
                      <p class="text-sm font-medium text-gray-900">{{ user.name }}</p>
                      <p class="text-sm text-gray-500">{{ user.email }}</p>
                    </div>
                    
                    <!-- 選單項目 -->
                    <MenuItem>
                      <RouterLink
                        :to="{ name: 'Profile' }"
                        class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition-colors"
                      >
                        <svg class="h-4 w-4 mr-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                        個人資訊
                      </RouterLink>
                    </MenuItem>
                    
                    <div class="border-t border-gray-100 mt-2 pt-2">
                      <MenuItem>
                        <button 
                          @click="logout" 
                          class="flex w-full items-center px-4 py-2 text-sm text-red-700 hover:bg-red-50 transition-colors"
                        >
                          <svg class="h-4 w-4 mr-3 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                          </svg>
                          登出系統
                        </button>
                      </MenuItem>
                    </div>
                  </MenuItems>
                </transition>
              </Menu>
            </div>
          </div>
          
          <!-- 手機版選單按鈕 -->
          <div class="-mr-2 flex md:hidden">
            <DisclosureButton class="relative inline-flex items-center justify-center rounded-lg bg-white p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-inset transition-colors">
              <span class="absolute -inset-0.5" />
              <span class="sr-only">開啟主選單</span>
              <Bars3Icon v-if="!open" class="block h-6 w-6" aria-hidden="true" />
              <XMarkIcon v-else class="block h-6 w-6" aria-hidden="true" />
            </DisclosureButton>
          </div>
        </div>
      </div>

      <!-- 手機版選單 -->
      <DisclosurePanel class="md:hidden border-t border-gray-200 bg-white">
        <div class="space-y-1 px-2 pt-2 pb-3">
          <RouterLink 
            v-for="item in navigation" 
            :key="item.name"
            :to="item.to" 
            :class="[
              $route.name === item.to.name 
                ? 'bg-blue-50 border-blue-500 text-blue-700' 
                : 'border-transparent text-gray-600 hover:bg-gray-50 hover:border-gray-300 hover:text-gray-800',
              'block pl-3 pr-4 py-2 border-l-4 text-base font-medium transition-colors'
            ]" 
            :aria-current="$route.name === item.to.name ? 'page' : undefined"
          >
            {{ item.name }}
          </RouterLink>
        </div>
        
        <div class="border-t border-gray-200 pt-4 pb-3">
          <div class="flex items-center px-5">
            <div class="flex-shrink-0">
              <img class="h-10 w-10 rounded-full ring-2 ring-gray-200" :src="userAvatarUrl" alt="用戶頭像" />
            </div>
            <div class="ml-3">
              <div class="text-base font-medium text-gray-800">{{ user.name }}</div>
              <div class="text-sm text-gray-500">{{ user.email }}</div>
            </div>
          </div>
          <div class="mt-3 space-y-1 px-2">
            <DisclosureButton 
              as="button"
              @click="logout" 
              class="block w-full text-left px-3 py-2 rounded-lg text-base font-medium text-red-600 hover:bg-red-50 hover:text-red-700 transition-colors"
            >
              登出系統
            </DisclosureButton>
          </div>
        </div>
      </DisclosurePanel>
    </Disclosure>

    <!-- 主要內容區域 -->
    <main class="flex-1">
      <RouterView />
    </main>
  </div>
</template>

<script setup>
import { useRoute, useRouter } from 'vue-router'
import { ref, onMounted, computed, watch } from 'vue'
import { Disclosure, DisclosureButton, DisclosurePanel, Menu, MenuButton, MenuItem, MenuItems } from '@headlessui/vue'
import { Bars3Icon, BellIcon, XMarkIcon } from '@heroicons/vue/24/outline'
import { validateToken, apiPost } from '../utils/api.js'

const route = useRoute()
const router = useRouter()

const user = ref({
  name: 'Loading...',
  email: '',
  role: 'user',
  imageUrl: 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?ixlib=rb-1.2.1&ixid=eyJhcHBfaWQiOjEyMDd9&auto=format&fit=facearea&facepad=2&w=256&h=256&q=80',
  avatar: null
})

// 根據用戶角色生成導航選單
const navigation = computed(() => {
  const baseNav = [
    { name: '首頁', to: { name: 'Dashboard' } }
  ]
  
  // 只有管理員才能看到管理功能
  if (user.value.role === 'admin') {
    baseNav.push(
      { name: '預約紀錄', to: { name: 'Reservations' } },
      { name: '服務項目', to: { name: 'Services' } },
      { name: '可預約時段', to: { name: 'AvailableTimes' } },
      { name: '客戶管理', to: { name: 'Customers' } },
      { name: '系統設定', to: { name: 'Settings' } }
    )
  }
  
  return baseNav
})

// 計算用戶頭像 URL
const userAvatarUrl = computed(() => {
  if (user.value.avatar) {
    return `http://127.0.0.1:8000/storage/${user.value.avatar}`
  }
  return user.value.imageUrl || 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?ixlib=rb-1.2.1&ixid=eyJhcHBfaWQiOjEyMDd9&auto=format&fit=facearea&facepad=2&w=256&h=256&q=80'
})

// 載入用戶資料
onMounted(async () => {
  const userData = localStorage.getItem('user')
  if (userData) {
    try {
      const parsedUser = JSON.parse(userData)
      user.value.name = parsedUser.name || 'User'
      user.value.email = parsedUser.email || ''
      user.value.role = parsedUser.role || 'user'
      user.value.avatar = parsedUser.avatar || null
    } catch (e) {
      // 如果用戶數據解析失敗，嘗試重新驗證 token
      const isValid = await validateToken()
      if (!isValid) {
        localStorage.removeItem('token')
        localStorage.removeItem('user')
        router.push({ name: 'Login' })
      }
    }
  }
  
  // 監聽 localStorage 變化以即時更新用戶資料
  const handleStorageChange = () => {
    const userData = localStorage.getItem('user')
    if (userData) {
      try {
        const parsedUser = JSON.parse(userData)
        user.value.name = parsedUser.name || 'User'
        user.value.email = parsedUser.email || ''
        user.value.role = parsedUser.role || 'user'
        user.value.avatar = parsedUser.avatar || null
      } catch (e) {
        console.error('Failed to parse user data:', e)
      }
    }
  }
  
  // 監聽 storage 事件（跨標籤頁變化）
  window.addEventListener('storage', handleStorageChange)
  
  // 監聽自定義事件（同標籤頁內變化）
  window.addEventListener('userDataUpdated', handleStorageChange)
})

// 監聽路由變化，在每次導航時驗證 token
watch(route, async (to, from) => {
  // 跳過公開頁面的驗證
  const publicPages = ['Login', 'NotFound']
  if (publicPages.includes(to.name)) {
    return
  }

  const token = localStorage.getItem('token')
  if (!token) {
    router.push({ name: 'Login' })
    return
  }

  // 驗證 token 是否仍然有效
  try {
    const isValid = await validateToken()
    if (!isValid) {
      localStorage.removeItem('token')
      localStorage.removeItem('user')
      router.push({ name: 'Login' })
    }
  } catch (error) {
    // 如果驗證過程出錯，為了安全起見，清除認證信息
    localStorage.removeItem('token')
    localStorage.removeItem('user')
    router.push({ name: 'Login' })
  }
})

async function logout() {
  try {
    const token = localStorage.getItem('token')
    if (token) {
      // 呼叫後端登出 API
      await apiPost('/auth/logout')
    }
  } catch (error) {
    // Logout API call failed silently
  } finally {
    // 無論 API 呼叫是否成功，都清除本地儲存
    localStorage.removeItem('token')
    localStorage.removeItem('user')
    router.push({ name: 'Login' })
  }
}

</script>
