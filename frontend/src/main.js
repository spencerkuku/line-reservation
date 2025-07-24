import { createApp } from 'vue'
import './style.css'
import App from './App.vue'
import router from './router'
import { getCsrfCookie } from './utils/api.js'

// 初始化 CSRF token
async function initializeApp() {
    try {
        // 嘗試獲取 CSRF cookie，如果失敗也不影響應用啟動
        console.log('正在初始化 CSRF cookie...');
        const success = await getCsrfCookie();
        if (success) {
            console.log('CSRF cookie 初始化成功');
        } else {
            console.warn('CSRF cookie 初始化失敗，但應用仍會啟動');
        }
    } catch (error) {
        console.warn('CSRF cookie 初始化失敗:', error);
    }
    
    // 創建並掛載應用
    createApp(App)
        .use(router)
        .mount('#app')
}

// 啟動應用
initializeApp();
