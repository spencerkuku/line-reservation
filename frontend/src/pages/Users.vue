<template>
  <div class="min-h-screen bg-gray-50 p-6">
    <!-- 頁面標題區域 -->
    <div class="mb-8">
      <h1 class="text-3xl font-bold text-gray-900">管理人員</h1>
      <p class="text-gray-600 mt-2">管理系統使用者與權限設定</p>
    </div>

    <!-- 操作欄 -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div class="flex items-center space-x-4">
          <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
              <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
              </svg>
            </div>
            <input
              v-model="search"
              type="text"
              placeholder="搜尋使用者..."
              class="w-full sm:w-80 pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            />
          </div>
        </div>
        <button
          @click="showAddModal = true"
          class="inline-flex items-center px-4 py-2.5 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors"
        >
          <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
          </svg>
          新增管理人員
        </button>
      </div>
    </div>

    <!-- 使用者表格 -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
      <!-- 載入狀態 -->
      <div v-if="loading" class="p-12 text-center">
        <div class="inline-flex items-center justify-center w-16 h-16 bg-blue-100 rounded-full mb-4">
          <svg class="animate-spin w-8 h-8 text-blue-600" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
        </div>
        <p class="text-gray-600 font-medium">載入中...</p>
      </div>

      <!-- 錯誤狀態 -->
      <div v-else-if="error" class="p-12 text-center">
        <div class="inline-flex items-center justify-center w-16 h-16 bg-red-100 rounded-full mb-4">
          <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.732 15.5c-.77.833.192 2.5 1.732 2.5z" />
          </svg>
        </div>
        <p class="text-red-600 font-medium mb-2">{{ error }}</p>
        <button @click="fetchUsers" class="text-blue-600 hover:text-blue-800 font-medium">重新載入</button>
      </div>

      <!-- 空狀態 -->
      <div v-else-if="filteredUsers.length === 0" class="p-12 text-center">
        <div class="inline-flex items-center justify-center w-16 h-16 bg-gray-100 rounded-full mb-4">
          <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z" />
          </svg>
        </div>
        <p class="text-gray-600 font-medium">查無符合的使用者</p>
        <p class="text-gray-500 text-sm mt-1">嘗試調整搜尋條件或新增管理人員</p>
      </div>

      <!-- 表格內容 -->
      <div v-else class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">使用者</th>
              <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">聯絡資訊</th>
              <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">角色</th>
              <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">狀態</th>
              <th class="px-6 py-4 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">操作</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr v-for="user in filteredUsers" :key="user.id" class="hover:bg-gray-50 transition-colors">
              <!-- 使用者資訊 -->
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex items-center">
                  <div class="flex-shrink-0 h-10 w-10">
                    <div class="h-10 w-10 rounded-full bg-gradient-to-r from-blue-400 to-blue-600 flex items-center justify-center">
                      <span class="text-sm font-medium text-white">{{ user.name?.charAt(0) || 'U' }}</span>
                    </div>
                  </div>
                  <div class="ml-4">
                    <div class="text-sm font-medium text-gray-900">{{ user.name }}</div>
                    <div class="text-sm text-gray-500">ID: {{ user.id }}</div>
                  </div>
                </div>
              </td>
              <!-- 聯絡資訊 -->
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-gray-900">{{ user.email }}</div>
              </td>
              <!-- 角色 -->
              <td class="px-6 py-4 whitespace-nowrap">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                  {{ user.role || '使用者' }}
                </span>
              </td>
              <!-- 狀態 -->
              <td class="px-6 py-4 whitespace-nowrap">
                <span :class="statusClass(user.status)" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium">
                  {{ statusText(user.status) }}
                </span>
              </td>
              <!-- 操作按鈕 -->
              <td class="px-6 py-4 whitespace-nowrap text-center">
                <div class="flex items-center justify-center space-x-2">
                  <button
                    @click="editUser(user)"
                    class="inline-flex items-center px-3 py-1.5 bg-blue-100 text-blue-700 text-xs font-medium rounded-md hover:bg-blue-200 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors"
                  >
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                    編輯
                  </button>
                  <button
                    @click="toggleUserStatus(user)"
                    :class="user.status === 'Active' ? 'bg-yellow-100 text-yellow-700 hover:bg-yellow-200 focus:ring-yellow-500' : 'bg-green-100 text-green-700 hover:bg-green-200 focus:ring-green-500'"
                    class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-md focus:outline-none focus:ring-2 transition-colors"
                  >
                    <svg v-if="user.status === 'Active'" class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728L5.636 5.636m12.728 12.728L18.364 5.636M5.636 18.364l12.728-12.728" />
                    </svg>
                    <svg v-else class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    {{ user.status === 'Active' ? '停權' : '啟用' }}
                  </button>
                  <button
                    @click="deleteUser(user)"
                    class="inline-flex items-center px-3 py-1.5 bg-red-100 text-red-700 text-xs font-medium rounded-md hover:bg-red-200 focus:outline-none focus:ring-2 focus:ring-red-500 transition-colors"
                  >
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                    刪除
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- 編輯用戶模態框 -->
    <div v-if="showEditModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-md transform transition-all">
        <div class="p-6">
          <!-- 模態框標題 -->
          <div class="flex items-center justify-between mb-6">
            <h3 class="text-xl font-semibold text-gray-900">編輯用戶</h3>
            <button @click="cancelEdit" class="text-gray-400 hover:text-gray-600 transition-colors">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
          
          <!-- 表單 -->
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">姓名</label>
              <input 
                v-model="editingUser.name" 
                type="text" 
                class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors"
                placeholder="請輸入姓名"
              >
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">電子郵件</label>
              <input 
                v-model="editingUser.email" 
                type="email" 
                class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors"
                placeholder="請輸入電子郵件"
              >
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">電話</label>
              <input 
                v-model="editingUser.phone" 
                type="text" 
                class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors"
                placeholder="請輸入電話號碼"
              >
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">角色</label>
              <select 
                v-model="editingUser.role" 
                class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors"
              >
                <option value="user">使用者</option>
                <option value="admin">系統管理員</option>
              </select>
            </div>
          </div>
          
          <!-- 按鈕 -->
          <div class="flex justify-end space-x-3 mt-8">
            <button 
              @click="cancelEdit" 
              class="px-4 py-2.5 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-500 transition-colors"
            >
              取消
            </button>
            <button 
              @click="saveUser" 
              class="px-4 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors"
            >
              儲存變更
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- 新增用戶模態框 -->
    <div v-if="showAddModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-md transform transition-all">
        <div class="p-6">
          <!-- 模態框標題 -->
          <div class="flex items-center justify-between mb-6">
            <h3 class="text-xl font-semibold text-gray-900">新增管理人員</h3>
            <button @click="cancelAdd" class="text-gray-400 hover:text-gray-600 transition-colors">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
          
          <!-- 表單 -->
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">姓名</label>
              <input 
                v-model="newUser.name" 
                type="text" 
                class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors"
                placeholder="請輸入姓名"
              >
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">電子郵件</label>
              <input 
                v-model="newUser.email" 
                type="email" 
                class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors"
                placeholder="請輸入電子郵件"
              >
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">電話</label>
              <input 
                v-model="newUser.phone" 
                type="text" 
                class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors"
                placeholder="請輸入電話號碼"
              >
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">密碼</label>
              <input 
                v-model="newUser.password" 
                type="password" 
                class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors"
                placeholder="請輸入密碼"
              >
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">確認密碼</label>
              <input 
                v-model="newUser.password_confirmation" 
                type="password" 
                class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors"
                placeholder="請再次輸入密碼"
              >
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">角色</label>
              <select 
                v-model="newUser.role" 
                class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors"
              >
                <option value="user">使用者</option>
                <option value="admin">系統管理員</option>
              </select>
            </div>
          </div>
          
          <!-- 按鈕 -->
          <div class="flex justify-end space-x-3 mt-8">
            <button 
              @click="cancelAdd" 
              class="px-4 py-2.5 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-500 transition-colors"
            >
              取消
            </button>
            <button 
              @click="addUser" 
              class="px-4 py-2.5 bg-green-600 text-white rounded-lg hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 transition-colors"
            >
              新增用戶
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { apiGet, apiPut, apiPost, apiDelete } from '../utils/api.js'

