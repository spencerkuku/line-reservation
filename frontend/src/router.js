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
            { path: 'settings', name: 'Settings', component: Settings },
        ]
    },
    {
        path: "/login",
        name: "Login",
        component: Login
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
        next({ name: 'Dashboard' })
        return
    }
    
    // 檢查是否為公開頁面
    const publicPages = ['Login', 'NotFound', 'AvailableTimes']
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
    
    // 檢查管理員權限 - 所有管理頁面都只允許管理員訪問
    const adminOnlyPages = ['Dashboard', 'Customers', 'CheckIn', 'Services', 'AvailableTimes', 'Reservations', 'Settings']
    if (adminOnlyPages.includes(to.name) && user && user.role !== 'admin') {
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