import router from '../router.js'
import logger from './logger.js'
import { SecureStorage, InputSecurity } from './security.js'
import { sanitizeInput, sanitizeName, sanitizeEmail, sanitizePhone, sanitizeNote } from './xss-protection.js'

const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000/api'

// 檢查是否為有效的 token（Laravel Sanctum 使用隨機字符串）
function isValidToken(token) {
    if (!token) return false;
    // Laravel Sanctum token 是隨機字符串，長度通常在 40-80 字符之間
    return typeof token === 'string' && token.length >= 10 && token.length <= 100;
}

// 獲取 CSRF Cookie（用於 Sanctum SPA 認證）
async function getCsrfCookie() {
    try {
        const baseUrl = API_BASE_URL.replace('/api', '');
        const response = await fetch(`${baseUrl}/sanctum/csrf-cookie`, {
            method: 'GET',
            credentials: 'include',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        
        return response.ok;
    } catch (error) {
        // Failed to get CSRF cookie silently
        return false;
    }
}

// 從 Cookie 中獲取 CSRF Token
function getCsrfTokenFromCookie() {
    const name = 'XSRF-TOKEN';
    const value = `; ${document.cookie}`;
    const parts = value.split(`; ${name}=`);
    if (parts.length === 2) {
        const token = parts.pop().split(';').shift();
        return decodeURIComponent(token);
    }
    return null;
}

// 統一的 HTTP 請求函數
async function apiRequest(url, options = {}) {
    const startTime = performance.now();
    const method = options.method || 'GET';
    
    // 記錄 API 請求開始
    logger.apiRequest(method, url, options.body ? JSON.parse(options.body) : null);

    const token = localStorage.getItem('token')
    
    // 驗證URL安全性
    if (!url.startsWith('/')) {
        const error = new Error('Invalid API endpoint');
        logger.error('Invalid API endpoint', error, { url, method });
        throw error;
    }
    
    // 驗證token格式
    if (token && !isValidToken(token)) {
        localStorage.removeItem('token');
        localStorage.removeItem('user');
        const error = new Error('Invalid token format');
        logger.error('Invalid token format', error, { url, method });
        throw error;
    }
    
    // 對於所有狀態變更請求（POST、PUT、DELETE），獲取 CSRF token
    const shouldGetCsrf = ['POST', 'PUT', 'DELETE', 'PATCH'].includes(method.toUpperCase());
    let csrfToken = null;
    
    if (shouldGetCsrf) {
        const csrfSuccess = await getCsrfCookie();
        if (csrfSuccess) {
            // 等待一小段時間確保 cookie 設置完成
            await new Promise(resolve => setTimeout(resolve, 100));
            csrfToken = getCsrfTokenFromCookie();
        }
        
        // Silent CSRF token check
    }
    
    // 預設設定
    const defaultOptions = {
        credentials: shouldGetCsrf ? 'include' : 'same-origin', // 狀態變更請求需要 cookies
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(token && { 'Authorization': `Bearer ${token}` }),
            ...(csrfToken && { 'X-XSRF-TOKEN': csrfToken })
        }
    }
    
    // 合併選項
    const mergedOptions = {
        ...defaultOptions,
        ...options,
        headers: {
            ...defaultOptions.headers,
            ...options.headers
        }
    }

    // 對 GET 類請求禁用瀏覽器快取（不增加自訂標頭以避免 CORS 預檢問題）
    if (method.toUpperCase() === 'GET') {
        mergedOptions.cache = 'no-store'
    }
    
    // 如果有body且是JSON，先進行過濾
    if (mergedOptions.body && mergedOptions.headers['Content-Type'] === 'application/json') {
        try {
            const bodyData = JSON.parse(mergedOptions.body);
            mergedOptions.body = JSON.stringify(sanitizeInput(bodyData));
        } catch (e) {
            // 如果解析失敗，保持原樣
        }
    }
    
    try {
        const response = await fetch(`${API_BASE_URL}${url}`, mergedOptions)
        const duration = performance.now() - startTime;
        
        // 檢查 401 或 403 錯誤
        if (response.status === 401 || response.status === 403) {
            logger.error('Authentication failed', null, {
                url,
                method,
                status: response.status
            });
            
            // 清除本地存儲
            localStorage.removeItem('token')
            localStorage.removeItem('user')
            
            // 重定向到登入頁面
            if (router.currentRoute.value.name !== 'Login') {
                router.push({ name: 'Login' })
            }
            
            // 拋出錯誤
            const errorData = await response.json().catch(() => ({}))
            const error = new Error(errorData.message || '認證失敗，請重新登入');
            logger.apiResponse(method, url, response.status, errorData);
            throw error;
        }
        
        // 檢查速率限制
        if (response.status === 429) {
            const errorData = await response.json().catch(() => ({}))
            logger.apiResponse(method, url, response.status, errorData);
            const error = new Error(errorData.message || '請求過於頻繁，請稍後再試');
            logger.error('Rate limit exceeded', error, { url, method });
            throw error;
        }
        
        // 檢查其他錯誤
        if (!response.ok) {
            const errorData = await response.json().catch(() => ({}))
            logger.apiResponse(method, url, response.status, errorData);
            const error = new Error(errorData.message || `HTTP ${response.status}: ${response.statusText}`);
            logger.error('API request failed', error, { url, method });
            throw error;
        }

        // 記錄成功響應
        const responseData = await response.clone().json().catch(() => null);
        logger.apiResponse(method, url, response.status, responseData);
        
        return response
    } catch (error) {
        const duration = performance.now() - startTime;
        
        // 記錄網絡錯誤
        logger.error('API request failed', error, {
            url,
            method,
            duration_ms: duration
        }, 'api_network');
        
        // 網絡錯誤或其他錯誤
        if (error.name === 'TypeError' && error.message.includes('fetch')) {
            throw new Error('無法連接到服務器，請檢查網絡連接')
        }
        throw error
    }
}

// GET 請求
export async function apiGet(url, params = {}) {
    // 如果有參數，將其添加到URL中
    if (Object.keys(params).length > 0) {
        const searchParams = new URLSearchParams()
        Object.entries(params).forEach(([key, value]) => {
            if (value !== null && value !== undefined && value !== '') {
                searchParams.append(key, value)
            }
        })
        const queryString = searchParams.toString()
        if (queryString) {
            url += (url.includes('?') ? '&' : '?') + queryString
        }
    }
    
    const response = await apiRequest(url, { method: 'GET' })
    return response.json()
}

// POST 請求
export async function apiPost(url, data) {
    const response = await apiRequest(url, {
        method: 'POST',
        body: JSON.stringify(data)
    })
    return response.json()
}

// PUT 請求
export async function apiPut(url, data) {
    const response = await apiRequest(url, {
        method: 'PUT',
        body: JSON.stringify(data)
    })
    return response.json()
}

// DELETE 請求
export async function apiDelete(url) {
    const response = await apiRequest(url, { method: 'DELETE' })
    return response.json()
}

// 檔案上傳請求
export async function apiUpload(url, formData, method = 'POST') {
    const token = localStorage.getItem('token')
    
    const response = await fetch(`${API_BASE_URL}${url}`, {
        method: method,
        credentials: 'same-origin',
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(token && { 'Authorization': `Bearer ${token}` })
            // 不設定 Content-Type，讓瀏覽器自動設定 multipart/form-data
        },
        body: formData
    })
    
    // 檢查 401 或 403 錯誤
    if (response.status === 401 || response.status === 403) {
        localStorage.removeItem('token')
        localStorage.removeItem('user')
        
        if (router.currentRoute.value.name !== 'Login') {
            router.push({ name: 'Login' })
        }
        
        const errorData = await response.json().catch(() => ({}))
        throw new Error(errorData.message || '認證失敗，請重新登入')
    }
    
    if (!response.ok) {
        const errorData = await response.json().catch(() => ({}))
        throw new Error(errorData.message || `HTTP ${response.status}: ${response.statusText}`)
    }
    
    return response.json()
}

// 檢查 token 有效性並返回用戶資料
export async function validateToken() {
    const token = localStorage.getItem('token')
    if (!token) {
        return false
    }
    
    try {
        const userData = await apiGet('/auth/user')
        
        // 更新用戶信息（防止角色變更）
        if (userData.user) {
            localStorage.setItem('user', JSON.stringify(userData.user))
        }
        
        return true
    } catch (error) {
        return false
    }
}

export { API_BASE_URL, getCsrfCookie, getCsrfTokenFromCookie }