const search = ref('')
const roleFilter = ref('')
const users = ref([])
const loading = ref(false)
const error = ref('')
const showEditModal = ref(false)
const showAddModal = ref(false)
const editingUser = ref(null)
const newUser = ref({
  name: '',
  email: '',
  phone: '',
  password: '',
  password_confirmation: '',
  role: 'user'
})

// 編輯用戶
function editUser(user) {
  editingUser.value = { ...user }
  showEditModal.value = true
}

// 保存編輯
async function saveUser() {
  if (!editingUser.value) return
  
  try {
    await apiPut(`/users/${editingUser.value.id}`, {
      name: editingUser.value.name,
      email: editingUser.value.email,
      phone: editingUser.value.phone || '',
      role: editingUser.value.role
    })
    
    showEditModal.value = false
    editingUser.value = null
    await fetchUsers()
    alert('用戶資料已更新')
  } catch (err) {
    alert(`更新失敗: ${err.message}`)
  }
}

// 取消編輯
function cancelEdit() {
  showEditModal.value = false
  editingUser.value = null
}

// 獲取使用者列表
async function fetchUsers() {
  loading.value = true
  error.value = ''
  try {
    const data = await apiGet('/users')
    users.value = data.data || data
  } catch (err) {
    error.value = err.message
    console.error('Error fetching users:', err)
  } finally {
    loading.value = false
  }
}

