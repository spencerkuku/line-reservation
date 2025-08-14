import { createApp } from 'vue'
import './style.css'
import App from './App.vue'
import router from './router'
import { getCsrfCookie } from './utils/api.js'
import logger from './utils/logger.js'

// 初始化日誌系統
logger.info('Application starting', {
    environment: import.meta.env.MODE,
    timestamp: new Date().toISOString()
});

// 監聽路由變化以記錄頁面訪問
router.beforeEach((to, from) => {
    logger.debug(`Route: ${from.name || from.path} -> ${to.name || to.path}`, {
        from_page: from.name || from.path,
        route_params: to.params,
        route_query: to.query
    });
});

// 初始化 CSRF token
async function initializeApp() {
    try {
        // 嘗試獲取 CSRF cookie，如果失敗也不影響應用啟動
        logger.info('Initializing CSRF cookie');
        
        const success = await getCsrfCookie();
        if (success) {
            logger.info('CSRF cookie initialized successfully');
        } else {
            logger.warn('CSRF cookie initialization failed, but app will continue');
        }
    } catch (error) {
        logger.error('CSRF cookie initialization error', error);
    }
    
    // 創建並掛載應用
    const app = createApp(App);
    
    // 全局錯誤處理
    app.config.errorHandler = (err, instance, info) => {
        logger.error('Vue application error', err, {
            component_info: info,
            component_name: instance?.$options.name || 'Unknown'
        });
    };
    
    // 全局警告處理（開發模式）
    if (import.meta.env.DEV) {
        app.config.warnHandler = (msg, instance, trace) => {
            logger.warn('Vue warning', {
                message: msg,
                component_name: instance?.$options.name || 'Unknown',
                trace
            });
        };
    }
    
    app.use(router).mount('#app');
    
    logger.info('Application mounted successfully');
}

// 啟動應用
initializeApp();
