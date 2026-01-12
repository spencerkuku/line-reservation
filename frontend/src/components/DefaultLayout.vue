<template>
  <div class="min-h-screen bg-gray-50">
    <!-- 側邊欄 - 桌面版 -->
    <div :class="[
      'hidden lg:fixed lg:inset-y-0 lg:flex lg:flex-col transition-all duration-300',
      sidebarCollapsed ? 'lg:w-20' : 'lg:w-64'
    ]">
      <div class="flex flex-col flex-grow bg-white border-r border-gray-200 overflow-y-auto">
        <!-- Logo -->
        <div :class="[
          'flex items-center flex-shrink-0 py-5 border-b border-gray-200',
          sidebarCollapsed ? 'justify-center px-2 flex-col space-y-3' : 'justify-between px-6'
        ]">
          <div :class="[
            'flex items-center',
            sidebarCollapsed ? 'flex-col space-y-2' : 'min-w-0'
          ]">
            <div class="h-10 w-10 bg-gradient-to-r from-green-500 to-blue-600 rounded-lg flex items-center justify-center shadow-sm flex-shrink-0">
              <svg class="h-6 w-6 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <g>
                  <rect x="3" y="4" width="18" height="16" rx="2" stroke-width="1.5"/>
                  <path d="M3 10h18" stroke-width="1.5"/>
                  <path d="M8 2v4" stroke-width="1.5" stroke-linecap="round"/>
                  <path d="M16 2v4" stroke-width="1.5" stroke-linecap="round"/>
                  <circle cx="12" cy="15" r="3" stroke-width="1.2"/>
                  <path d="M12 13v2l1 1" stroke-width="1.2" stroke-linecap="round"/>
                </g>
              </svg>
            </div>
            <transition
              enter-active-class="transition-opacity duration-200"
              leave-active-class="transition-opacity duration-150"
              enter-from-class="opacity-0"
              leave-to-class="opacity-0"
            >
              <span v-if="!sidebarCollapsed" class="ml-3 text-lg font-semibold text-gray-900">預約系統</span>
            </transition>
          </div>
          
          <!-- 收合按鈕 -->
          <button
            @click="sidebarCollapsed = !sidebarCollapsed"
            :class="[
              'p-2 rounded-lg hover:bg-gray-100 transition-all duration-200 flex-shrink-0 group',
              sidebarCollapsed ? 'w-full' : ''
            ]"
            :title="sidebarCollapsed ? '展開側邊欄' : '收合側邊欄'"
          >
            <svg :class="[
              'h-5 w-5 text-gray-600 transition-transform duration-200',
              sidebarCollapsed ? 'rotate-180 mx-auto' : ''
            ]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7" />
            </svg>
          </button>
        </div>
        
        <!-- 導航選單 -->
        <nav class="flex-1 px-4 py-4 space-y-6">
          <div v-for="group in navigation" :key="group.label || 'default'" class="space-y-1">
            <transition
              enter-active-class="transition-opacity duration-200"
              leave-active-class="transition-opacity duration-150"
              enter-from-class="opacity-0"
              leave-to-class="opacity-0"
            >
              <div v-if="group.label && !sidebarCollapsed" class="px-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                {{ group.label }}
              </div>
            </transition>
            <RouterLink 
              v-for="item in group.items" 
              :key="item.name" 
              :to="item.to"
              :title="sidebarCollapsed ? item.name : ''"
              :class="[
                route.name === item.to.name 
                  ? 'bg-blue-50 text-blue-700' 
                  : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900 hover:translate-x-1',
                sidebarCollapsed ? 'justify-center px-2' : 'px-3',
                'group flex items-center py-2.5 text-sm font-medium rounded-lg transition-all duration-200'
              ]"
            >
              <component :is="item.icon" :class="[
                'flex-shrink-0',
                sidebarCollapsed ? 'h-6 w-6' : 'h-5 w-5 mr-3'
              ]" />
              <transition
                enter-active-class="transition-opacity duration-200"
                leave-active-class="transition-opacity duration-150"
                enter-from-class="opacity-0"
                leave-to-class="opacity-0"
              >
                <span v-if="!sidebarCollapsed">{{ item.name }}</span>
              </transition>
            </RouterLink>
          </div>
        </nav>
        
        <!-- 用戶資訊區 -->
        <div class="flex-shrink-0 border-t border-gray-200 bg-gray-50">
          <Menu as="div" class="relative">
            <MenuButton :class="[
              'w-full flex items-center p-4 hover:bg-gray-100 transition-colors',
              sidebarCollapsed ? 'justify-center' : ''
            ]">
              <img class="h-10 w-10 rounded-full ring-2 ring-gray-200 flex-shrink-0" :src="userAvatarUrl" alt="用戶頭像" />
              <transition
                enter-active-class="transition-opacity duration-200"
                leave-active-class="transition-opacity duration-150"
                enter-from-class="opacity-0"
                leave-to-class="opacity-0"
              >
                <div v-if="!sidebarCollapsed" class="ml-3 flex-1 text-left">
                  <div class="text-sm font-medium text-gray-900 truncate">{{ user.name }}</div>
                </div>
              </transition>
              <transition
                enter-active-class="transition-opacity duration-200"
                leave-active-class="transition-opacity duration-150"
                enter-from-class="opacity-0"
                leave-to-class="opacity-0"
              >
                <svg v-if="!sidebarCollapsed" class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                </svg>
              </transition>
            </MenuButton>
            
            <transition 
              enter-active-class="transition ease-out duration-100" 
              enter-from-class="transform opacity-0 scale-95" 
              enter-to-class="transform opacity-100 scale-100" 
              leave-active-class="transition ease-in duration-75" 
              leave-from-class="transform opacity-100 scale-100" 
              leave-to-class="transform opacity-0 scale-95"
            >
              <MenuItems class="absolute bottom-full left-4 right-4 mb-2 origin-bottom rounded-xl bg-white py-2 shadow-lg ring-1 ring-gray-200 focus:outline-none">
                <div class="px-4 py-3 border-b border-gray-100">
                  <p class="text-sm font-medium text-gray-900 truncate">{{ user.name }}</p>
                  <p class="text-xs text-gray-500 truncate">{{ user.email }}</p>
                </div>
                
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
                
                <MenuItem>
                  <RouterLink
                    :to="{ name: 'Subscription' }"
                    class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition-colors"
                  >
                    <svg class="h-4 w-4 mr-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                    </svg>
                    訂閱資訊
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
    </div>
    
    <!-- 移動版側邊欄 -->
    <Disclosure as="div" class="lg:hidden" v-slot="{ open, close }">
      <!-- 頂部工具列 -->
      <div class="sticky top-0 z-40 flex h-16 flex-shrink-0 bg-white border-b border-gray-200">
        <DisclosureButton class="px-4 text-gray-500 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-blue-500 lg:hidden">
          <span class="sr-only">開啟側邊欄</span>
          <Bars3Icon v-if="!open" class="h-6 w-6" />
          <XMarkIcon v-else class="h-6 w-6" />
        </DisclosureButton>
        
        <div class="flex flex-1 justify-between px-4">
          <div class="flex items-center">
            <div class="h-8 w-8 bg-gradient-to-r from-green-500 to-blue-600 rounded-lg flex items-center justify-center shadow-sm">
              <svg class="h-5 w-5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <g>
                  <rect x="3" y="4" width="18" height="16" rx="2" stroke-width="1.5"/>
                  <path d="M3 10h18" stroke-width="1.5"/>
                  <path d="M8 2v4" stroke-width="1.5" stroke-linecap="round"/>
                  <path d="M16 2v4" stroke-width="1.5" stroke-linecap="round"/>
                  <circle cx="12" cy="15" r="3" stroke-width="1.2"/>
                  <path d="M12 13v2l1 1" stroke-width="1.2" stroke-linecap="round"/>
                </g>
              </svg>
            </div>
            <span class="ml-2 text-lg font-semibold text-gray-900">預約系統</span>
          </div>
          
          <div class="flex items-center">
            <img class="h-8 w-8 rounded-full ring-2 ring-gray-200" :src="userAvatarUrl" alt="用戶頭像" />
          </div>
        </div>
      </div>
      
      <!-- 移動版選單面板 -->
      <transition
        enter-active-class="transition ease-out duration-200"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition ease-in duration-150"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <DisclosurePanel class="lg:hidden">
          <div class="fixed inset-0 z-40 flex" @click.self="close">
            <transition
              enter-active-class="transition ease-out duration-200"
              enter-from-class="-translate-x-full"
              enter-to-class="translate-x-0"
              leave-active-class="transition ease-in duration-150"
              leave-from-class="translate-x-0"
              leave-to-class="-translate-x-full"
            >
              <div class="relative flex w-full max-w-xs flex-1 flex-col bg-white">
                <div class="h-0 flex-1 overflow-y-auto pb-4">
                  <nav class="mt-5 space-y-6 px-4">
                    <div v-for="group in navigation" :key="group.label || 'default'" class="space-y-1">
                      <div v-if="group.label" class="px-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                        {{ group.label }}
                      </div>
                      <RouterLink 
                        v-for="item in group.items" 
                        :key="item.name"
                        :to="item.to"
                        @click="close"
                        :class="[
                          $route.name === item.to.name 
                            ? 'bg-blue-50 text-blue-700' 
                            : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900 hover:translate-x-1',
                          'group flex items-center px-3 py-2.5 text-base font-medium rounded-lg transition-all duration-200'
                        ]"
                      >
                        <component :is="item.icon" class="mr-3 h-6 w-6 flex-shrink-0" />
                        {{ item.name }}
                      </RouterLink>
                    </div>
                  </nav>
                </div>
                
                <div class="flex flex-shrink-0 border-t border-gray-200 p-4">
                  <div class="flex items-center w-full">
                    <img class="h-10 w-10 rounded-full ring-2 ring-gray-200" :src="userAvatarUrl" alt="用戶頭像" />
                    <div class="ml-3 flex-1">
                      <div class="text-base font-medium text-gray-800 truncate">{{ user.name }}</div>
                      <div class="text-sm text-gray-500 truncate">{{ user.email }}</div>
                    </div>
                  </div>
                </div>
                
                <div class="border-t border-gray-200 px-3 py-2 space-y-1">
                  <RouterLink
                    :to="{ name: 'Profile' }"
                    @click="close"
                    class="flex items-center px-3 py-2 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors"
                  >
                    <svg class="h-5 w-5 mr-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                    個人資訊
                  </RouterLink>
                  
                  <RouterLink
                    :to="{ name: 'Subscription' }"
                    @click="close"
                    class="flex items-center px-3 py-2 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors"
                  >
                    <svg class="h-5 w-5 mr-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                    </svg>
                    訂閱資訊
                  </RouterLink>
                </div>
                
                <div class="border-t border-gray-200 p-3">
                  <button 
                    @click="logout" 
                    class="flex w-full items-center px-3 py-2 rounded-lg text-base font-medium text-red-600 hover:bg-red-50 transition-colors"
                  >
                    <svg class="h-5 w-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                    登出系統
                  </button>
                </div>
              </div>
            </transition>
            
            <div class="w-14 flex-shrink-0" aria-hidden="true"></div>
          </div>
        </DisclosurePanel>
      </transition>
    </Disclosure>

    <!-- 主要內容區域 -->
    <div :class="[
      'transition-all duration-300',
      sidebarCollapsed ? 'lg:pl-20' : 'lg:pl-64'
    ]">
      <main class="flex-1">
        <RouterView />
      </main>
    </div>
  </div>