// 停權/啟用使用者
async function toggleUserStatus(user) {
  const action = user.status === 'Active' ? '停權' : '啟用'
  if (!confirm(`確定要${action}使用者 ${user.name} 嗎？`)) return
  
  try {
    const newStatus = user.status === 'Active' ? 'Banned' : 'Active'
    await apiPut(`/users/${user.id}/status`, { status: newStatus })
    
    // 重新獲取使用者列表
    await fetchUsers()
    alert(`使用者已${action}`)
  } catch (err) {
    alert(`${action}失敗: ${err.message}`)
  }
}

// 新增用戶
async function addUser() {
  if (!newUser.value.name || !newUser.value.email || !newUser.value.password) {
    alert('請填寫必要資料')
    return
  }
  
  if (newUser.value.password !== newUser.value.password_confirmation) {
    alert('密碼確認不一致')
    return
  }

  try {
    await apiPost('/users', newUser.value)
    await fetchUsers()
    cancelAdd()
    alert('管理人員新增成功')
  } catch (err) {
    alert(`新增失敗: ${err.message}`)
  }
}

// 取消新增
function cancelAdd() {
  showAddModal.value = false
  newUser.value = {
    name: '',
    email: '',
    phone: '',
    password: '',
    password_confirmation: '',
    role: 'user'
  }
}

// 刪除用戶
async function deleteUser(user) {
  if (!confirm(`確定要刪除使用者 ${user.name} 嗎？此操作無法復原！`)) return
  
  try {
    await apiDelete(`/users/${user.id}`)
    await fetchUsers()
    alert('使用者已刪除')
  } catch (err) {
    alert(`刪除失敗: ${err.message}`)
  }
}

const statusClass = (status) => {
  switch (status) {
    case 'Active':
      return 'bg-green-100 text-green-800'
    case 'Inactive':
      return 'bg-yellow-100 text-yellow-800'
    case 'Banned':
      return 'bg-red-100 text-red-800'
    default:
      return 'bg-gray-100 text-gray-800'
  }
}

const statusText = (status) => {
  switch (status) {
    case 'Active':
      return '啟用'
    case 'Inactive':
      return '待審核'
    case 'Banned':
      return '停權'
    default:
      return status
  }
}

const filteredUsers = computed(() => {
  let filtered = users.value

  // 搜尋過濾
  if (search.value.trim()) {
    const keyword = search.value.toLowerCase()
    filtered = filtered.filter(
      (u) =>
        u.name.toLowerCase().includes(keyword) ||
        u.email.toLowerCase().includes(keyword) ||
        (u.role && u.role.toLowerCase().includes(keyword))
    )
  }

  // 角色過濾
  if (roleFilter.value) {
    filtered = filtered.filter(u => u.role === roleFilter.value)
  }

  return filtered
})

// 頁面載入時獲取使用者列表
onMounted(() => {
  fetchUsers()
})
</script>

<style scoped>
/* Tailwind 已涵蓋，無需額外樣式 */
</style>
