// Remove unused and incorrect imports
import { createRouter, createWebHistory } from "vue-router";
// Only import the correct layout once
import DefaultLayout from "./components/DefaultLayout.vue";

import Dashboard from "./pages/Dashboard.vue";
import Services from "./pages/Services.vue";
import AvailableTimes from "./pages/AvailableTimes.vue";
import Reservations from "./pages/Reservations.vue";
import Settings from "./pages/Settings.vue";
import Profile from "./pages/Profile.vue";
import Login from "./pages/Login.vue";
import NotFound from "./pages/NotFound.vue";
import Customers from './pages/Customers.vue'
import CheckIn from './pages/CheckIn.vue'
import Tenants from './pages/Tenants.vue'
import ForceChangePassword from './pages/ForceChangePassword.vue'
import ActivityLogs from './pages/ActivityLogs.vue'
import LineMessageLogs from './pages/LineMessageLogs.vue'
import Subscription from './pages/Subscription.vue'
import { validateToken } from "./utils/api.js"

const routes = [
    {
        path: "/",
        component: DefaultLayout,
        children: [
            { path: '/', name: 'Dashboard', component: Dashboard },
            { path: 'customers', name: 'Customers', component: Customers },
            { path: 'check-in', name: 'CheckIn', component: CheckIn },
            { path: 'services', name: 'Services', component: Services },
            { path: 'available-times', name: 'AvailableTimes', component: AvailableTimes },
            { path: 'reservations', name: 'Reservations', component: Reservations },
            { path: 'profile', name: 'Profile', component: Profile },
            { path: 'subscription', name: 'Subscription', component: Subscription },
            { path: 'settings', name: 'Settings', component: Settings },
            // 系統管理員專用
            { path: 'tenants', name: 'Tenants', component: Tenants, meta: { requiresSystemAdmin: true } },
            { path: 'activity-logs', name: 'ActivityLogs', component: ActivityLogs, meta: { requiresSystemAdmin: true } },
            { path: 'line-message-logs', name: 'LineMessageLogs', component: LineMessageLogs, meta: { requiresSystemAdmin: true } },
        ]
    },
    {
        path: "/login",
        name: "Login",
        component: Login
    },
    {
        path: "/force-change-password",
        name: "ForceChangePassword",
        component: ForceChangePassword
    },
    {
        path: "/:pathMatch(.*)*",
        name: "NotFound",
        component: NotFound
    }
];

const router = createRouter({
    history: createWebHistory(),
    routes
})

// 路由守衛
router.beforeEach(async (to, from, next) => {
    const token = localStorage.getItem('token')
    const userStr = localStorage.getItem('user')
    const isLoggedIn = !!token
    
    let user = null
    if (userStr) {
        try {
            user = JSON.parse(userStr)
        } catch (e) {
            // 如果用戶數據解析失敗，清除相關數據
            localStorage.removeItem('user')
            localStorage.removeItem('token')
        }
    }
    
    // 如果前往登入頁面且已登入，重定向到首頁
    if (to.name === 'Login' && isLoggedIn) {
        // 檢查是否需要強制修改密碼
        if (user && user.must_change_password) {
            next({ name: 'ForceChangePassword' })
            return
        }
        next({ name: 'Dashboard' })
        return
    }
    
    // 檢查是否為公開頁面
    const publicPages = ['Login', 'NotFound', 'AvailableTimes', 'ForceChangePassword']
    const isPublicPage = publicPages.includes(to.name)
    
    // 如果是公開頁面，直接允許訪問
    if (isPublicPage) {
        next()
        return
    }
    
    // 非公開頁面需要登入
    if (!isLoggedIn) {
        next({ name: 'Login' })
        return
    }
    
    // 檢查是否需要強制修改密碼
    if (user && user.must_change_password && to.name !== 'ForceChangePassword') {
        next({ name: 'ForceChangePassword' })
        return
    }
    
    // 如果已登入，驗證 token 是否仍然有效
    if (isLoggedIn) {
        try {
            // 使用統一的 token 驗證
            const isValid = await validateToken()
            
            if (!isValid) {
                localStorage.removeItem('token')
                localStorage.removeItem('user')
                next({ name: 'Login' })
                return
            }
        } catch (error) {
            localStorage.removeItem('token')
            localStorage.removeItem('user')
            next({ name: 'Login' })
            return
        }
    }
    
    // 檢查系統管理員專用頁面
    if (to.meta?.requiresSystemAdmin && (!user || user.role !== 'system_admin')) {
        next({ name: 'Dashboard' })
        return
    }
    
    // 檢查管理員權限 - 所有管理頁面都只允許管理員訪問
    const adminOnlyPages = ['Dashboard', 'Customers', 'CheckIn', 'Services', 'AvailableTimes', 'Reservations', 'Settings']
    if (adminOnlyPages.includes(to.name) && user && user.role !== 'admin' && user.role !== 'system_admin') {
        // 非管理員訪問管理頁面，重定向到登入頁面並顯示錯誤
        alert('權限不足，僅限管理員訪問此系統')
        localStorage.removeItem('token')
        localStorage.removeItem('user')
        next({ name: 'Login' })
        return
    }
    
    next()
})

export default router;