</template>

<script setup>
import { useRoute, useRouter } from 'vue-router'
import { ref, onMounted, computed, watch } from 'vue'
import { Disclosure, DisclosureButton, DisclosurePanel, Menu, MenuButton, MenuItem, MenuItems } from '@headlessui/vue'
import { 
  Bars3Icon, 
  XMarkIcon,
  HomeIcon,
  BuildingOfficeIcon,
  ClipboardDocumentListIcon,
  ChatBubbleBottomCenterTextIcon,
  ClipboardDocumentCheckIcon,
  CalendarDaysIcon,
  WrenchScrewdriverIcon,
  ClockIcon,
  UsersIcon,
  Cog6ToothIcon
} from '@heroicons/vue/24/outline'
import { validateToken, apiPost } from '../utils/api.js'

const route = useRoute()
const router = useRouter()

// 側邊欄收合狀態
const sidebarCollapsed = ref(false)

const user = ref({
  name: 'Loading...',
  email: '',
  role: 'user',
  avatar: null
})

// 根據用戶角色生成導航選單
const navigation = computed(() => {
  const navGroups = []
  
  // 系統管理員導航
  if (user.value.role === 'system_admin') {
    navGroups.push({
      label: null, // 首頁不需要分組標籤
      items: [
        { name: '首頁', to: { name: 'Dashboard' }, icon: HomeIcon }
      ]
    })
    navGroups.push({
      label: '系統管理',
      items: [
        { name: '租戶管理', to: { name: 'Tenants' }, icon: BuildingOfficeIcon },
        { name: '活動日誌', to: { name: 'ActivityLogs' }, icon: ClipboardDocumentListIcon },
        { name: 'LINE 訊息', to: { name: 'LineMessageLogs' }, icon: ChatBubbleBottomCenterTextIcon }
      ]
    })
  }
  
  // 租戶管理員導航
  if (user.value.role === 'admin') {
    navGroups.push({
      label: null, // 首頁不需要分組標籤
      items: [
        { name: '首頁', to: { name: 'Dashboard' }, icon: HomeIcon }
      ]
    })
    navGroups.push({
      label: '日常業務',
      items: [
        { name: '報到管理', to: { name: 'CheckIn' }, icon: ClipboardDocumentCheckIcon },
        { name: '預約紀錄', to: { name: 'Reservations' }, icon: CalendarDaysIcon },
        { name: '客戶管理', to: { name: 'Customers' }, icon: UsersIcon }
      ]
    })
    navGroups.push({
      label: '設定管理',
      items: [
        { name: '服務項目', to: { name: 'Services' }, icon: WrenchScrewdriverIcon },
        { name: '可預約時段', to: { name: 'AvailableTimes' }, icon: ClockIcon },
        { name: '系統設定', to: { name: 'Settings' }, icon: Cog6ToothIcon }
      ]
    })
  }
  
  return navGroups
})

// 計算用戶頭像 URL
const userAvatarUrl = computed(() => {
  if (user.value.avatar) {
    return `http://127.0.0.1:8000/storage/${user.value.avatar}`
  }
  // 使用用戶名稱的第一個字元生成預設頭像
  const name = user.value.name || 'User'
  const firstChar = encodeURIComponent(name.charAt(0).toUpperCase())
  return `https://ui-avatars.com/api/?name=${firstChar}&background=3b82f6&color=fff&size=128&bold=true`
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
